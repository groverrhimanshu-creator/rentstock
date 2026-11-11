<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * The four reports. Each is ['name' => file name, 'tabs' title, 'cols' => [[label, kind]], 'rows' => [...], 'empty' => text].
 * kind: n (number), m (money), d (date, stored as Y-m-d), or empty for text.
 */
class Reports
{
    public const TABS = ['Stock summary', 'On rent by customer', 'Overdue returns', 'Sales register'];

    public static function data(int $t): array
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
            $rows = DB::table('customers as c')->orderBy('c.name')
                ->selectRaw("c.name,
                    (select count(*) from devices d where d.customer_id = c.id and d.status = 'rented') as items,
                    (select coalesce(sum(l.rent), 0) from rental_lines l join rentals r on r.id = l.rental_id join devices d on d.code = l.code
                        where r.kind = 'challan' and l.returned_at is null and d.status = 'rented' and d.customer_id = c.id) as rent")->get();
            return ['name' => 'on-rent-by-customer', 'cols' => [['Customer', ''], ['Items on rent', 'n'], ['Rent per month', 'm']],
                'rows' => $rows->map(fn ($r) => [$r->name, (int) $r->items, (float) $r->rent])->all()];
        }
        if ($t === 2) {
            $rows = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
                ->where('d.status', 'rented')->whereNotNull('d.return_by')->where('d.return_by', '<', date('Y-m-d'))->orderBy('d.return_by')->orderBy('d.code')
                ->get(['d.code', 'd.return_by', 'i.name as model', 'c.name as customer']);
            return ['name' => 'overdue-returns', 'cols' => [['Barcode', ''], ['Item', ''], ['Customer', ''], ['Was due', 'd']],
                'rows' => $rows->map(fn ($r) => [$prefix . $r->code, $r->model, (string) $r->customer, $r->return_by])->all(), 'empty' => 'Nothing overdue.'];
        }
        $rows = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')->orderBy('s.id')
            ->get(['s.no', 's.created_at', 's.taxable', 's.cgst', 's.sgst', 's.igst', 's.total', 'c.name as customer']);
        return ['name' => 'sales-register', 'cols' => [['Invoice', ''], ['Date', 'd'], ['Customer', ''], ['Taxable', 'm'], ['GST', 'm'], ['Total', 'm']],
            'rows' => $rows->map(fn ($r) => [$r->no, substr((string) $r->created_at, 0, 10), $r->customer, (float) $r->taxable, Tax::r2((float) $r->cgst + (float) $r->sgst + (float) $r->igst), (float) $r->total])->all()];
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
