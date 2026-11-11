<?php

namespace App\Support;

/*
 * Rental maths with no database in it, so it can be tested on its own.
 *  - billLines: how many days each piece was out in a billing period and what to charge for them.
 *  - the parts of a device config (RAM, storage) for upgrades.
 */
class RentMath
{
    public static function dayNo(string $ymd): int
    {
        return intdiv((int) strtotime(substr($ymd, 0, 10) . ' 00:00:00 UTC'), 86400);
    }

    /*
     * $segments: every time a piece went out on rent, oldest first, as
     *   ['code' => int, 'item_id' => int, 'rate' => monthly rent, 'from' => 'Y-m-d...', 'returned' => 'Y-m-d...'|null, 'repl' => true when the piece came as a replacement]
     * Returns one row per piece that was out at least one day between $from and $to (both included):
     *   ['code', 'item_id', 'days', 'rate', 'price', 'P']  where P = days in the period and price = rate * months * days / P.
     * months is 1 for a normal one-month period and 2, 3... for a longer one (period / 30.44 days, at least 1).
     *
     * $full = false: a piece is charged only for the days it was out inside the period.
     * $full = true:  a piece that went out before or during the period is charged from the first day of the period
     *                (the customer and the company agreed on the billing dates). A piece that came back inside the period
     *                is still charged only up to its return day, and a replacement piece only from the day it arrived.
     * The pieces are listed in the order they first appear in $segments.
     */
    public static function billLines(array $segments, string $from, string $to, bool $full = false): array
    {
        $f = self::dayNo($from);
        $t = self::dayNo($to);
        if ($t < $f) {
            return [];
        }
        $P = $t - $f + 1;
        $months = max(1, (int) round($P / 30.4375));
        $m = [];
        foreach ($segments as $s) {
            $segStart = self::dayNo($s['from']);
            if ($segStart > $t) {
                continue;
            }
            $start = ($full && empty($s['repl'])) ? $f : max($segStart, $f);
            $end = !empty($s['returned']) ? self::dayNo($s['returned']) + 1 : $t + 1;
            $end = min($end, $t + 1);
            $days = $end - $start;
            if ($days <= 0) {
                continue;
            }
            $code = (int) $s['code'];
            if (!isset($m[$code])) {
                $m[$code] = ['code' => $code, 'item_id' => (int) $s['item_id'], 'days' => 0, 'rate' => (float) $s['rate']];
            }
            $m[$code]['days'] = min($P, $m[$code]['days'] + $days);
            $m[$code]['rate'] = (float) $s['rate'];
        }
        $out = [];
        foreach ($m as $x) {
            $x['price'] = Tax::r2($x['rate'] * $months * $x['days'] / $P);
            $x['P'] = $P;
            $out[] = $x;
        }
        return $out;
    }

    /* ---------- parts of a device config, for upgrades ---------- */

    public const PART_TYPES = ['RAM', 'Storage', 'Part'];

    public static function partLabel(string $p): string
    {
        return ['RAM' => 'RAM', 'Storage' => 'SSD', 'Part' => 'Part replacement', 'Battery' => 'Battery'][$p] ?? $p;
    }

    /*
     * Which part an item is (RAM, Storage, Battery) and its size, worked out from its name and category.
     * Returns null when it is none of these. val is in GB for RAM and storage (1 TB = 1024), null when the name has no size.
     */
    public static function itemPart(string $name, string $category): ?array
    {
        $hay = $name . ' ' . $category;
        $part = null;
        if (preg_match('/\bbatter(y|ies)\b/i', $hay)) {
            $part = 'Battery';
        } elseif (preg_match('/\bram\b|\bddr\s?\d/i', $hay)) {
            $part = 'RAM';
        } elseif (preg_match('/\bssd\b|\bhdd\b|\bnvme\b|\bsata\b|\bm\.?2\b|hard\s*(disk|drive)|\bstorage\b/i', $hay)) {
            $part = 'Storage';
        }
        if ($part === null) {
            return null;
        }
        $val = null;
        if ($part !== 'Battery' && preg_match('/(\d+(?:\.\d+)?)\s*(tb|gb)\b/i', $name, $m)) {
            $val = (float) $m[1] * (strtolower($m[2]) === 'tb' ? 1024 : 1);
            $val = (int) round($val);
        }
        return ['part' => $part, 'val' => $val];
    }

    /* "8 GB", "512 GB", "1 TB" for a part item; the item name for a battery. */
    public static function valLabel(string $part, ?int $val, string $name): string
    {
        if ($val === null) {
            return $name;
        }
        if ($part === 'RAM') {
            return $val . ' GB';
        }
        if ($part === 'Storage') {
            return $val >= 1024 ? rtrim(rtrim(number_format($val / 1024, 2, '.', ''), '0'), '.') . ' TB' : $val . ' GB';
        }
        return $name;
    }

    /* Splits a config such as i5/8th/8gb/512 and finds the RAM segment (the first one like 8gb). */
    private static function segs(string $cfg): array
    {
        $s = explode('/', $cfg);
        $ri = -1;
        foreach ($s as $i => $x) {
            if (preg_match('/^\s*\d+\s*gb\s*$/i', $x)) {
                $ri = $i;
                break;
            }
        }
        return [$s, $ri];
    }

    /* The value now in the config for this part (empty when the config does not show it). */
    public static function partOld(string $cfg, string $part): string
    {
        [$s, $ri] = self::segs($cfg);
        if ($part === 'RAM') {
            return $ri >= 0 ? trim($s[$ri]) : '';
        }
        if ($part === 'Storage') {
            return $ri >= 0 && isset($s[$ri + 1]) ? trim($s[$ri + 1]) : '';
        }
        return '';
    }

    /* The config after the part is changed to $val GB. The config is returned unchanged when it cannot be read. */
    public static function partNew(string $cfg, string $part, int $val): string
    {
        [$s, $ri] = self::segs($cfg);
        if ($ri < 0) {
            return $cfg;
        }
        if ($part === 'RAM') {
            $s[$ri] = $val . 'gb';
        } elseif ($part === 'Storage') {
            if (!isset($s[$ri + 1])) {
                return $cfg;
            }
            $o = trim($s[$ri + 1]);
            // keep what follows the number (256SSD becomes 512SSD, 256gb becomes 512gb)
            $s[$ri + 1] = preg_match('/^\d+/', $o) ? preg_replace('/^\d+/', (string) $val, $o, 1) : (string) $val;
        }
        return implode('/', $s);
    }

    /*
     * Applies the upgrades one after the other.
     * $ups: list of ['part' => 'RAM', 'val' => int|null]. Returns a list of ['part', 'old', 'cfg_before', 'cfg_after'].
     */
    public static function upChain(string $cfg, array $ups): array
    {
        $cur = $cfg;
        $out = [];
        foreach ($ups as $u) {
            $old = self::partOld($cur, $u['part']);
            $new = ($u['val'] ?? null) ? self::partNew($cur, $u['part'], (int) $u['val']) : $cur;
            $out[] = ['part' => $u['part'], 'old' => $old, 'cfg_before' => $cur, 'cfg_after' => $new];
            $cur = $new;
        }
        return $out;
    }
}
