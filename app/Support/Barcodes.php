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
     * The next $n barcode numbers, without using them: they carry on after the LAST (highest) barcode that exists.
     * Gaps left by deleted pieces are not filled. "Exists" means a piece in stock or a number an old document (sale, rental, return,
     * replacement, upgrade) still points to, so no number is ever given out twice. With nothing yet, the series starts at BARCODE_START
     * (.env) or 600001. A BARCODE_START higher than the last number moves the series up to it (for example 900001).
     */
    public static function free(int $n): array
    {
        $last = max(
            (int) DB::table('devices')->max('code'),
            (int) DB::table('sale_lines')->max('code'),
            (int) DB::table('rental_lines')->max('code'),
            (int) DB::table('return_lines')->max('code'),
            (int) DB::table('replacements')->max('old_code'),
            (int) DB::table('replacements')->max('new_code'),
            (int) DB::table('consumptions')->max('code')
        );
        $floor = (int) config('rentstock.barcode_start');
        $start = max($last + 1, $floor > 0 ? $floor : 600001);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $start + $i;
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
