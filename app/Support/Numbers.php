<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * Document numbers (invoice, challan, return, credit note). The lowest number that is not in use is given out, so when a document is
 * deleted its number is used again by the next one (delete INV12, the next invoice is INV12 again).
 * Call it inside the database transaction that saves the document: the counter row is locked until the save finishes, so two people
 * saving at the same moment cannot get the same number.
 */
class Numbers
{
    /* $sources: list of [table, column]. $prefix: the fixed start of the number, for example "KPI/26-27/". */
    public static function lowestFree(string $lock, array $sources, string $prefix, int $first = 1): int
    {
        DB::table('counters')->insertOrIgnore(['name' => $lock, 'value' => 0]);
        DB::table('counters')->where('name', $lock)->lockForUpdate()->value('value');
        $used = [];
        $len = strlen($prefix);
        foreach ($sources as $s) {
            [$table, $col] = $s;
            $q = DB::table($table)->where($col, 'like', addcslashes($prefix, '%_\\') . '%');
            if (isset($s[2])) {
                $q->where($s[2][0], $s[2][1]);
            }
            foreach ($q->pluck($col) as $no) {
                $tail = substr((string) $no, $len);
                if ($tail !== '' && ctype_digit($tail)) {
                    $used[(int) $tail] = true;
                }
            }
        }
        $n = $first;
        while (isset($used[$n])) {
            $n++;
        }
        return $n;
    }

    /* Purchase serial: 1, 2, 3 ... the lowest number not in use (the database row id is not shown to anyone). */
    public static function purchase(): int
    {
        DB::table('counters')->insertOrIgnore(['name' => 'purchase-no', 'value' => 0]);
        DB::table('counters')->where('name', 'purchase-no')->lockForUpdate()->value('value');
        $used = array_flip(array_map('intval', DB::table('purchases')->whereNotNull('no')->pluck('no')->all()));
        $n = 1;
        while (isset($used[$n])) {
            $n++;
        }
        return $n;
    }

    private static function pad(int $n): string
    {
        return str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /* KPI/26-27/012: sales and rental invoices share one series. */
    public static function invoice(): string
    {
        $fy = Tax::fy(now());
        $p = Barcodes::prefix() . '/' . $fy . '/';
        return $p . self::pad(self::lowestFree('sale-' . $fy, [['sales', 'no'], ['rentals', 'no', ['kind', 'invoice']]], $p));
    }

    /* KPI/26-27/1: challans have their own series that starts again at 1 every financial year (April to March).
       Challans made before this change keep their old "Ch 2150" numbers. */
    public static function challan(): string
    {
        $fy = Tax::fy(now());
        $p = Barcodes::prefix() . '/' . $fy . '/';
        $n = self::lowestFree('challan-' . $fy, [['rentals', 'no', ['kind', 'challan']]], $p);
        while (DB::table('rentals')->where('no', $p . $n)->exists()) {   // never the same text as an invoice number
            $n++;
        }
        return $p . $n;
    }

    public static function returnNo(): string
    {
        $fy = Tax::fy(now());
        $p = Barcodes::prefix() . '/' . $fy . '/';
        return $p . self::pad(self::lowestFree('ret-' . $fy, [['returns', 'no']], $p));
    }

    public static function creditNote(): string
    {
        $fy = Tax::fy(now());
        $p = 'CN/' . $fy . '/';
        return $p . self::pad(self::lowestFree('cn-' . $fy, [['replacements', 'cn']], $p));
    }
}
