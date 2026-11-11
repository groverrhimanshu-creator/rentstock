<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/* Reads a challan or a rental invoice with everything its printed page needs. */
class Docs
{
    /* Dates for the screen: 2026-10-17 becomes 17/10/2026. */
    public static function d(?string $ymd): string
    {
        return $ymd ? date('d/m/Y', strtotime(substr($ymd, 0, 10))) : '';
    }

    /* The rental document with its customer, or null. $customerId limits it to one customer (the customer's own login). */
    public static function rental(int $id, ?int $customerId = null): ?array
    {
        $q = DB::table('rentals as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->where('r.id', $id)
            ->select('r.*', 'c.name as cname', 'c.address', 'c.city', 'c.state', 'c.state_code', 'c.gstin', 'c.phone');
        if ($customerId !== null) {
            $q->where('r.customer_id', $customerId);
        }
        $r = $q->first();
        if (!$r) {
            return null;
        }
        $lines = DB::table('rental_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->leftJoin('categories as ct', 'ct.id', '=', 'i.category_id')
            ->leftJoin('devices as d', 'd.code', '=', 'l.code')
            ->where('l.rental_id', $id)->orderBy('l.id')->get(['l.*', 'i.name', 'ct.name as cat', 'd.config']);
        $rent = $lines->where('line_kind', 'rent');
        return ['r' => $r, 'lines' => $lines, 'groups' => $r->kind === 'invoice' ? self::invoiceGroups($rent) : self::challanGroups($rent),
            'sales' => $r->kind === 'invoice' ? Sales::groupLines($lines->where('line_kind', 'sale')) : []];
    }

    /* Challan rows: the same item at the same rent is one row, with its barcodes listed. */
    public static function challanGroups($lines): array
    {
        $g = [];
        foreach ($lines as $l) {
            $k = $l->item_id . '|' . $l->rent;
            $g[$k] ??= ['name' => $l->name, 'hsn' => $l->hsn, 'qty' => 0, 'codes' => []];
            $g[$k]['qty']++;
            $g[$k]['codes'][] = (int) $l->code;
        }
        return array_values($g);
    }

    /* Rental invoice rows, category wise: the same category and the same amount is one row, so two laptops at one rent show as Qty 2. */
    public static function invoiceGroups($lines): array
    {
        $g = [];
        foreach ($lines as $l) {
            $cat = (string) ($l->cat ?? '');
            $k = $cat . '|' . $l->price;
            if (!isset($g[$k])) {
                $g[$k] = ['cat' => $cat !== '' ? $cat : $l->name, 'models' => [], 'cfgs' => [], 'price' => (float) $l->price, 'gst' => (float) $l->gst_percent, 'hsn' => $l->hsn,
                    'days' => $l->days, 'rate' => (float) $l->rent, 'qty' => 0, 'codes' => []];
            }
            $g[$k]['qty']++;
            if (!in_array($l->name, $g[$k]['models'], true)) {
                $g[$k]['models'][] = $l->name;
            }
            if ($l->config && !in_array($l->config, $g[$k]['cfgs'], true)) {
                $g[$k]['cfgs'][] = $l->config;
            }
            $g[$k]['codes'][] = (int) $l->code;
        }
        return array_values($g);
    }

    public static function company(): object
    {
        return DB::table('company_profile')->where('id', 1)->first();
    }
}
