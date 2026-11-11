<?php

namespace App\Support;

/* GST sums, amount in words and invoice numbers. Same rules as the prototype. No database in here. */
class Tax
{
    public static function r2(float $x): float
    {
        return round($x * 100 + ($x < 0 ? -1e-9 : 1e-9)) / 100;
    }

    /* 'none' (bill of supply), 'intra' (same state: CGST + SGST) or 'inter' (other state: IGST). */
    public static function mode(string $partyStateCode, string $type, string $companyStateCode): string
    {
        if ($type !== 'GST') {
            return 'none';
        }
        return $partyStateCode === $companyStateCode ? 'intra' : 'inter';
    }

    /* $lines: list of ['qty' =>, 'price' =>, 'gst' =>] */
    public static function totals(array $lines, string $mode): array
    {
        $taxable = $cg = $sg = $ig = 0.0;
        foreach ($lines as $l) {
            $t = self::r2($l['qty'] * $l['price']);
            $taxable += $t;
            if ($mode === 'intra') {
                $cg += self::r2($t * $l['gst'] / 200);
                $sg += self::r2($t * $l['gst'] / 200);
            } elseif ($mode === 'inter') {
                $ig += self::r2($t * $l['gst'] / 100);
            }
        }
        [$taxable, $cg, $sg, $ig] = [self::r2($taxable), self::r2($cg), self::r2($sg), self::r2($ig)];
        return ['taxable' => $taxable, 'cg' => $cg, 'sg' => $sg, 'ig' => $ig, 'tax' => self::r2($cg + $sg + $ig), 'total' => self::r2($taxable + $cg + $sg + $ig)];
    }

    public static function money(float|int|string|null $n): string
    {
        $s = number_format((float) $n, 2, '.', '');
        [$int, $dec] = explode('.', $s);
        $neg = $int[0] === '-';
        $int = ltrim($int, '-');
        if (strlen($int) > 3) {
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)) . ',' . substr($int, -3);
        }
        return ($neg ? '-' : '') . $int . '.' . $dec;
    }

    private static function two(int $x): string
    {
        $a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        return $x < 20 ? $a[$x] : $b[intdiv($x, 10)] . ($x % 10 ? ' ' . $a[$x % 10] : '');
    }

    private static function three(int $x): string
    {
        $h = intdiv($x, 100);
        $r = $x % 100;
        $s = '';
        if ($h) {
            $s .= self::two($h) . ' Hundred';
        }
        if ($r) {
            $s .= ($s ? ' ' : '') . self::two($r);
        }
        return $s;
    }

    private static function int2w(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        $cr = intdiv($n, 10000000);
        $lk = intdiv($n % 10000000, 100000);
        $th = intdiv($n % 100000, 1000);
        $rest = $n % 1000;
        if ($cr) {
            $parts[] = self::three($cr) . ' Crore';
        }
        if ($lk) {
            $parts[] = self::two($lk) . ' Lakh';
        }
        if ($th) {
            $parts[] = self::two($th) . ' Thousand';
        }
        if ($rest) {
            $parts[] = self::three($rest);
        }
        return implode(' ', $parts);
    }

    public static function words(float $n): string
    {
        $n = self::r2($n);
        $rs = (int) floor($n);
        $ps = (int) round(($n - $rs) * 100);
        return 'INR ' . self::int2w($rs) . ($ps ? ' and ' . self::int2w($ps) . ' Paise' : '') . ' Only';
    }

    /* Financial year label for a date, e.g. 2026-10-17 gives "26-27". */
    public static function fy(\DateTimeInterface $d): string
    {
        $y = (int) $d->format('Y');
        $m = (int) $d->format('n');
        $s = $m >= 4 ? $y : $y - 1;
        return str_pad((string) ($s % 100), 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) (($s + 1) % 100), 2, '0', STR_PAD_LEFT);
    }
}
