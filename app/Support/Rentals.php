<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Rental challans and rental invoices: make, edit, delete. Every change to stock happens inside one database transaction. */
class Rentals
{
    /* Gives out the next number of a named counter. $first is used the first time. Safe when two people save at once. */
    public static function counter(string $name, int $first = 1): int
    {
        DB::statement('INSERT INTO counters (name, value) VALUES (?, LAST_INSERT_ID(?)) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)', [$name, $first]);
        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS id')->id;
    }

    private static function companyState(): string
    {
        return (string) DB::table('company_profile')->where('id', 1)->value('state_code');
    }

    private static function addHistory(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('device_history')->insert($chunk);
        }
    }

    /* ---------------------------------------------------------------- challan */

    /*
     * $d: customer_id, type, pay_mode, remark, return_by ('Y-m-d' or null), lines => [['code' => int, 'rent' => float]]
     * The pieces must be in stock. They go on rent with the customer. Returns the new rental id.
     */
    public static function createChallan(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $customer = DB::table('customers')->where('id', $d['customer_id'])->first(['id', 'name']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            $codes = array_column($d['lines'], 'code');
            $devs = DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->get(['code', 'item_id', 'status'])->keyBy('code');
            foreach ($d['lines'] as $l) {
                $dv = $devs[$l['code']] ?? null;
                if (!$dv) {
                    throw new RuntimeException('Barcode ' . Barcodes::text($l['code']) . ' is not in the system.');
                }
                if ($dv->status !== 'in_stock') {
                    throw new RuntimeException(Barcodes::text($l['code']) . ' is ' . strtolower(Stock::STATUS[$dv->status][0] ?? $dv->status) . ', not in stock.');
                }
            }
            $items = DB::table('items')->whereIn('id', $devs->pluck('item_id')->unique()->all())->get(['id', 'hsn', 'gst_percent'])->keyBy('id');

            $no = 'Ch ' . self::counter('challan', 2143);
            $now = now();
            $rid = DB::table('rentals')->insertGetId([
                'kind' => 'challan', 'no' => $no, 'customer_id' => $customer->id, 'type' => $d['type'], 'pay_mode' => $d['pay_mode'],
                'remark' => $d['remark'], 'return_by' => $d['return_by'] ?: null, 'user_id' => $userId, 'created_at' => $now,
            ]);
            $lines = [];
            $hist = [];
            foreach ($d['lines'] as $l) {
                $it = $items[$devs[$l['code']]->item_id];
                $lines[] = [
                    'rental_id' => $rid, 'code' => $l['code'], 'item_id' => $it->id, 'rent' => $l['rent'],
                    'gst_percent' => $it->gst_percent, 'hsn' => config('rentstock.rent_sac', '997315'), 'from_at' => $now,
                ];
                $hist[] = ['code' => $l['code'], 'at' => $now, 'text' => 'Rented to ' . $customer->name . ' · challan ' . $no, 'user_id' => $userId];
            }
            foreach (array_chunk($lines, 500) as $chunk) {
                DB::table('rental_lines')->insert($chunk);
            }
            DB::table('devices')->whereIn('code', $codes)->update([
                'status' => 'rented', 'customer_id' => $customer->id, 'rented_at' => $now, 'return_by' => $d['return_by'] ?: null, 'rent_no' => $no,
            ]);
            self::addHistory($hist);
            return $rid;
        });
    }

    /* Why a challan cannot be deleted, or null when it can. */
    public static function challanDeleteBlocker(int $id): ?string
    {
        $r = DB::table('rentals')->where('id', $id)->where('kind', 'challan')->first(['id', 'no', 'customer_id']);
        if (!$r) {
            return 'That challan was not found.';
        }
        if (DB::table('rental_lines')->where('rental_id', $id)->whereNotNull('returned_at')->exists()) {
            return 'Challan ' . $r->no . ' cannot be deleted. Some of its items have already come back or were replaced.';
        }
        $out = DB::table('rental_lines as l')->join('devices as d', 'd.code', '=', 'l.code')->where('l.rental_id', $id)
            ->where(function ($w) use ($r) {
                $w->where('d.status', '!=', 'rented')->orWhere('d.customer_id', '!=', $r->customer_id)->orWhereNull('d.customer_id')->orWhere('d.rent_no', '!=', $r->no)->orWhereNull('d.rent_no');
            })->count();
        if ($out) {
            return 'Challan ' . $r->no . ' cannot be deleted. ' . $out . ' of its items are no longer on rent with this customer on this challan.';
        }
        $first = DB::table('rental_lines')->where('rental_id', $id)->min('from_at');
        $billed = DB::table('rentals')->where('kind', 'invoice')->where('customer_id', $r->customer_id)
            ->whereDate('period_to', '>=', substr((string) $first, 0, 10))->orderBy('id')->value('no');
        if ($billed) {
            return 'Challan ' . $r->no . ' cannot be deleted. Rental invoice ' . $billed . ' already bills this customer for a period after these items went out. Delete that invoice first.';
        }
        return null;
    }

    /* Deletes the challan and puts its pieces back in stock. */
    public static function deleteChallan(int $id, ?int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $r = DB::table('rentals as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->where('r.id', $id)->where('r.kind', 'challan')->lockForUpdate()->first(['r.id', 'r.no', 'c.name']);
            if (!$r) {
                throw new RuntimeException('That challan was not found.');
            }
            $codes = DB::table('rental_lines')->where('rental_id', $id)->pluck('code')->all();
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->pluck('code');
            }
            if ($m = self::challanDeleteBlocker($id)) {
                throw new RuntimeException($m);
            }
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->update(['status' => 'in_stock', 'customer_id' => null, 'rented_at' => null, 'return_by' => null, 'rent_no' => null]);
                $now = now();
                self::addHistory(array_map(fn ($c) => ['code' => $c, 'at' => $now, 'text' => 'Rental cancelled · challan ' . $r->no . ' deleted', 'user_id' => $userId], $codes));
            }
            DB::table('rentals')->where('id', $id)->delete();   // the lines go with it
            Audit::log('delete', 'rental challan', $r->no, 'Customer ' . $r->name . ' · ' . count($codes) . ' pieces back in stock');
        });
    }

    /* Edit: type, payment mode, remark, expected return date and the rent of each line. $d['rents'] is [line id => rent]. */
    public static function updateChallan(int $id, array $d, ?int $userId): void
    {
        DB::transaction(function () use ($id, $d) {
            $r = DB::table('rentals')->where('id', $id)->where('kind', 'challan')->lockForUpdate()->first();
            if (!$r) {
                throw new RuntimeException('That challan was not found.');
            }
            $changes = [];
            foreach (DB::table('rental_lines')->where('rental_id', $id)->get(['id', 'rent']) as $l) {
                if (array_key_exists($l->id, $d['rents']) && abs($d['rents'][$l->id] - (float) $l->rent) > 0.0001) {
                    DB::table('rental_lines')->where('id', $l->id)->update(['rent' => $d['rents'][$l->id]]);
                    $changes[] = 'line ' . $l->id . ' rent ' . $l->rent . ' to ' . $d['rents'][$l->id];
                }
            }
            DB::table('rentals')->where('id', $id)->update(['type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'remark' => $d['remark'], 'return_by' => $d['return_by'] ?: null]);
            if (($r->return_by ?: null) !== ($d['return_by'] ?: null)) {
                // pieces still out on this challan follow the new expected return date
                $codes = DB::table('rental_lines')->where('rental_id', $id)->whereNull('returned_at')->pluck('code')->all();
                if ($codes) {
                    DB::table('devices')->whereIn('code', $codes)->where('rent_no', $r->no)->update(['return_by' => $d['return_by'] ?: null]);
                }
                $changes[] = 'return date ' . ($r->return_by ?: 'none') . ' to ' . ($d['return_by'] ?: 'none');
            }
            if ($r->type !== $d['type']) {
                $changes[] = 'type ' . $r->type . ' to ' . $d['type'];
            }
            Audit::log('edit', 'rental challan', $r->no, $changes ? implode('; ', $changes) : 'details');
        });
    }

    /* ---------------------------------------------------------------- rental invoice */

    /*
     * What to bill a customer for a period: one row per piece that was out at least one day (see RentMath::billLines for $full).
     * Each row: code, bc, item_id, name, cat, days, P, rate, price.
     */
    public static function billLines(int $customerId, string $from, string $to, bool $full = true): array
    {
        $segs = DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')
            ->where('r.kind', 'challan')->where('r.customer_id', $customerId)
            ->orderBy('r.id')->orderBy('l.id')->get(['l.code', 'l.item_id', 'l.rent', 'l.from_at', 'l.returned_at', 'l.open_replacement_id'])
            ->map(fn ($x) => ['code' => (int) $x->code, 'item_id' => (int) $x->item_id, 'rate' => (float) $x->rent, 'from' => (string) $x->from_at, 'returned' => $x->returned_at, 'repl' => $x->open_replacement_id !== null])->all();
        $rows = RentMath::billLines($segs, $from, $to, $full);
        if (!$rows) {
            return [];
        }
        $items = DB::table('items as i')->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereIn('i.id', array_unique(array_column($rows, 'item_id')))->get(['i.id', 'i.name', 'c.name as cat'])->keyBy('id');
        $prefix = Barcodes::prefix();
        foreach ($rows as &$x) {
            $it = $items[$x['item_id']];
            $x['bc'] = $prefix . $x['code'];
            $x['name'] = $it->name;
            $x['cat'] = (string) ($it->cat ?? '');
        }
        unset($x);
        return $rows;
    }

    /* The first rental invoice that already bills one of $codes for dates inside the period, or null. */
    public static function overlapping(int $customerId, array $codes, string $from, string $to): ?string
    {
        if (!$codes) {
            return null;
        }
        return DB::table('rentals as r')->join('rental_lines as l', 'l.rental_id', '=', 'r.id')
            ->where('r.kind', 'invoice')->where('l.line_kind', 'rent')->where('r.customer_id', $customerId)->whereIn('l.code', $codes)
            ->whereDate('r.period_from', '<=', $to)->whereDate('r.period_to', '>=', $from)->orderBy('r.id')->value('r.no');
    }

    /*
     * $d: customer_id, type, pay_mode, remark, period_from, period_to ('Y-m-d'), full (bool), prices => [code => amount],
     *     sale_lines => [['code' => int|null, 'item_id' => int, 'qty' => int, 'price' => float]]   (items sold on the same invoice)
     * The days and rent are worked out again here. Only the pieces named in prices are billed, at the amount given.
     * Sold items leave stock exactly as in a normal sale.
     */
    public static function createInvoice(array $d, ?int $userId): int
    {
        return DB::transaction(function () use ($d, $userId) {
            $customer = DB::table('customers')->where('id', $d['customer_id'])->lockForUpdate()->first(['id', 'name', 'state_code']);
            if (!$customer) {
                throw new RuntimeException('Please choose a customer.');
            }
            $rows = array_values(array_filter(self::billLines($customer->id, $d['period_from'], $d['period_to'], !empty($d['full'])), fn ($x) => isset($d['prices'][$x['code']])));
            if (!$rows) {
                throw new RuntimeException('Nothing to bill. No items were on rent with this customer in that period.');
            }
            if ($no = self::overlapping($customer->id, array_column($rows, 'code'), $d['period_from'], $d['period_to'])) {
                throw new RuntimeException('Rental invoice ' . $no . ' already bills this customer for some of these items in these dates. Remove those items from the list, or ask the Admin to delete that invoice first.');
            }
            $sale = $d['sale_lines'] ?? [];
            if ($sale) {
                Sales::checkLines($sale);
            }
            $mode = Tax::mode((string) $customer->state_code, $d['type'], self::companyState());
            $ids = array_unique(array_merge(array_column($rows, 'item_id'), array_column($sale, 'item_id')));
            $items = DB::table('items')->whereIn('id', $ids)->get(['id', 'hsn', 'gst_percent'])->keyBy('id');
            $calc = [];
            foreach ($rows as $x) {
                $calc[] = ['qty' => 1, 'price' => $d['prices'][$x['code']], 'gst' => (float) $items[$x['item_id']]->gst_percent];
            }
            foreach ($sale as $l) {
                $calc[] = ['qty' => $l['qty'], 'price' => $l['price'], 'gst' => (float) $items[$l['item_id']]->gst_percent];
            }
            $t = Tax::totals($calc, $mode);

            $no = Sales::nextNo();
            $rid = DB::table('rentals')->insertGetId([
                'kind' => 'invoice', 'no' => $no, 'customer_id' => $customer->id, 'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'remark' => $d['remark'],
                'period_from' => $d['period_from'], 'period_to' => $d['period_to'], 'tax_mode' => $mode,
                'taxable' => $t['taxable'], 'cgst' => $t['cg'], 'sgst' => $t['sg'], 'igst' => $t['ig'], 'total' => $t['total'],
                'user_id' => $userId, 'created_at' => now(),
            ]);
            $lines = [];
            foreach ($rows as $x) {
                $it = $items[$x['item_id']];
                $lines[] = [
                    'rental_id' => $rid, 'line_kind' => 'rent', 'qty' => 1, 'code' => $x['code'], 'item_id' => $x['item_id'], 'rent' => $x['rate'], 'price' => $d['prices'][$x['code']],
                    'days' => $x['days'], 'period_days' => $x['P'], 'gst_percent' => $it->gst_percent, 'hsn' => config('rentstock.rent_sac', '997315'),
                ];
            }
            foreach ($sale as $l) {
                $it = $items[$l['item_id']];
                $lines[] = [
                    'rental_id' => $rid, 'line_kind' => 'sale', 'qty' => $l['qty'], 'code' => $l['code'] ?: null, 'item_id' => $l['item_id'], 'rent' => 0, 'price' => $l['price'],
                    'days' => null, 'period_days' => null, 'gst_percent' => $it->gst_percent, 'hsn' => $it->hsn,
                ];
            }
            foreach (array_chunk($lines, 500) as $chunk) {
                DB::table('rental_lines')->insert($chunk);
            }
            if ($sale) {
                Sales::takeLines($sale, $customer, $no, $userId);
            }
            return $rid;
        });
    }

    /* Why a rental invoice cannot be edited or deleted, or null when it can. */
    public static function invoiceBlocker(int $id, bool $forDelete): ?string
    {
        $r = DB::table('rentals')->where('id', $id)->where('kind', 'invoice')->first(['id', 'no', 'paid', 'customer_id']);
        if (!$r) {
            return 'That invoice was not found.';
        }
        if ((float) $r->paid > 0) {
            return 'This invoice cannot be ' . ($forDelete ? 'deleted' : 'edited') . '. A payment of INR ' . Tax::money($r->paid) . ' is already recorded against it.';
        }
        if ($forDelete) {
            $moved = DB::table('rental_lines as l')->join('devices as d', 'd.code', '=', 'l.code')->where('l.rental_id', $id)->where('l.line_kind', 'sale')
                ->where(function ($w) use ($r) {
                    $w->where('d.status', '!=', 'sold')->orWhere('d.customer_id', '!=', $r->customer_id);
                })->count();
            if ($moved) {
                return 'This invoice cannot be deleted. ' . $moved . ' of its sold items are no longer shown as sold to this customer (they may have moved on since).';
            }
        }
        return null;
    }

    /* Edit: type, payment mode, remark and the amount of each line. $d['prices'] is [line id => amount]. */
    public static function updateInvoice(int $id, array $d, ?int $userId): void
    {
        DB::transaction(function () use ($id, $d) {
            $r = DB::table('rentals as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->where('r.id', $id)->where('r.kind', 'invoice')->lockForUpdate()->first(['r.*', 'c.state_code']);
            if (!$r) {
                throw new RuntimeException('That invoice was not found.');
            }
            if ($m = self::invoiceBlocker($id, false)) {
                throw new RuntimeException($m);
            }
            $mode = Tax::mode((string) $r->state_code, $d['type'], self::companyState());
            $calc = [];
            $changes = [];
            foreach (DB::table('rental_lines')->where('rental_id', $id)->get() as $l) {
                $price = array_key_exists($l->id, $d['prices']) ? $d['prices'][$l->id] : (float) $l->price;
                if (abs($price - (float) $l->price) > 0.0001) {
                    DB::table('rental_lines')->where('id', $l->id)->update(['price' => $price]);
                    $changes[] = 'line ' . $l->id . ' amount ' . $l->price . ' to ' . $price;
                }
                $calc[] = ['qty' => (int) $l->qty, 'price' => $price, 'gst' => (float) $l->gst_percent];
            }
            $t = Tax::totals($calc, $mode);
            DB::table('rentals')->where('id', $id)->update([
                'type' => $d['type'], 'pay_mode' => $d['pay_mode'], 'remark' => $d['remark'], 'tax_mode' => $mode,
                'taxable' => $t['taxable'], 'cgst' => $t['cg'], 'sgst' => $t['sg'], 'igst' => $t['ig'], 'total' => $t['total'],
            ]);
            if ($r->type !== $d['type']) {
                $changes[] = 'type ' . $r->type . ' to ' . $d['type'];
            }
            Audit::log('edit', 'rental invoice', $r->no, ($changes ? implode('; ', $changes) : 'details') . ' · total now ' . $t['total']);
        });
    }

    /* Deletes a rental invoice. Rent lines do not touch stock. Items that were sold on it go back into stock. */
    public static function deleteInvoice(int $id, ?int $userId = null): void
    {
        DB::transaction(function () use ($id, $userId) {
            $r = DB::table('rentals as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->where('r.id', $id)->where('r.kind', 'invoice')->lockForUpdate()->first(['r.id', 'r.no', 'r.total', 'c.name']);
            if (!$r) {
                throw new RuntimeException('That invoice was not found.');
            }
            $sold = DB::table('rental_lines')->where('rental_id', $id)->where('line_kind', 'sale')->get();
            $codes = $sold->pluck('code')->filter()->values()->all();
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->lockForUpdate()->pluck('code');
            }
            if ($m = self::invoiceBlocker($id, true)) {
                throw new RuntimeException($m);
            }
            if ($codes) {
                DB::table('devices')->whereIn('code', $codes)->update(['status' => 'in_stock', 'customer_id' => null]);
                $now = now();
                self::addHistory(array_map(fn ($c) => ['code' => $c, 'at' => $now, 'text' => 'Sale cancelled · invoice ' . $r->no . ' deleted', 'user_id' => $userId], $codes));
            }
            foreach ($sold as $l) {
                if (!$l->code) {
                    DB::statement('INSERT INTO item_qty (item_id, qty) VALUES (?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)', [$l->item_id, $l->qty]);
                }
            }
            DB::table('rentals')->where('id', $id)->delete();   // the lines go with it
            Audit::log('delete', 'rental invoice', $r->no, 'Customer ' . $r->name . ' · total ' . $r->total . ($sold->count() ? ' · ' . $sold->count() . ' sold lines back in stock' : ''));
        });
    }
}
