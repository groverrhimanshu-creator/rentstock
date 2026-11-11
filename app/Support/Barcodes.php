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

    /* Gives out the next number. Safe when two people save at the same moment: the counter row is locked by the UPDATE. */
    public static function next(): int
    {
        DB::update("UPDATE counters SET value = LAST_INSERT_ID(value + 1) WHERE name = 'barcode'");
        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS id')->id;
    }

    /* Reserves $n numbers in one go and returns the first of them. */
    public static function reserve(int $n): int
    {
        DB::update("UPDATE counters SET value = LAST_INSERT_ID(value + ?) WHERE name = 'barcode'", [$n]);
        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS id')->id - $n + 1;
    }

    /* The number that will be given out next, without using it. */
    public static function peek(): int
    {
        return (int) DB::table('counters')->where('name', 'barcode')->value('value') + 1;
    }

    /* When a barcode from an old sheet is higher than our counter, move the counter up so we never repeat it. */
    public static function raise(int $code): void
    {
        DB::update("UPDATE counters SET value = GREATEST(value, ?) WHERE name = 'barcode'", [$code]);
    }
}
