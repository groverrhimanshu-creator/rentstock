<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * The reports. Each is ['name' => file name, 'cols' => [[label, kind]], 'rows' => [...], 'empty' => text].
 * kind: n (number), m (money), d (date, stored as Y-m-d), c (a count that opens a pop-up, see 'meta'), or empty for text.
 * 'meta' holds one id per row for the pop-up counts. Filters come in as $f (the query string) and go back out in 'filters'.
 */
class Reports
{
    public const TABS = ['Stock summary', 'On rent by customer', 'Overdue returns', 'Sales register', 'Purchase report'];

    public static function data(int $t, array $f = []): array
    {
        $prefix = Barcodes::prefix();
        if ($t === 0) {
            $rows = DB::table('items as i')->leftJoin('item_qty as q', 'q.item_id', '=', 'i.id')->orderBy('i.name')
                ->selectRaw("i.name,
                    (select count(*) from devices d where d.item_id = i.id and d.status = 'in_stock') + coalesce(q.qty, 0) as in_stock,
                    (select count(*) from devices d where d.item_id = i.id and d.status = 'rented') as rented,
                    (select count(*) from devices d where d.item_id = i.id and d.status = 'repair') as repair,
                    (select count(*) from devices d where d.item_id = i.id and d.status = 'sold') as sold")->get();
            return ['name' => 'stock-summary', 'cols' => [['Item', ''], ['In stock', 'n'], ['On rent', 'n'], ['Repair', 'n'], ['Sold', 'n']],
                'rows' => $rows->map(fn ($r) => [$r->name, (int) $r->in_stock, (int) $r->rented, (int) $r->repair, (int) $r->sold])->all()];
        }
        if ($t === 1) {
            return self::onRent($f);
        }
        if ($t === 2) {
            $rows = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
                ->where('d.status', 'rented')->whereNotNull('d.return_by')->where('d.return_by', '<', date('Y-m-d'))->orderBy('d.return_by')->orderBy('d.code')
                ->get(['d.code', 'd.return_by', 'i.name as model', 'c.name as customer']);
            return ['name' => 'overdue-returns', 'cols' => [['Barcode', ''], ['Item', ''], ['Customer', ''], ['Was due', 'd']],
                'rows' => $rows->map(fn ($r) => [$prefix . $r->code, $r->model, (string) $r->customer, $r->return_by])->all(), 'empty' => 'Nothing overdue.'];
        }
        if ($t === 3) {
            $rows = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')->orderBy('s.id')
                ->get(['s.no', 's.created_at', 's.taxable', 's.cgst', 's.sgst', 's.igst', 's.total', 'c.name as customer']);
            return ['name' => 'sales-register', 'cols' => [['Invoice', ''], ['Date', 'd'], ['Customer', ''], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm']],
                'rows' => $rows->map(fn ($r) => [$r->no, substr((string) $r->created_at, 0, 10), $r->customer, (float) $r->taxable, Tax::r2((float) $r->cgst + (float) $r->sgst + (float) $r->igst), (float) $r->total])->all()];
        }
        return self::purchases($f);
    }

    /* ---------- On rent by customer ---------- */

    /* One row per customer (or the one chosen / searched for). The count opens the list of the items on rent. */
    /* From / to of the reports: the whole of last month unless dates are given (1st to last day of the previous calendar month). */
    public static function monthRange(array $f): array
    {
        $ok = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        $from = $ok($f['from'] ?? null) ? $f['from'] : date('Y-m-01', strtotime('first day of last month'));
        $to = $ok($f['to'] ?? null) ? $f['to'] : date('Y-m-t', strtotime('first day of last month'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    /* Rental lines (challan) that were on rent at some time between $from and $to, for the customers table. */
    private static function onRentLines(string $from, string $to)
    {
        return DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')
            ->where('r.kind', 'challan')->whereNotNull('l.code')
            ->whereRaw('date(l.from_at) <= ?', [$to])->whereRaw('(l.returned_at is null or date(l.returned_at) >= ?)', [$from]);
    }

    private static function onRent(array $f): array
    {
        [$from, $to] = self::monthRange($f);
        $cust = (int) ($f['cust'] ?? 0);
        $q = trim((string) ($f['q'] ?? ''));
        $agg = self::onRentLines($from, $to)->selectRaw('r.customer_id, count(*) as items, coalesce(sum(l.rent), 0) as rent')->groupBy('r.customer_id');
        $query = DB::table('customers as c')->joinSub($agg, 'x', 'x.customer_id', '=', 'c.id')->orderBy('c.name');
        if ($cust) {
            $query->where('c.id', $cust);
        }
        if ($q !== '') {
            $query->where('c.name', 'like', '%' . $q . '%');
        }
        $rows = $query->get(['c.id', 'c.name', 'x.items', 'x.rent']);
        return ['name' => 'on-rent-by-customer', 'cols' => [['Customer', ''], ['Items on rent', 'c'], ['Rent per month', 'm']],
            'rows' => $rows->map(fn ($r) => [$r->name, (int) $r->items, (float) $r->rent])->all(),
            'meta' => $rows->pluck('id')->all(),
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name']),
            'filters' => ['cust' => $cust ?: '', 'q' => $q, 'from' => $from, 'to' => $to],
            'note' => 'Shows the items that were on rent at any time between the two dates (default: last month). Rent per month is the monthly rent of those items.'];
    }

    /* The pieces a customer had on rent between $from and $to: [customer name, rows]. */
    public static function onRentItems(int $customerId, array $f = []): array
    {
        [$from, $to] = self::monthRange($f);
        $name = (string) DB::table('customers')->where('id', $customerId)->value('name');
        $prefix = Barcodes::prefix();
        $rows = self::onRentLines($from, $to)->where('r.customer_id', $customerId)
            ->join('items as i', 'i.id', '=', 'l.item_id')->leftJoin('categories as c', 'c.id', '=', 'i.category_id')->leftJoin('devices as d', 'd.code', '=', 'l.code')
            ->orderBy('l.code')->orderBy('l.id')
            ->get(['l.code', 'l.rent', 'l.from_at', 'l.returned_at', 'r.no as challan', 'd.brand_tag', 'd.config', 'd.return_by', 'i.name as item', 'c.name as category']);
        $out = $rows->map(fn ($r) => [
            'barcode' => $prefix . $r->code, 'item' => $r->item, 'category' => (string) $r->category, 'tag' => (string) $r->brand_tag, 'config' => (string) $r->config,
            'challan' => (string) $r->challan, 'since' => Docs::d($r->from_at),
            'due' => $r->returned_at ? 'Returned ' . Docs::d($r->returned_at) : ($r->return_by ? Docs::d($r->return_by) : 'Long term'),
            'rent' => number_format((float) $r->rent, 2, '.', ''),
        ])->all();
        return [$name, $out];
    }

    /* ---------- Purchase report ---------- */

    /* From / to of the purchase report. Default: last month (1st to last day). The dates are the purchase invoice dates. */
    public static function purchaseRange(array $f): array
    {
        $ok = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        return self::monthRange($f);
    }

    private static function purchaseQuery(array $f)
    {
        [$from, $to] = self::purchaseRange($f);
        $q = DB::table('purchases as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')->leftJoin('stores as st', 'st.id', '=', 'p.store_id')
            ->whereRaw('coalesce(p.inv_date, date(p.created_at)) between ? and ?', [$from, $to]);
        if (!empty($f['supplier'])) {
            $q->where('p.supplier_id', (int) $f['supplier']);
        }
        return $q;
    }

    /* One row per purchase, by the invoice date the user entered with the purchase. The "Items" count opens what is inside. */
    private static function purchases(array $f): array
    {
        [$from, $to] = self::purchaseRange($f);
        $rows = self::purchaseQuery($f)->orderByRaw('coalesce(p.inv_date, date(p.created_at)) desc')->orderByDesc('p.id')
            ->selectRaw("p.id, p.inv_no, coalesce(p.inv_date, date(p.created_at)) as idate, p.created_at, p.type, p.pay_mode, s.name as supplier, st.name as store,
                (select count(*) from purchase_lines l where l.purchase_id = p.id) as nlines,
                (select coalesce(sum(l.qty), 0) from purchase_lines l where l.purchase_id = p.id) as qty,
                (select coalesce(sum(l.qty * l.rate), 0) from purchase_lines l where l.purchase_id = p.id) as taxable,
                (select coalesce(sum(l.qty * l.rate * i.gst_percent / 100), 0) from purchase_lines l join items i on i.id = l.item_id where l.purchase_id = p.id) as gst")->get();
        $tot = ['taxable' => 0.0, 'gst' => 0.0];
        $out = [];
        foreach ($rows as $r) {
            $taxable = Tax::r2((float) $r->taxable);
            $gst = $r->type === 'GST' ? Tax::r2((float) $r->gst) : 0.0;   // "Bill of supply" has no GST
            $tot['taxable'] += $taxable;
            $tot['gst'] += $gst;
            $out[] = [$r->idate, $r->inv_no, $r->supplier, $r->type, $r->pay_mode, (string) $r->store, (int) $r->nlines, (int) $r->qty, $taxable, $gst, Tax::r2($taxable + $gst), substr((string) $r->created_at, 0, 10)];
        }
        return ['name' => 'purchase-report',
            'cols' => [['Invoice date', 'd'], ['Invoice no', ''], ['Supplier', ''], ['Type', ''], ['Payment', ''], ['Store', ''], ['Items', 'c'], ['Qty', 'n'], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm'], ['Entered on', 'd']],
            'rows' => $out, 'meta' => $rows->pluck('id')->all(),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
            'filters' => ['from' => $from, 'to' => $to, 'supplier' => !empty($f['supplier']) ? (int) $f['supplier'] : ''],
            'summary' => [['Purchases', (string) count($out)], ['Taxable', Tax::money($tot['taxable'])], ['GST', Tax::money($tot['gst'])], ['Total', Tax::money($tot['taxable'] + $tot['gst'])]],
            'note' => 'Purchases are picked by the invoice date entered with the purchase. GST is worked out from each item\'s GST % (none for Bill of supply).'];
    }

    /* What is inside purchases: one row per line, with the barcodes it made. $ids = purchase ids. */
    public static function purchaseLines(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $lines = DB::table('purchase_lines as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')->whereIn('l.purchase_id', $ids)->orderBy('p.id')->orderBy('l.id')
            ->get(['l.id', 'l.purchase_id', 'l.config', 'l.qty', 'l.rate', 'l.barcoded', 'i.name as item', 'i.gst_percent', 'p.inv_no', 'p.inv_date', 'p.created_at', 'p.type', 's.name as supplier']);
        $codes = DB::table('devices')->whereIn('purchase_id', $ids)->selectRaw('purchase_line_id, count(*) as n, min(code) as lo, max(code) as hi')->groupBy('purchase_line_id')->get()->keyBy('purchase_line_id');
        $out = [];
        foreach ($lines as $l) {
            $c = $codes[$l->id] ?? null;
            $amount = Tax::r2((float) $l->qty * (float) $l->rate);
            $out[] = [
                'purchase_id' => (int) $l->purchase_id,
                'date' => Docs::d($l->inv_date ?: substr((string) $l->created_at, 0, 10)), 'inv_no' => $l->inv_no, 'supplier' => $l->supplier,
                'item' => $l->item, 'config' => (string) $l->config, 'qty' => (int) $l->qty, 'rate' => number_format((float) $l->rate, 2, '.', ''),
                'amount' => number_format($amount, 2, '.', ''), 'gst' => $l->type === 'GST' ? number_format(Tax::r2($amount * (float) $l->gst_percent / 100), 2, '.', '') : '0.00',
                'kind' => $l->barcoded ? 'Barcoded' : 'By quantity',
                'barcodes' => $c ? ($c->n > 1 ? Barcodes::text((int) $c->lo) . ' to ' . Barcodes::text((int) $c->hi) . ' (' . $c->n . ')' : Barcodes::text((int) $c->lo)) : '',
            ];
        }
        return $out;
    }

    /* The purchase ids the report shows for these filters (used by the line-detail download). */
    public static function purchaseIds(array $f): array
    {
        return self::purchaseQuery($f)->orderByRaw('coalesce(p.inv_date, date(p.created_at)) desc')->orderByDesc('p.id')->pluck('p.id')->all();
    }

    public static function csv(array $data): string
    {
        $q = function ($v) {
            $s = (string) ($v ?? '');
            return preg_match('/[",\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        };
        $lines = [implode(',', array_map(fn ($c) => $q($c[0]), $data['cols']))];
        foreach ($data['rows'] as $r) {
            $cells = [];
            foreach ($r as $i => $v) {
                $k = $data['cols'][$i][1];
                $cells[] = $q($k === 'd' ? Docs::d($v) : ($k === 'm' ? number_format((float) $v, 2, '.', '') : $v));
            }
            $lines[] = implode(',', $cells);
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }
}
