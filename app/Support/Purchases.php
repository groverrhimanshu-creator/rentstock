<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Purchases
{
    private static function tag(): string
    {
        $c = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
        $s = '';
        for ($i = 0; $i < 7; $i++) {
            $s .= $c[random_int(0, strlen($c) - 1)];
        }
        return $s;
    }

    /*
     * $d: supplier_id, inv_no, inv_date, type, pay_mode, store_id, lines => [[item_id, config, qty, rate, barcode (true/false/null)]]
     * One barcode is made for every piece of a "with barcode" row. Other rows only raise the quantity count.
     * Returns [purchase id, number of barcoded pieces added].
     */
    public static function create(array $d, ?int $userId): array
    {
        return DB::transaction(function () use ($d, $userId) {
            $supplier = (string) DB::table('suppliers')->where('id', $d['supplier_id'])->value('name');
            $pid = DB::table('purchases')->insertGetId([
                'supplier_id' => $d['supplier_id'], 'inv_no' => $d['inv_no'], 'inv_date' => $d['inv_date'] ?: null,
                'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'store_id' => $d['store_id'], 'user_id' => $userId, 'created_at' => now(),
            ]);
            $added = 0;
            foreach ($d['lines'] as $l) {
                $item = DB::table('items')->where('id', $l['item_id'])->first(['id', 'name', 'tracked']);
                $want = $l['barcode'] === null ? (bool) $item->tracked : (bool) $l['barcode'];
                $config = $l['config'] !== '' ? $l['config'] : $item->name;
                $lid = DB::table('purchase_lines')->insertGetId([
                    'purchase_id' => $pid, 'item_id' => $item->id, 'config' => $config, 'qty' => $l['qty'], 'rate' => $l['rate'], 'barcoded' => $want,
                ]);
                if ($want) {
                    $first = Barcodes::reserve($l['qty']);
                    $now = now();
                    $devices = [];
                    $history = [];
                    $cfglog = [];
                    for ($i = 0; $i < $l['qty']; $i++) {
                        $devices[] = [
                            'code' => $first + $i, 'item_id' => $item->id, 'config' => $config, 'brand_tag' => self::tag(), 'status' => 'in_stock',
                            'store_id' => $d['store_id'], 'purchase_id' => $pid, 'purchase_line_id' => $lid, 'created_at' => $now,
                        ];
                        $history[] = ['code' => $first + $i, 'at' => $now, 'text' => 'Purchased from ' . $supplier . ' (purchase no ' . $pid . ')', 'user_id' => $userId];
                        $cfglog[] = ['code' => $first + $i, 'event' => 'start', 'new' => $config, 'note' => 'Purchased from ' . $supplier . ' (purchase no ' . $pid . ')', 'user_id' => $userId, 'at' => $now];
                    }
                    foreach (array_chunk($devices, 500) as $c) {
                        DB::table('devices')->insert($c);
                    }
                    foreach (array_chunk($history, 500) as $c) {
                        DB::table('device_history')->insert($c);
                    }
                    ConfigLog::addMany($cfglog);
                    $added += $l['qty'];
                } else {
                    DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$item->id, $l['qty']]);
                }
            }
            return [$pid, $added];
        });
    }

    /* ---------- edit and delete ---------- */

    /* Why a purchase cannot be deleted, or null when it can. A purchase can go only while all its stock is still untouched. */
    public static function deleteBlocker(int $id): ?string
    {
        $codes = DB::table('devices')->where('purchase_id', $id)->select('code');
        $away = DB::table('devices')->where('purchase_id', $id)->where('status', '!=', 'in_stock')->count();
        if ($away) {
            return 'This purchase cannot be deleted. ' . $away . ' of its barcoded items are already sold, on rent or in repair. Only a purchase whose items are all still in stock can be deleted.';
        }
        if (DB::table('sale_lines')->whereIn('code', $codes)->exists()) {
            return 'This purchase cannot be deleted. Some of its items appear on a sale invoice.';
        }
        if (($n = Stock::movedCount($codes)) > 0) {
            return 'This purchase cannot be deleted. ' . $n . ' of its barcoded items have been rented, returned or changed before.';
        }
        $qty = DB::table('purchase_lines')->where('purchase_id', $id)->where('barcoded', 0)->groupBy('item_id')->selectRaw('item_id, sum(qty) as q')->get();
        foreach ($qty as $l) {
            $have = (int) DB::table('item_qty')->where('item_id', $l->item_id)->value('qty');
            if ($have < (int) $l->q) {
                $name = (string) DB::table('items')->where('id', $l->item_id)->value('name');
                return 'This purchase cannot be deleted. Only ' . $have . ' of "' . $name . '" are left in stock, but this purchase added ' . (int) $l->q . ' (the rest were already sold or used).';
            }
        }
        return null;
    }

    public static function delete(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id) {
            $p = DB::table('purchases as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')->where('p.id', $id)->lockForUpdate()->first(['p.id', 'p.inv_no', 's.name']);
            if (!$p) {
                throw new \RuntimeException('That purchase was not found.');
            }
            DB::table('devices')->where('purchase_id', $id)->lockForUpdate()->pluck('code');
            if ($m = self::deleteBlocker($id)) {
                throw new \RuntimeException($m);
            }
            $lines = DB::table('purchase_lines')->where('purchase_id', $id)->get();
            foreach ($lines as $l) {
                if (!$l->barcoded) {
                    DB::table('item_qty')->where('item_id', $l->item_id)->decrement('qty', $l->qty);
                }
            }
            $pieces = DB::table('devices')->where('purchase_id', $id)->count();
            DB::table('devices')->where('purchase_id', $id)->delete();   // their history goes with them
            DB::table('purchases')->where('id', $id)->delete();           // the lines go with it
            Audit::log('delete', 'purchase', $id, 'Supplier ' . $p->name . ' · invoice ' . $p->inv_no . ' · ' . $pieces . ' barcoded items removed');
        });
    }

    /*
     * Edit: the details at the top, and the config and rate of each line. Items and quantities stay as saved,
     * because the stock was made from them. $d: supplier_id, inv_no, inv_date, type, pay_mode, store_id, lines => [line id => [config, rate]]
     */
    public static function update(int $id, array $d, ?int $userId): void
    {
        DB::transaction(function () use ($id, $d, $userId) {
            $old = DB::table('purchases')->where('id', $id)->lockForUpdate()->first();
            if (!$old) {
                throw new \RuntimeException('That purchase was not found.');
            }
            DB::table('purchases')->where('id', $id)->update([
                'supplier_id' => $d['supplier_id'], 'inv_no' => $d['inv_no'], 'inv_date' => $d['inv_date'] ?: null,
                'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'store_id' => $d['store_id'],
            ]);
            if ((int) $old->store_id !== (int) $d['store_id']) {
                // Pieces still on the shelf move to the new store.
                DB::table('devices')->where('purchase_id', $id)->where('status', 'in_stock')->update(['store_id' => $d['store_id']]);
            }
            $changes = [];
            foreach (DB::table('purchase_lines')->where('purchase_id', $id)->get() as $l) {
                $new = $d['lines'][$l->id] ?? null;
                if (!$new) {
                    continue;
                }
                $config = $new['config'] !== '' ? $new['config'] : (string) DB::table('items')->where('id', $l->item_id)->value('name');
                if ($config !== $l->config) {
                    $log = [];
                    foreach (DB::table('devices')->where('purchase_line_id', $l->id)->where('status', 'in_stock')->get(['code', 'config']) as $dv) {
                        if ($dv->config !== $config) {
                            $log[] = ['code' => (int) $dv->code, 'event' => 'purchase_edit', 'old' => $dv->config, 'new' => $config, 'note' => 'Config corrected in purchase no ' . $id, 'user_id' => $userId];
                        }
                    }
                    ConfigLog::addMany($log);
                    DB::table('devices')->where('purchase_line_id', $l->id)->where('status', 'in_stock')->update(['config' => $config]);
                    $changes[] = 'line ' . $l->id . ' config "' . $l->config . '" to "' . $config . '"';
                }
                if (abs((float) $l->rate - $new['rate']) > 0.0001) {
                    $changes[] = 'line ' . $l->id . ' rate ' . $l->rate . ' to ' . $new['rate'];
                }
                DB::table('purchase_lines')->where('id', $l->id)->update(['config' => $config, 'rate' => $new['rate']]);
            }
            Audit::log('edit', 'purchase', $id, $changes ? implode('; ', $changes) : 'details at the top');
        });
    }
}
