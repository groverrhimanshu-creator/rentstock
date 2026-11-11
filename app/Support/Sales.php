<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class Sales
{
    /* Next invoice number of the financial year, e.g. KPI/26-27/001. Each year starts again at 001. */
    public static function nextNo(): string
    {
        $fy = Tax::fy(now());
        $name = 'sale-' . $fy;
        DB::statement('INSERT INTO counters (name, value) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)', [$name]);
        $n = (int) DB::selectOne('SELECT LAST_INSERT_ID() AS id')->id;
        return Barcodes::prefix() . '/' . $fy . '/' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /*
     * $d: customer_id, type, pay_mode, remark, lines => [['code' => int|null, 'item_id' => int, 'qty' => int, 'price' => float]]
     * Returns the new sale id. Throws RuntimeException with a plain message when something cannot be sold.
     */
    public static function create(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $customer = DB::table('customers')->where('id', $d['customer_id'])->first(['id', 'name', 'state_code']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            $companyState = (string) DB::table('company_profile')->where('id', 1)->value('state_code');
            $mode = Tax::mode((string) $customer->state_code, $d['type'], $companyState);

            // Barcoded pieces: lock them and make sure each is still in stock.
            $codes = array_values(array_filter(array_column($d['lines'], 'code')));
            $devs = $codes ? DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->get(['code', 'item_id', 'status'])->keyBy('code') : collect();
            $need = [];
            foreach ($d['lines'] as $l) {
                if ($l['code']) {
                    $dv = $devs[$l['code']] ?? null;
                    if (!$dv) {
                        throw new RuntimeException('Barcode ' . Barcodes::text($l['code']) . ' is not in the system.');
                    }
                    if ($dv->status !== 'in_stock') {
                        throw new RuntimeException(Barcodes::text($l['code']) . ' is ' . strtolower(Stock::STATUS[$dv->status][0] ?? $dv->status) . ', not in stock.');
                    }
                } else {
                    $need[$l['item_id']] = ($need[$l['item_id']] ?? 0) + $l['qty'];
                }
            }
            // Quantity items: lock the count and make sure there is enough.
            foreach ($need as $itemId => $qty) {
                $have = (int) DB::table('item_qty')->where('item_id', $itemId)->lockForUpdate()->value('qty');
                if ($have < $qty) {
                    $name = (string) DB::table('items')->where('id', $itemId)->value('name');
                    throw new RuntimeException('Not enough ' . $name . ' in stock (' . $have . ' left).');
                }
            }

            $items = DB::table('items')->whereIn('id', array_column($d['lines'], 'item_id'))->get(['id', 'hsn', 'gst_percent'])->keyBy('id');
            $calc = [];
            foreach ($d['lines'] as $l) {
                $calc[] = ['qty' => $l['qty'], 'price' => $l['price'], 'gst' => (float) $items[$l['item_id']]->gst_percent];
            }
            $t = Tax::totals($calc, $mode);

            $no = self::nextNo();
            $sid = DB::table('sales')->insertGetId([
                'no' => $no, 'customer_id' => $customer->id, 'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'remark' => $d['remark'],
                'tax_mode' => $mode, 'taxable' => $t['taxable'], 'cgst' => $t['cg'], 'sgst' => $t['sg'], 'igst' => $t['ig'], 'total' => $t['total'],
                'user_id' => $userId, 'created_at' => now(),
            ]);

            $now = now();
            $history = [];
            foreach ($d['lines'] as $l) {
                $it = $items[$l['item_id']];
                DB::table('sale_lines')->insert([
                    'sale_id' => $sid, 'item_id' => $l['item_id'], 'code' => $l['code'] ?: null, 'qty' => $l['qty'],
                    'price' => $l['price'], 'gst_percent' => $it->gst_percent, 'hsn' => $it->hsn,
                ]);
                if ($l['code']) {
                    DB::table('devices')->where('code', $l['code'])->update(['status' => 'sold', 'customer_id' => $customer->id]);
                    $history[] = ['code' => $l['code'], 'at' => $now, 'text' => 'Sold to ' . $customer->name . ' · invoice ' . $no, 'user_id' => $userId];
                }
            }
            foreach (array_chunk($history, 500) as $c) {
                DB::table('device_history')->insert($c);
            }
            foreach ($need as $itemId => $qty) {
                DB::table('item_qty')->where('item_id', $itemId)->decrement('qty', $qty);
            }
            return $sid;
        });
    }

    /*
     * For sold items that go on a rental invoice. $lines: [['code' => int|null, 'item_id' => int, 'qty' => int, 'price' => float]].
     * checkLines locks the pieces and counts and throws when something is not in stock. Call it inside a transaction.
     */
    public static function checkLines(array $lines): void
    {
        $codes = array_values(array_filter(array_column($lines, 'code')));
        $devs = $codes ? DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->get(['code', 'item_id', 'status'])->keyBy('code') : collect();
        $need = [];
        foreach ($lines as $l) {
            if ($l['code']) {
                $dv = $devs[$l['code']] ?? null;
                if (!$dv) {
                    throw new RuntimeException('Barcode ' . Barcodes::text($l['code']) . ' is not in the system.');
                }
                if ($dv->status !== 'in_stock') {
                    throw new RuntimeException(Barcodes::text($l['code']) . ' is ' . strtolower(Stock::STATUS[$dv->status][0] ?? $dv->status) . ', not in stock.');
                }
            } else {
                $need[$l['item_id']] = ($need[$l['item_id']] ?? 0) + $l['qty'];
            }
        }
        foreach ($need as $itemId => $qty) {
            $have = (int) DB::table('item_qty')->where('item_id', $itemId)->lockForUpdate()->value('qty');
            if ($have < $qty) {
                $name = (string) DB::table('items')->where('id', $itemId)->value('name');
                throw new RuntimeException('Not enough ' . $name . ' in stock (' . $have . ' left).');
            }
        }
    }

    /* Takes the sold lines out of stock: pieces become sold to the customer, quantity items are reduced. Call after checkLines. */
    public static function takeLines(array $lines, object $customer, string $no, ?int $userId): void
    {
        $now = now();
        $history = [];
        foreach ($lines as $l) {
            if ($l['code']) {
                DB::table('devices')->where('code', $l['code'])->update(['status' => 'sold', 'customer_id' => $customer->id]);
                $history[] = ['code' => $l['code'], 'at' => $now, 'text' => 'Sold to ' . $customer->name . ' · invoice ' . $no, 'user_id' => $userId];
            } else {
                DB::table('item_qty')->where('item_id', $l['item_id'])->decrement('qty', $l['qty']);
            }
        }
        foreach (array_chunk($history, 500) as $c) {
            DB::table('device_history')->insert($c);
        }
    }

    /* Invoice rows: lines with the same item and price are joined into one row, with all their barcodes listed. */
    public static function groupLines($lines): array
    {
        $g = [];
        foreach ($lines as $l) {
            $k = $l->item_id . '|' . $l->price;
            $g[$k] ??= ['item_id' => $l->item_id, 'name' => $l->name, 'hsn' => $l->hsn, 'price' => (float) $l->price, 'gst' => (float) $l->gst_percent, 'qty' => 0, 'codes' => []];
            $g[$k]['qty'] += $l->qty;
            if ($l->code) {
                $g[$k]['codes'][] = (int) $l->code;
            }
        }
        return array_values($g);
    }

    /* Why an invoice cannot be edited or deleted, or null when it can. */
    public static function blocker(int $id, bool $forDelete): ?string
    {
        $s = DB::table('sales')->where('id', $id)->first(['id', 'customer_id', 'paid']);
        if (!$s) {
            return 'That invoice was not found.';
        }
        $verb = $forDelete ? 'deleted' : 'edited';
        if ((float) $s->paid > 0) {
            return 'This invoice cannot be ' . $verb . '. A payment of INR ' . Tax::money($s->paid) . ' is already recorded against it.';
        }
        if ($forDelete) {
            $moved = DB::table('sale_lines as l')->join('devices as d', 'd.code', '=', 'l.code')->where('l.sale_id', $id)
                ->where(function ($w) use ($s) {
                    $w->where('d.status', '!=', 'sold')->orWhere('d.customer_id', '!=', $s->customer_id);
                })->count();
            if ($moved) {
                return 'This invoice cannot be deleted. ' . $moved . ' of its items are no longer shown as sold to this customer (they may have moved on since).';
            }
        }
        return null;
    }

    /* Edit: customer, type, payment mode, remark and the price of each line. Items and quantities stay as saved. $d['prices'] is [line id => price]. */
    public static function update(int $id, array $d, ?int $userId): void
    {
        DB::transaction(function () use ($id, $d, $userId) {
            $s = DB::table('sales')->where('id', $id)->lockForUpdate()->first();
            if (!$s) {
                throw new RuntimeException('That invoice was not found.');
            }
            if ($m = self::blocker($id, false)) {
                throw new RuntimeException($m);
            }
            $customer = DB::table('customers')->where('id', $d['customer_id'])->first(['id', 'name', 'state_code']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            $companyState = (string) DB::table('company_profile')->where('id', 1)->value('state_code');
            $mode = Tax::mode((string) $customer->state_code, $d['type'], $companyState);

            $lines = DB::table('sale_lines')->where('sale_id', $id)->get();
            $calc = [];
            $changes = [];
            foreach ($lines as $l) {
                $price = array_key_exists($l->id, $d['prices']) ? $d['prices'][$l->id] : (float) $l->price;
                if (abs($price - (float) $l->price) > 0.0001) {
                    DB::table('sale_lines')->where('id', $l->id)->update(['price' => $price]);
                    $changes[] = 'line ' . $l->id . ' price ' . $l->price . ' to ' . $price;
                }
                $calc[] = ['qty' => $l->qty, 'price' => $price, 'gst' => (float) $l->gst_percent];
            }
            $t = Tax::totals($calc, $mode);
            DB::table('sales')->where('id', $id)->update([
                'customer_id' => $customer->id, 'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'remark' => $d['remark'],
                'tax_mode' => $mode, 'taxable' => $t['taxable'], 'cgst' => $t['cg'], 'sgst' => $t['sg'], 'igst' => $t['ig'], 'total' => $t['total'],
            ]);
            if ((int) $s->customer_id !== (int) $customer->id) {
                $codes = $lines->pluck('code')->filter()->values()->all();
                if ($codes) {
                    DB::table('devices')->whereIn('code', $codes)->update(['customer_id' => $customer->id]);
                    $now = now();
                    $rows = array_map(fn ($c) => ['code' => $c, 'at' => $now, 'text' => 'Invoice ' . $s->no . ' changed to ' . $customer->name, 'user_id' => $userId], $codes);
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('device_history')->insert($chunk);
                    }
                }
                $changes[] = 'customer changed';
            }
            if ($s->type !== $d['type']) {
                $changes[] = 'type ' . $s->type . ' to ' . $d['type'];
            }
            Audit::log('edit', 'sale invoice', $s->no, ($changes ? implode('; ', $changes) : 'details') . ' · total now ' . $t['total']);
        });
    }

    /* Delete the invoice and put its items back in stock. */
    public static function delete(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $s = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')->where('s.id', $id)->lockForUpdate()->first(['s.id', 's.no', 's.total', 'c.name']);
            if (!$s) {
                throw new RuntimeException('That invoice was not found.');
            }
            $lines = DB::table('sale_lines')->where('sale_id', $id)->get();
            $codes = $lines->pluck('code')->filter()->values()->all();
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->pluck('code');
            }
            if ($m = self::blocker($id, true)) {
                throw new RuntimeException($m);
            }
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->update(['status' => 'in_stock', 'customer_id' => null]);
                $now = now();
                $rows = array_map(fn ($c) => ['code' => $c, 'at' => $now, 'text' => 'Sale cancelled · invoice ' . $s->no . ' deleted', 'user_id' => $userId], $codes);
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('device_history')->insert($chunk);
                }
            }
            foreach ($lines as $l) {
                if (!$l->code) {
                    DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$l->item_id, $l->qty]);
                }
            }
            DB::table('sales')->where('id', $id)->delete();   // the lines go with it
            Audit::log('delete', 'sale invoice', $s->no, 'Customer ' . $s->name . ' · total ' . $s->total . ' · ' . count($codes) . ' pieces back in stock');
        });
    }
}
