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
    public const TABS = ['Stock summary', 'On rent by customer', 'Overdue returns', 'Sales register', 'Purchase report', 'Barcode history'];

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
            return self::sales($f);
        }
        if ($t === 5) {
            return self::barcode($f);
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

    /* ---------- Sales register ---------- */

    private static function salesQuery(array $f)
    {
        [$from, $to] = self::monthRange($f);
        $q = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')->whereRaw('date(s.created_at) between ? and ?', [$from, $to]);
        if (!empty($f['customer'])) {
            $q->where('s.customer_id', (int) $f['customer']);
        }
        $term = trim((string) ($f['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . $term . '%';
            $q->where(function ($w) use ($like) {
                $w->where('s.no', 'like', $like)->orWhere('c.name', 'like', $like);
            });
        }
        [$cond, $b] = self::lineFilter($f, 'lx');
        if ($cond !== '') {
            $q->whereRaw('exists (select 1 from sale_lines lx where lx.sale_id = s.id' . $cond . ')', $b);
        }
        return $q;
    }

    private static function salesFilters(array $f, string $from, string $to): array
    {
        return ['from' => $from, 'to' => $to, 'customer' => !empty($f['customer']) ? (int) $f['customer'] : '',
            'item' => trim((string) ($f['item'] ?? '')), 'category' => trim((string) ($f['category'] ?? '')),
            'q' => trim((string) ($f['q'] ?? '')), 'by' => (($f['by'] ?? '') === 'item') ? 'item' : 'bill'];
    }

    /* One row per sale invoice, or (by=item) one row per item. Dates are the invoice dates; the default is last month, like the other reports. */
    private static function sales(array $f): array
    {
        [$from, $to] = self::monthRange($f);
        $common = ['customers' => DB::table('customers')->orderBy('name')->get(['id', 'name']),
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name']), 'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'filters' => self::salesFilters($f, $from, $to)];
        [$cond, $b] = self::lineFilter($f, 'l');

        if (($f['by'] ?? '') === 'item') {
            $rows = self::salesQuery($f)->join('sale_lines as l', 'l.sale_id', '=', 's.id')->join('items as i', 'i.id', '=', 'l.item_id')->leftJoin('categories as ct', 'ct.id', '=', 'i.category_id')
                ->when($cond !== '', fn ($q) => $q->whereRaw('1 = 1' . $cond, $b))
                ->groupBy('i.id', 'i.name', 'ct.name')->orderBy('i.name')
                ->selectRaw("i.name, ct.name as category, count(distinct s.id) as invoices, sum(l.qty) as qty, sum(l.qty * l.price) as taxable,
                    sum(case when s.tax_mode = 'none' then 0 else l.qty * l.price * l.gst_percent / 100 end) as gst")->get();
            $tot = ['qty' => 0, 'taxable' => 0.0, 'gst' => 0.0];
            $out = [];
            foreach ($rows as $r) {
                $taxable = Tax::r2((float) $r->taxable);
                $gst = Tax::r2((float) $r->gst);
                $tot['qty'] += (int) $r->qty; $tot['taxable'] += $taxable; $tot['gst'] += $gst;
                $out[] = [$r->name, (string) $r->category, (int) $r->invoices, (int) $r->qty, (int) $r->qty > 0 ? Tax::r2($taxable / (int) $r->qty) : 0.0, $taxable, $gst, Tax::r2($taxable + $gst)];
            }
            return $common + ['name' => 'sales-by-item',
                'cols' => [['Item', ''], ['Category', ''], ['Invoices', 'n'], ['Qty', 'n'], ['Average price', 'm'], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm']],
                'rows' => $out, 'meta' => [],
                'summary' => [['Items', (string) count($out)], ['Qty', (string) $tot['qty']], ['Taxable', Tax::money($tot['taxable'])], ['GST', Tax::money($tot['gst'])], ['Total', Tax::money($tot['taxable'] + $tot['gst'])]],
                'note' => 'One row per item for the dates and filters chosen (item totals, before the invoice round-off).'];
        }

        $rows = self::salesQuery($f)->orderByRaw('date(s.created_at) desc')->orderByDesc('s.id')
            ->selectRaw("s.id, s.no, s.created_at, s.taxable, s.cgst, s.sgst, s.igst, s.total, s.tax_mode, c.name as customer,
                (select group_concat(concat(i2.name, ' x', l2.qty) order by l2.id separator ', ') from sale_lines l2 join items i2 on i2.id = l2.item_id where l2.sale_id = s.id" . str_replace(' l.', ' l2.', $cond) . ") as products,
                (select coalesce(sum(l.qty), 0) from sale_lines l where l.sale_id = s.id" . $cond . ") as qty,
                (select coalesce(sum(l.qty * l.price), 0) from sale_lines l where l.sale_id = s.id" . $cond . ") as ltaxable,
                (select coalesce(sum(l.qty * l.price * l.gst_percent / 100), 0) from sale_lines l where l.sale_id = s.id" . $cond . ") as lgst", array_merge($b, $b, $b, $b))->get();
        $filtered = $cond !== '';   // with an item / category chosen the amounts are those of the matching lines only
        $tot = ['taxable' => 0.0, 'gst' => 0.0, 'total' => 0.0];
        $out = [];
        foreach ($rows as $r) {
            if ($filtered) {
                $taxable = Tax::r2((float) $r->ltaxable);
                $gst = $r->tax_mode === 'none' ? 0.0 : Tax::r2((float) $r->lgst);
                $total = Tax::r2($taxable + $gst);
            } else {
                $taxable = (float) $r->taxable;
                $gst = Tax::r2((float) $r->cgst + (float) $r->sgst + (float) $r->igst);
                $total = (float) $r->total;
            }
            $tot['taxable'] += $taxable; $tot['gst'] += $gst; $tot['total'] += $total;
            $out[] = [$r->no, substr((string) $r->created_at, 0, 10), $r->customer, (string) $r->products, (int) $r->qty, $taxable, $gst, $total];
        }
        return $common + ['name' => 'sales-register',
            'cols' => [['Invoice', ''], ['Date', 'd'], ['Customer', ''], ['Products', ''], ['Qty', 'n'], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm']],
            'rows' => $out, 'meta' => $rows->pluck('id')->all(),
            'summary' => [['Invoices', (string) count($out)], ['Taxable', Tax::money($tot['taxable'])], ['GST', Tax::money($tot['gst'])], ['Total', Tax::money($tot['total'])]],
            'note' => 'Invoices are picked by invoice date. With an item or category chosen, the amounts are those of the matching lines only.'];
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
        if (self::lineFilter($f, 'lx')[0] !== '') {
            [$cond, $b] = self::lineFilter($f, 'lx');
            $q->whereRaw('exists (select 1 from purchase_lines lx where lx.purchase_id = p.id' . $cond . ')', $b);
        }
        return $q;
    }

    /* " and ..." condition (and its bindings) that keeps only the purchase lines of the chosen item / category. $a = the purchase_lines alias. */
    private static function like(string $v): string
    {
        return '%' . addcslashes(trim($v), '%_\\') . '%';
    }

    private static function lineFilter(array $f, string $a): array
    {
        $cond = '';
        $b = [];
        if (trim((string) ($f['item'] ?? '')) !== '') {
            $cond .= " and $a.item_id in (select id from items where name like ?)";
            $b[] = self::like((string) $f['item']);
        }
        if (trim((string) ($f['category'] ?? '')) !== '') {
            $cond .= " and $a.item_id in (select i9.id from items i9 join categories c9 on c9.id = i9.category_id where c9.name like ?)";
            $b[] = self::like((string) $f['category']);
        }
        return [$cond, $b];
    }

    /* Item-wise view: one row per item over the same dates / supplier / filters. */
    private static function purchasesByItem(array $f): array
    {
        [$from, $to] = self::purchaseRange($f);
        [$cond, $b] = self::lineFilter($f, 'l');
        $rows = self::purchaseQuery($f)->join('purchase_lines as l', 'l.purchase_id', '=', 'p.id')->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->when($cond !== '', fn ($q) => $q->whereRaw('1 = 1' . $cond, $b))
            ->groupBy('i.id', 'i.name', 'c.name')->orderBy('i.name')
            ->selectRaw("i.name, c.name as category, count(distinct p.id) as purchases, sum(l.qty) as qty, sum(l.qty * l.rate) as taxable,
                sum(case when p.type = 'GST' then l.qty * l.rate * i.gst_percent / 100 else 0 end) as gst")->get();
        $tot = ['qty' => 0, 'taxable' => 0.0, 'gst' => 0.0];
        $out = [];
        foreach ($rows as $r) {
            $taxable = Tax::r2((float) $r->taxable);
            $gst = Tax::r2((float) $r->gst);
            $tot['qty'] += (int) $r->qty; $tot['taxable'] += $taxable; $tot['gst'] += $gst;
            $out[] = [$r->name, (string) $r->category, (int) $r->purchases, (int) $r->qty, (int) $r->qty > 0 ? Tax::r2($taxable / (int) $r->qty) : 0.0, $taxable, $gst, Tax::r2($taxable + $gst)];
        }
        return ['name' => 'purchase-by-item',
            'cols' => [['Item', ''], ['Category', ''], ['Purchases', 'n'], ['Qty', 'n'], ['Average rate', 'm'], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm']],
            'rows' => $out, 'meta' => [], 'empty' => null,
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name']), 'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'filters' => self::purchaseFilters($f, $from, $to),
            'summary' => [['Items', (string) count($out)], ['Qty', (string) $tot['qty']], ['Taxable', Tax::money($tot['taxable'])], ['GST', Tax::money($tot['gst'])], ['Total', Tax::money($tot['taxable'] + $tot['gst'])]],
            'note' => 'One row per item, for the dates and filters chosen. GST is worked out from each item\'s GST % (none for Bill of supply).'];
    }

    private static function purchaseFilters(array $f, string $from, string $to): array
    {
        return ['from' => $from, 'to' => $to, 'supplier' => !empty($f['supplier']) ? (int) $f['supplier'] : '',
            'item' => trim((string) ($f['item'] ?? '')), 'category' => trim((string) ($f['category'] ?? '')),
            'by' => (($f['by'] ?? '') === 'item') ? 'item' : 'bill'];
    }

    /* One row per purchase, by the invoice date the user entered with the purchase. The "Items" count opens what is inside. */
    private static function purchases(array $f): array
    {
        if (($f['by'] ?? '') === 'item') {
            return self::purchasesByItem($f);
        }
        [$from, $to] = self::purchaseRange($f);
        [$c2, $b2] = self::lineFilter($f, 'l2');
        [$c1, $b1] = self::lineFilter($f, 'l');
        $rows = self::purchaseQuery($f)->orderByRaw('coalesce(p.inv_date, date(p.created_at)) desc')->orderByDesc('p.id')
            ->selectRaw("p.id, p.inv_no, coalesce(p.inv_date, date(p.created_at)) as idate, p.created_at, p.type, p.pay_mode, s.name as supplier, st.name as store,
                (select group_concat(concat(i2.name, ' x', l2.qty) order by l2.id separator ', ') from purchase_lines l2 join items i2 on i2.id = l2.item_id where l2.purchase_id = p.id" . $c2 . ") as products,
                (select count(*) from purchase_lines l where l.purchase_id = p.id" . $c1 . ") as nlines,
                (select coalesce(sum(l.qty), 0) from purchase_lines l where l.purchase_id = p.id" . $c1 . ") as qty,
                (select coalesce(sum(l.qty * l.rate), 0) from purchase_lines l where l.purchase_id = p.id" . $c1 . ") as taxable,
                (select coalesce(sum(l.qty * l.rate * i.gst_percent / 100), 0) from purchase_lines l join items i on i.id = l.item_id where l.purchase_id = p.id" . $c1 . ") as gst", array_merge($b2, $b1, $b1, $b1, $b1))->get();
        $tot = ['taxable' => 0.0, 'gst' => 0.0];
        $out = [];
        foreach ($rows as $r) {
            $taxable = Tax::r2((float) $r->taxable);
            $gst = $r->type === 'GST' ? Tax::r2((float) $r->gst) : 0.0;   // "Bill of supply" has no GST
            $tot['taxable'] += $taxable;
            $tot['gst'] += $gst;
            $out[] = [$r->idate, $r->inv_no, $r->supplier, $r->type, $r->pay_mode, (string) $r->store, (string) $r->products, (int) $r->nlines, (int) $r->qty, $taxable, $gst, Tax::r2($taxable + $gst), substr((string) $r->created_at, 0, 10)];
        }
        return ['name' => 'purchase-report',
            'cols' => [['Invoice date', 'd'], ['Invoice no', ''], ['Supplier', ''], ['Type', ''], ['Payment', ''], ['Store', ''], ['Products', ''], ['Items', 'c'], ['Qty', 'n'], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm'], ['Entered on', 'd']],
            'rows' => $out, 'meta' => $rows->pluck('id')->all(),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name']), 'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'filters' => self::purchaseFilters($f, $from, $to),
            'summary' => [['Purchases', (string) count($out)], ['Taxable', Tax::money($tot['taxable'])], ['GST', Tax::money($tot['gst'])], ['Total', Tax::money($tot['taxable'] + $tot['gst'])]],
            'note' => 'Purchases are picked by the invoice date entered with the purchase. GST is worked out from each item\'s GST % (none for Bill of supply).'];
    }

    /* What is inside purchases: one row per line, with the barcodes it made. $ids = purchase ids. */
    public static function purchaseLines(array $ids, array $f = []): array
    {
        if (!$ids) {
            return [];
        }
        $lines = DB::table('purchase_lines as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('items as i', 'i.id', '=', 'l.item_id')->whereIn('l.purchase_id', $ids)
            ->when(self::lineFilter($f, 'l')[0] !== '', fn ($q) => $q->whereRaw('1 = 1' . self::lineFilter($f, 'l')[0], self::lineFilter($f, 'l')[1]))
            ->orderBy('p.id')->orderBy('l.id')
            ->get(['l.id', 'l.purchase_id', 'l.config', 'l.qty', 'l.rate', 'l.barcoded', 'l.hsn', 'l.brand_tags', 'i.name as item', 'i.hsn as item_hsn', 'i.gst_percent', 'p.inv_no', 'p.inv_date', 'p.created_at', 'p.type', 's.name as supplier']);
        $codes = DB::table('devices')->whereIn('purchase_id', $ids)->orderBy('code')->get(['purchase_line_id', 'code'])->groupBy('purchase_line_id');
        $tagRows = DB::table('devices')->whereIn('purchase_id', $ids)->orderBy('code')->get(['purchase_line_id', 'brand_tag'])->groupBy('purchase_line_id');
        $out = [];
        foreach ($lines as $l) {
            $c = $codes[$l->id] ?? null;
            $amount = Tax::r2((float) $l->qty * (float) $l->rate);
            $out[] = [
                'purchase_id' => (int) $l->purchase_id,
                'date' => Docs::d($l->inv_date ?: substr((string) $l->created_at, 0, 10)), 'inv_no' => $l->inv_no, 'supplier' => $l->supplier,
                'item' => $l->item, 'config' => (string) $l->config, 'qty' => (int) $l->qty, 'rate' => number_format((float) $l->rate, 2, '.', ''),
                'amount' => number_format($amount, 2, '.', ''), 'gst' => $l->type === 'GST' ? number_format(Tax::r2($amount * (float) $l->gst_percent / 100), 2, '.', '') : '0.00',
                'kind' => $l->barcoded ? 'Barcoded' : 'By quantity', 'hsn' => (string) ($l->hsn ?: $l->item_hsn),
                'tags' => isset($tagRows[$l->id]) ? $tagRows[$l->id]->pluck('brand_tag')->filter()->implode(', ') : (string) ($l->brand_tags ?? ''),
                'barcodes' => $c ? Barcodes::compact($c->pluck('code')->map(fn ($x) => (int) $x)->all()) : '',
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

    /* ---------- Barcode history ---------- */

    public const HIST_TYPES = ['Purchase', 'Stock entry', 'Sale', 'Rental', 'Return', 'Replacement', 'Repair', 'Upgrade', 'Config'];

    /*
     * Everything that has happened to one piece, oldest first, each line with the link to the document behind it.
     * $f['q'] is a barcode (KPI600001 or 600001) or a brand / service tag. A tag that fits several pieces gives a list to choose from.
     */
    public static function barcode(array $f): array
    {
        $q = trim((string) ($f['q'] ?? ''));
        $out = ['name' => 'barcode-history', 'q' => $q, 'device' => null, 'matches' => [], 'events' => [], 'log' => [], 'msg' => '',
            'cols' => [['Date', 'd'], ['Type', ''], ['What happened', ''], ['Document', ''], ['Details', '']], 'rows' => []];
        if ($q === '') {
            return $out;
        }
        $prefix = Barcodes::prefix();
        $base = fn () => DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->leftJoin('stores as st', 'st.id', '=', 'd.store_id')->leftJoin('brands as b', 'b.id', '=', 'i.brand_id')->leftJoin('categories as ca', 'ca.id', '=', 'i.category_id')
            ->select('d.code', 'd.status', 'd.brand_tag', 'd.config', 'd.rent_no', 'd.return_by', 'd.purchase_id', 'd.stock_entry_id', 'i.name as model', 'b.name as brand', 'ca.name as category', 'c.name as customer', 'st.name as store');
        $dev = null;
        $code = Barcodes::parse($q);
        if ($code) {
            $dev = $base()->where('d.code', $code)->first();
        }
        if (!$dev) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $m = $base()->where('d.brand_tag', 'like', $like)->orderBy('d.code')->limit(30)->get();
            if ($m->count() === 1) {
                $dev = $m->first();
            } elseif ($m->count() > 1) {
                $out['matches'] = $m->map(fn ($r) => ['code' => $r->code, 'bc' => $prefix . $r->code, 'model' => $r->model, 'tag' => (string) $r->brand_tag, 'status' => Stock::STATUS[$r->status][0] ?? $r->status])->all();
                return $out;
            }
        }
        if (!$dev) {
            $out['msg'] = 'No piece found for "' . $q . '". Type a barcode such as ' . $prefix . '600001, or a brand / service tag.';
            return $out;
        }
        $code = (int) $dev->code;
        $bc = $prefix . $code;
        $st = Stock::STATUS[$dev->status] ?? [$dev->status, 'idle'];
        $out['device'] = ['code' => $code, 'bc' => $bc, 'model' => $dev->model, 'brand' => (string) $dev->brand, 'category' => (string) $dev->category, 'tag' => (string) $dev->brand_tag,
            'config' => (string) $dev->config, 'status' => $st[0], 'tone' => $st[1], 'customer' => (string) $dev->customer, 'rent_no' => (string) $dev->rent_no, 'store' => (string) $dev->store,
            'url' => Perm::has('stock.view') ? route('stock.device', $code) : ''];
        $ev = [];
        $need = ['Purchase' => 'purchase.view', 'Stock entry' => 'stock.view', 'Sale' => 'sale.view', 'Rental' => 'rental.view|sale.view', 'Return' => 'return.view', 'Replacement' => 'replacement.view', 'Upgrade' => 'consumption.view', 'Config' => 'stock.view', 'Repair' => 'stock.view'];
        $add = function (string $type, $at, string $title, string $doc, string $detail, ?string $url) use (&$ev, $need) {
            if ($url !== null && !Perm::has($need[$type] ?? 'reports.view')) {
                $url = null;   // the line stays, but it is not a link this login may open
            }
            $ev[] = ['at' => (string) $at, 'type' => $type, 'title' => $title, 'doc' => $doc, 'detail' => $detail, 'url' => $url];
        };
        $inr = fn ($v) => '₹' . number_format((float) $v, 2);

        // Bought, or put in stock some other way
        if ($dev->purchase_id) {
            $p = DB::table('purchases as p')->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')->where('p.id', $dev->purchase_id)->first(['p.id', 'p.no', 'p.inv_no', 'p.inv_date', 'p.created_at', 's.name as supplier']);
            if ($p) {
                $add('Purchase', $p->created_at, 'Bought from ' . $p->supplier, 'Purchase ' . ($p->inv_no !== '' ? $p->inv_no : '#' . $p->no), 'Supplier invoice date ' . ($p->inv_date ? Docs::d($p->inv_date) : '-'), route('purchase.show', $p->id));
            }
        } elseif ($dev->stock_entry_id) {
            $e = DB::table('stock_entries')->where('id', $dev->stock_entry_id)->first(['id', 'created_at']);
            if ($e) {
                $add('Stock entry', $e->created_at, 'Added to stock', 'Stock entry ' . $e->id, '', route('stock.entries', ['q' => $e->id]));
            }
        }
        // Sold
        foreach (DB::table('sale_lines as l')->join('sales as s', 's.id', '=', 'l.sale_id')->leftJoin('customers as c', 'c.id', '=', 's.customer_id')->where('l.code', $code)
            ->get(['s.id', 's.no', 's.created_at', 'l.price', 'c.name as customer']) as $r) {
            $add('Sale', $r->created_at, 'Sold to ' . $r->customer, 'Invoice ' . $r->no, 'Price ' . $inr($r->price), route('sale.show', $r->id));
        }
        // On a rental challan or rental invoice
        foreach (DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')->where('l.code', $code)->orderBy('l.id')
            ->get(['r.id', 'r.kind', 'r.no', 'r.created_at', 'r.period_from', 'r.period_to', 'l.rent', 'l.price', 'l.days', 'l.from_at', 'l.returned_at', 'l.return_id', 'l.close_replacement_id', 'c.name as customer']) as $r) {
            if ($r->kind === 'challan') {
                $add('Rental', $r->from_at ?: $r->created_at, 'Given on rent to ' . $r->customer, 'Challan ' . $r->no, 'Rent ' . $inr($r->rent) . ' per month', route('rental.show', $r->id));
                if ($r->returned_at && !$r->return_id && !$r->close_replacement_id) {
                    $add('Rental', $r->returned_at, 'Taken off the challan', 'Challan ' . $r->no, $r->customer, route('rental.show', $r->id));
                }
            } else {
                $add('Rental', $r->created_at, 'Billed on a rental invoice', 'Invoice ' . $r->no, ($r->period_from ? Docs::d($r->period_from) . ' to ' . Docs::d($r->period_to) : '') . ($r->days ? ' · ' . $r->days . ' days' : '') . ' · ' . $inr($r->price) . ' · ' . $r->customer, route('rental.show', $r->id));
            }
        }
        // Came back
        foreach (DB::table('return_lines as l')->join('returns as r', 'r.id', '=', 'l.return_id')->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')->where('l.code', $code)
            ->get(['r.id', 'r.no', 'r.created_at', 'l.cond', 'l.reason', 'l.note', 'c.name as customer']) as $r) {
            $add('Return', $r->created_at, 'Returned by ' . $r->customer, 'Return ' . $r->no, trim('Condition ' . $r->cond . ($r->reason !== '' ? ' · ' . $r->reason : '') . ($r->note !== '' ? ' · ' . $r->note : '')), route('return.index', ['q' => $r->no]));
        }
        // Swapped
        foreach (DB::table('replacements as r')->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')->where(fn ($w) => $w->where('r.old_code', $code)->orWhere('r.new_code', $code))
            ->get(['r.id', 'r.kind', 'r.old_code', 'r.new_code', 'r.cn', 'r.fault', 'r.reason', 'r.created_at', 'c.name as customer']) as $r) {
            $old = (int) $r->old_code === $code;
            $add('Replacement', $r->created_at, $old ? 'Came back from ' . $r->customer . ', replaced by ' . $prefix . $r->new_code : 'Given to ' . $r->customer . ' in place of ' . $prefix . $r->old_code,
                'Replacement' . ($r->cn ? ' · ' . $r->cn : ''), trim(($r->fault ?? '') . ($r->reason !== '' ? ' · ' . $r->reason : '')), route('replacement.index', ['q' => $bc]));
        }
        // Upgrades
        foreach (DB::table('consumptions')->where('code', $code)->get(['id', 'part', 'from_val', 'to_val', 'used_name', 'created_at']) as $r) {
            $add('Upgrade', $r->created_at, 'Upgraded ' . $r->part . ': ' . $r->from_val . ' to ' . $r->to_val, 'Upgrade assets', $r->used_name !== '' ? 'Used from stock: ' . $r->used_name : '', route('consumption.index', ['q' => $bc]));
        }
        // Configuration edits
        foreach (DB::table('device_config_log')->where('code', $code)->whereNotIn('event', ['start', 'upgrade', 'upgrade_undo'])->get(['at', 'event', 'old_config', 'new_config', 'note']) as $r) {
            $add('Config', $r->at, ConfigLog::EVENTS[$r->event] ?? $r->event, '', trim(($r->old_config ? $r->old_config . ' to ' : '') . $r->new_config . ($r->note !== '' ? ' · ' . $r->note : '')), route('stock.device', $code));
        }
        // The plain history lines; the ones about repair are also shown as their own events
        $log = DB::table('device_history')->where('code', $code)->orderBy('at')->orderBy('id')->get(['at', 'text']);
        foreach ($log as $r) {
            if (stripos($r->text, 'repair') !== false) {
                $add('Repair', $r->at, $r->text, '', '', route('stock.device', $code));
            }
        }
        usort($ev, fn ($a, $b) => strcmp($a['at'], $b['at']));
        $out['events'] = $ev;
        $out['log'] = $log->map(fn ($r) => ['at' => (string) $r->at, 'text' => $r->text])->all();
        $out['rows'] = array_map(fn ($e) => [substr($e['at'], 0, 10), $e['type'], $e['title'], $e['doc'], $e['detail']], $ev);
        $out['name'] = 'barcode-history-' . $bc;
        $out['empty'] = '';
        return $out;
    }
}
