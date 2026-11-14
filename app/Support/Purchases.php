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
     * $d: supplier_id, inv_no, inv_date, type, pay_mode, store_id, lines => [[item_id, config, qty, rate, barcode (true/false/null), tags (one brand tag per piece, list)]]
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
                $item = DB::table('items')->where('id', $l['item_id'])->first(['id', 'name', 'tracked', 'hsn']);
                $want = $l['barcode'] === null ? (bool) $item->tracked : (bool) $l['barcode'];
                $config = $l['config'] !== '' ? $l['config'] : $item->name;
                $lid = DB::table('purchase_lines')->insertGetId([
                    'purchase_id' => $pid, 'item_id' => $item->id, 'config' => $config, 'qty' => $l['qty'], 'rate' => $l['rate'], 'barcoded' => $want,
                    'hsn' => ($l['hsn'] ?? '') !== '' ? $l['hsn'] : $item->hsn,
                ]);
                if ($want) {
                    $first = Barcodes::reserve($l['qty']);
                    $now = now();
                    $devices = [];
                    $history = [];
                    $cfglog = [];
                    for ($i = 0; $i < $l['qty']; $i++) {
                        $devices[] = [
                            'code' => $first + $i, 'item_id' => $item->id, 'config' => $config, 'brand_tag' => ($l['tags'][$i] ?? '') !== '' ? $l['tags'][$i] : self::tag(), 'status' => 'in_stock',
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
                } elseif (!Stock::unstocked([$item->id])) {
                    DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$item->id, $l['qty']]);
                }
            }
            return [$pid, $added];
        });
    }

    /* Adds ($delta > 0) or takes off ($delta < 0) quantity of a by-quantity item. Does nothing for items that are not counted in stock. */
    private static function bumpQty(int $itemId, int $delta, string $why): void
    {
        if (Stock::unstocked([$itemId]) || $delta === 0) {
            return;
        }
        if ($delta < 0) {
            $have = (int) DB::table('item_qty')->where('item_id', $itemId)->value('qty');
            if ($have < -$delta) {
                $name = (string) DB::table('items')->where('id', $itemId)->value('name');
                throw new \RuntimeException('Only ' . $have . ' of "' . $name . '" are left in stock, but ' . (-$delta) . ' must come off (' . $why . '). The rest were already sold or used.');
            }
            DB::table('item_qty')->where('item_id', $itemId)->decrement('qty', -$delta);
            return;
        }
        DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$itemId, $delta]);
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
        $unstocked = Stock::unstocked($qty->pluck('item_id')->all());
        foreach ($qty as $l) {
            if (isset($unstocked[(int) $l->item_id])) {
                continue;   // not counted in stock, nothing to take back
            }
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
            $unstocked = Stock::unstocked($lines->where('barcoded', 0)->pluck('item_id')->all());
            foreach ($lines as $l) {
                if (!$l->barcoded && !isset($unstocked[(int) $l->item_id])) {
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
     * Edit: the details at the top and every line: item, config, HSN, rate and quantity.
     * A barcoded line can change item or quantity only while its pieces are in stock and untouched; raising the quantity needs one brand tag per new piece.
     * $d: supplier_id, inv_no, inv_date, type, pay_mode, store_id, lines => [line id => [config, rate, hsn, item_id, qty, tags]]
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
            $supplier = (string) DB::table('suppliers')->where('id', $d['supplier_id'])->value('name');
            foreach (DB::table('purchase_lines')->where('purchase_id', $id)->get() as $l) {
                $new = $d['lines'][$l->id] ?? null;
                if (!$new) {
                    continue;
                }
                $itemId = (int) ($new['item_id'] ?? $l->item_id) ?: (int) $l->item_id;
                $qty = (int) ($new['qty'] ?? $l->qty) ?: (int) $l->qty;
                $codes = DB::table('devices')->where('purchase_line_id', $l->id)->orderBy('code')->lockForUpdate()->pluck('code')->map(fn ($c) => (int) $c)->all();
                $names = DB::table('items')->whereIn('id', [$itemId, $l->item_id])->pluck('name', 'id');

                // 1. A different item.
                if ($itemId !== (int) $l->item_id) {
                    $newItem = DB::table('items')->where('id', $itemId)->first(['id', 'name', 'tracked']);
                    if (!$newItem) {
                        throw new \RuntimeException('One of the items is not in the Item master.');
                    }
                    if ($l->barcoded) {
                        if (!$newItem->tracked) {
                            throw new \RuntimeException('"' . $newItem->name . '" is counted by quantity, so it cannot replace a barcoded item. Choose a barcoded item.');
                        }
                        if ($codes) {
                            if (DB::table('devices')->whereIn('code', $codes)->where('status', '!=', 'in_stock')->exists() || Stock::movedCount($codes) > 0) {
                                throw new \RuntimeException('The item of "' . ($names[$l->item_id] ?? '') . '" cannot be changed. Some of its barcoded pieces have already been sold, rented, repaired or changed.');
                            }
                            DB::table('devices')->whereIn('code', $codes)->update(['item_id' => $itemId]);
                            $now = now();
                            foreach (array_chunk($codes, 500) as $c) {
                                DB::table('device_history')->insert(array_map(fn ($x) => ['code' => $x, 'at' => $now, 'text' => 'Item changed from ' . ($names[$l->item_id] ?? '') . ' to ' . $newItem->name . ' (purchase no ' . $id . ' edited)', 'user_id' => $userId], $c));
                            }
                        }
                    } else {
                        self::bumpQty((int) $l->item_id, -(int) $l->qty, 'it was taken off "' . ($names[$l->item_id] ?? '') . '"');
                        self::bumpQty($itemId, (int) $l->qty, '');
                    }
                    $changes[] = 'line ' . $l->id . ' item "' . ($names[$l->item_id] ?? '') . '" to "' . $newItem->name . '"';
                }

                // 2. A different quantity.
                if ($qty !== (int) $l->qty) {
                    $diff = $qty - (int) $l->qty;
                    $iname = (string) DB::table('items')->where('id', $itemId)->value('name');
                    $cfg = $new['config'] !== '' ? $new['config'] : $iname;
                    if ($l->barcoded && $diff > 0) {
                        $tags = $new['tags'] ?? [];
                        if (count($tags) !== $diff) {
                            throw new \RuntimeException('"' . $iname . '": to add ' . $diff . ' piece' . ($diff === 1 ? '' : 's') . ' enter ' . $diff . ' brand tag' . ($diff === 1 ? '' : 's') . ' in "New brand tags", separated by commas. You entered ' . count($tags) . '.');
                        }
                        $first = Barcodes::reserve($diff);
                        $now = now();
                        $dev = [];
                        $his = [];
                        $cfl = [];
                        for ($i = 0; $i < $diff; $i++) {
                            $dev[] = ['code' => $first + $i, 'item_id' => $itemId, 'config' => $cfg, 'brand_tag' => $tags[$i], 'status' => 'in_stock', 'store_id' => $d['store_id'], 'purchase_id' => $id, 'purchase_line_id' => $l->id, 'created_at' => $now];
                            $his[] = ['code' => $first + $i, 'at' => $now, 'text' => 'Purchased from ' . $supplier . ' (purchase no ' . $id . ', quantity raised)', 'user_id' => $userId];
                            $cfl[] = ['code' => $first + $i, 'event' => 'start', 'new' => $cfg, 'note' => 'Purchased from ' . $supplier . ' (purchase no ' . $id . ')', 'user_id' => $userId, 'at' => $now];
                        }
                        foreach (array_chunk($dev, 500) as $c) {
                            DB::table('devices')->insert($c);
                        }
                        foreach (array_chunk($his, 500) as $c) {
                            DB::table('device_history')->insert($c);
                        }
                        ConfigLog::addMany($cfl);
                    } elseif ($l->barcoded) {
                        $drop = -$diff;
                        $mine = DB::table('devices')->where('purchase_line_id', $l->id)->where('status', 'in_stock')->orderByDesc('code')->pluck('code')->map(fn ($c) => (int) $c)->all();
                        $pick = [];
                        foreach ($mine as $c) {
                            if (Stock::movedCount([$c]) === 0) {
                                $pick[] = $c;
                            }
                            if (count($pick) === $drop) {
                                break;
                            }
                        }
                        if (count($pick) < $drop) {
                            throw new \RuntimeException('"' . $iname . '": the quantity cannot go down by ' . $drop . '. Only ' . count($pick) . ' of its pieces are still in stock and untouched.');
                        }
                        DB::table('devices')->whereIn('code', $pick)->delete();   // their history goes with them
                    } else {
                        self::bumpQty($itemId, $diff, 'the quantity was lowered');
                    }
                    $changes[] = 'line ' . $l->id . ' quantity ' . $l->qty . ' to ' . $qty;
                }

                // 3. Config, HSN and rate.
                $iname = (string) DB::table('items')->where('id', $itemId)->value('name');
                $config = $new['config'] !== '' ? $new['config'] : $iname;
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
                $hsn = ($new['hsn'] ?? '') !== '' ? $new['hsn'] : (string) ($l->hsn ?? '');
                if ($hsn !== (string) ($l->hsn ?? '')) {
                    $changes[] = 'line ' . $l->id . ' HSN ' . ($l->hsn ?: '-') . ' to ' . $hsn;
                }
                if (abs((float) $l->rate - $new['rate']) > 0.0001) {
                    $changes[] = 'line ' . $l->id . ' rate ' . $l->rate . ' to ' . $new['rate'];
                }
                DB::table('purchase_lines')->where('id', $l->id)->update(['item_id' => $itemId, 'qty' => $qty, 'config' => $config, 'rate' => $new['rate'], 'hsn' => $hsn !== '' ? $hsn : null]);
            }
            Audit::log('edit', 'purchase', $id, $changes ? implode('; ', $changes) : 'details at the top');
        });
    }
}
