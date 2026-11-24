<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * Barcode numbers. A piece's barcode is the company prefix plus a number, for example KPI600001.
 * Only the number is stored (devices.code). The prefix comes from the company profile.
 */
class Barcodes
{
    private static ?string $prefix = null;

    public static function prefix(): string
    {
        if (self::$prefix === null) {
            self::$prefix = (string) DB::table('company_profile')->where('id', 1)->value('prefix');
        }
        return self::$prefix;
    }

    public static function forgetPrefix(): void
    {
        self::$prefix = null;
    }

    public static function text(int $code): string
    {
        return self::prefix() . $code;
    }

    /* "KPI600001", "kpi 600001" or "600001" all give 600001. Anything else gives null. */
    public static function parse(string $raw): ?int
    {
        $s = strtoupper(preg_replace('/\s+/', '', $raw));
        $p = strtoupper(self::prefix());
        if ($p !== '' && strncmp($s, $p, strlen($p)) === 0) {
            $s = substr($s, strlen($p));
        }
        return preg_match('/^[0-9]{4,9}$/', $s) ? (int) $s : null;
    }

    /*
     * The $n lowest barcode numbers that are free, without using them.
     * The series starts at the lowest barcode in stock (or BARCODE_START when there is none) and runs upwards: a number freed by a deleted
     * piece is given out again before a new higher one, and after the highest piece the numbers simply carry on. A number that an old
     * document still points to (a past sale, rental, return, replacement or upgrade) is never given out again, so no history is mixed up.
     */
    public static function free(int $n): array
    {
        $lo = DB::table('devices')->min('code');
        $expected = $lo === null ? (int) config('rentstock.barcode_start', 600001) : (int) $lo;
        $taken = DB::table('devices')->select('code')
            ->union(DB::table('sale_lines')->whereNotNull('code')->select('code'))
            ->union(DB::table('rental_lines')->select('code'))
            ->union(DB::table('return_lines')->select('code'))
            ->union(DB::table('replacements')->select('old_code'))
            ->union(DB::table('replacements')->select('new_code'))
            ->union(DB::table('consumptions')->select('code'))
            ->orderBy('code')->pluck('code');
        $out = [];
        foreach ($taken as $c) {
            $c = (int) $c;
            if ($c < $expected) {
                continue;
            }
            while ($expected < $c && count($out) < $n) {
                $out[] = $expected++;
            }
            if (count($out) >= $n) {
                return $out;
            }
            $expected = $c + 1;
        }
        while (count($out) < $n) {
            $out[] = $expected++;
        }
        return $out;
    }

    /* Takes $n numbers for new pieces (the same list free() shows). Call it inside the transaction that saves the pieces: the counter row is locked until then. */
    public static function allocate(int $n): array
    {
        DB::table('counters')->insertOrIgnore(['name' => 'barcode', 'value' => 0]);
        DB::table('counters')->where('name', 'barcode')->lockForUpdate()->value('value');
        $codes = self::free($n);
        if ($codes) {
            self::raise(max($codes));
        }
        return $codes;
    }

    /* The number that will be given out next, without using it. */
    public static function peek(): int
    {
        return self::free(1)[0];
    }

    /* "KPI11, KPI15 to KPI18 (5)": a list of barcode numbers written short. */
    public static function compact(array $codes): string
    {
        sort($codes);
        $runs = [];
        $i = 0;
        $n = count($codes);
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && $codes[$j + 1] === $codes[$j] + 1) {
                $j++;
            }
            $runs[] = $j > $i ? self::text((int) $codes[$i]) . ' to ' . self::text((int) $codes[$j]) : self::text((int) $codes[$i]);
            $i = $j + 1;
        }
        return implode(', ', $runs) . ($n > 1 ? ' (' . $n . ')' : '');
    }

    /* When a barcode from an old sheet is higher than our counter, move the counter up so we never repeat it. */
    public static function raise(int $code): void
    {
        DB::update("UPDATE counters SET value = GREATEST(value, ?) WHERE name = 'barcode'", [$code]);
    }
}
