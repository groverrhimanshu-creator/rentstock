<?php

namespace App\Support;

/*
 * Barcode labels as a PDF whose page is exactly one row of stickers (for example 100 x 25 mm for 2 stickers of 50 x 25 mm).
 * No browser headers or footers and no scaling can interfere, so it prints the same on every printer: choose "Actual size" / 100%.
 * Pure PHP, no library. Text uses the built-in Courier fonts, which have a fixed width, so centring is exact.
 */
class LabelPdf
{
    private const PT = 72 / 25.4;

    private static function esc(string $s): string
    {
        $s = preg_replace('/[^\x20-\x7E]/', '?', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private static function n(float $x): string
    {
        return rtrim(rtrim(number_format($x, 3, '.', ''), '0'), '.') ?: '0';
    }

    /* $labels: list of ['text' =>, 'tag' =>, 'config' =>, 'model' =>]. $o: cols, w, h, gap, dx, dy, z (percent), title. */
    public static function make(array $labels, array $o): string
    {
        $cols = max(1, (int) $o['cols']);
        $w = (float) $o['w'];
        $h = (float) $o['h'];
        $gap = (float) $o['gap'];
        $dx = (float) $o['dx'];
        $dy = (float) $o['dy'];
        $s = min($w / 50, $h / 25) * ((float) $o['z'] / 100);
        $pw = $cols * $w + ($cols - 1) * $gap;
        $pages = array_chunk($labels, $cols);
        $objs = [];
        $kids = [];
        foreach ($pages as $row) {
            $c = '';
            foreach ($row as $i => $l) {
                $c .= self::label($l, $dx + $i * ($w + $gap), $dy, $w, $h, $s, (string) ($o['title'] ?? ''));
            }
            $objs[] = ['stream' => $c];
        }
        // object numbers: 1 catalog, 2 pages, 3 font bold, 4 font regular, then (page, content) pairs
        $out = "%PDF-1.4\n";
        $off = [];
        $put = function (int $no, string $body) use (&$out, &$off) {
            $off[$no] = strlen($out);
            $out .= $no . " 0 obj\n" . $body . "\nendobj\n";
        };
        $no = 5;
        foreach ($objs as $k => $ob) {
            $kids[] = $no . ' 0 R';
            $put($no, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::n($pw * self::PT) . ' ' . self::n($h * self::PT) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($no + 1) . ' 0 R >>');
            $put($no + 1, '<< /Length ' . strlen($ob['stream']) . " >>\nstream\n" . $ob['stream'] . "\nendstream");
            $no += 2;
        }
        $put(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $put(2, '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>');
        $put(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>');
        $put(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>');
        $xref = strlen($out);
        $out .= "xref\n0 " . $no . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $no; $i++) {
            $out .= sprintf("%010d 00000 n \n", $off[$i]);
        }
        return $out . 'trailer << /Size ' . $no . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF";
    }

    /* Centred text: y is the distance of the text top from the top of the label (mm). */
    private static function text(string $t, string $font, float $pt, float $x0, float $w, float $y, float $h): string
    {
        $t = self::esc($t);
        $cw = 0.6 * $pt / self::PT;   // width of one Courier character in mm
        $max = max(1, (int) floor(($w - 2) / $cw));
        if (strlen($t) > $max) {
            $t = substr($t, 0, $max);
        }
        $tw = strlen($t) * $cw;
        $x = $x0 + ($w - $tw) / 2;
        $base = $h - ($y + $pt / self::PT * 0.8);
        return 'BT /' . $font . ' ' . self::n($pt) . ' Tf ' . self::n($x * self::PT) . ' ' . self::n($base * self::PT) . ' Td (' . $t . ") Tj ET\n";
    }

    private static function label(array $l, float $x0, float $y0, float $w, float $h, float $s, string $title): string
    {
        $c = '0 g ';
        $y = $y0 + 0.8 * $s;
        if ($title !== '') {
            $c .= self::text($title, 'F1', 7 * $s, $x0, $w, $y, $h);
            $y += 3 * $s;
        }
        // bars
        $bits = Code128::bits($l['text']);
        $quiet = 10;
        $mod = ($w - 4) / (strlen($bits) + 2 * $quiet);
        $bh = 7.5 * $s;
        $bx = $x0 + 2 + $quiet * $mod;
        $by = $h - ($y + $bh);
        $i = 0;
        $len = strlen($bits);
        while ($i < $len) {
            if ($bits[$i] === '1') {
                $j = $i;
                while ($j < $len && $bits[$j] === '1') {
                    $j++;
                }
                $c .= self::n(($bx + $i * $mod) * self::PT) . ' ' . self::n($by * self::PT) . ' ' . self::n(($j - $i) * $mod * self::PT) . ' ' . self::n($bh * self::PT) . " re f\n";
                $i = $j;
            } else {
                $i++;
            }
        }
        $y += $bh + 0.6 * $s;
        $c .= self::text($l['text'], 'F1', 10 * $s, $x0, $w, $y, $h);
        $y += 3.9 * $s;
        if ($l['tag'] !== '') {
            $c .= self::text('Sr.No : ' . $l['tag'], 'F2', 7.5 * $s, $x0, $w, $y, $h);
            $y += 3 * $s;
        }
        if ($l['config'] !== '') {
            $c .= self::text($l['config'], 'F2', 7 * $s, $x0, $w, $y, $h);
            $y += 2.8 * $s;
        }
        $c .= self::text($l['model'], 'F1', 7.5 * $s, $x0, $w, $y, $h);
        return $c;
    }
}
