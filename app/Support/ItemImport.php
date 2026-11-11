<?php

namespace App\Support;

use RuntimeException;

/*
 * Reads the Item master sheet (CSV or Excel) and checks it. No database work here: the controller hands in
 * what already exists (item names, categories, brands, units) and writes the result.
 *
 * Columns (found by name, any order): Product Name, Category, Brand, Unit, HSN Code (or SAC Code), Rental Price,
 * Sale Price, CGST, SGST, IGST, Parameterized, Generate Barcode, Maintain Stock. The other columns of the
 * client's template (MRP, Purchase Price, Discount ...) are read and ignored.
 *
 * Kind of item: "Generate Barcode" = Yes makes it a barcoded item (one barcode per piece). Otherwise it is
 * counted by quantity. When both "Parameterized" and "Generate Barcode" say Yes, the barcode wins.
 * "Maintain Stock": Yes/No for quantity items. A barcoded item is always kept in stock.
 */
class ItemImport
{
    public const HEADERS = 'Product Name, Category, Brand, Unit, HSN Code, Rental Price, Sale Price, Parameterized, Generate Barcode, Maintain Stock';
    public const DEFAULT_HSN = '8471';
    public const DEFAULT_GST = 18.0;

    private const ALIASES = [
        'name' => ['productname', 'name', 'itemname', 'item', 'product', 'modelproduct', 'model'],
        'category' => ['category'],
        'brand' => ['brand'],
        'unit' => ['unit'],
        'hsn' => ['hsncode', 'hsn'],
        'sac' => ['saccode', 'sac'],
        'rent' => ['rentalprice', 'rentpermonth', 'rent'],
        'sale' => ['saleprice', 'sellingprice'],
        'cgst' => ['cgst'],
        'sgst' => ['sgst'],
        'igst' => ['igst'],
        'param' => ['parameterized', 'parameterised'],
        'barcode' => ['generatebarcode', 'barcode'],
        'stock' => ['maintainstock'],
    ];

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    /* Lower-case key used to match names the same way the database does (case and extra spaces ignored). */
    public static function key(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    /* The table (first row = column names) to a list of plain rows. */
    public static function rows(array $table): array
    {
        if (count($table) < 2) {
            throw new RuntimeException('The file has no data rows.');
        }
        $idx = [];
        foreach ($table[0] as $i => $h) {
            foreach (self::ALIASES as $k => $list) {
                if (!isset($idx[$k]) && in_array(self::norm((string) $h), $list, true)) {
                    $idx[$k] = $i;
                }
            }
        }
        if (!isset($idx['name'])) {
            throw new RuntimeException('The first row must be the column names. The column "Product Name" was not found. Columns: ' . self::HEADERS . '.');
        }
        $out = [];
        foreach ($table as $n => $r) {
            if ($n === 0) {
                continue;
            }
            $g = fn ($k) => isset($idx[$k]) ? trim((string) ($r[$idx[$k]] ?? '')) : null;
            $out[] = [
                'line' => $n + 1,
                'name' => (string) $g('name'),
                'category' => (string) $g('category'), 'brand' => (string) $g('brand'), 'unit' => (string) $g('unit'),
                'hsn' => (string) ($g('hsn') !== null && $g('hsn') !== '' ? $g('hsn') : (string) $g('sac')),
                'rent' => (string) $g('rent'), 'sale' => (string) $g('sale'),
                'cgst' => (string) $g('cgst'), 'sgst' => (string) $g('sgst'), 'igst' => (string) $g('igst'),
                'param' => $g('param'), 'barcode' => $g('barcode'), 'stock' => $g('stock'),
            ];
        }
        return $out;
    }

    /* "Yes"/"No" (also Y, N, 1, 0, true, false). Returns true, false, or null when blank. Throws on anything else. */
    private static function yn(?string $v, string $label): ?bool
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        $x = strtolower(trim($v));
        if (in_array($x, ['yes', 'y', '1', 'true'], true)) {
            return true;
        }
        if (in_array($x, ['no', 'n', '0', 'false'], true)) {
            return false;
        }
        throw new RuntimeException($label . ' must be Yes or No');
    }

    private static function money(string $v, string $label): float
    {
        if ($v === '') {
            return 0.0;
        }
        $v = str_replace(',', '', $v);
        if (!is_numeric($v) || (float) $v < 0 || (float) $v > 99999999) {
            throw new RuntimeException($label . ' must be a number, 0 or more');
        }
        return round((float) $v, 2);
    }

    /*
     * $existing: item key => true (names already in the Item master)
     * $cats, $brands, $units: key => name already in the master
     * Each result row has 'status' = ok (will be added) | skip (already there) | err (needs fixing), 'err' (list), 'notes' (list) and the cleaned values in 'v'.
     */
    public static function validate(array $rows, array $existing, array $cats, array $brands, array $units): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $err = [];
            $notes = [];
            $name = trim(preg_replace('/\s+/u', ' ', $r['name']));
            $v = ['name' => $name];

            if ($name === '') {
                $err[] = 'Product Name is empty';
            } elseif (mb_strlen($name) > 150) {
                $err[] = 'Product Name is longer than 150 characters';
            }

            foreach (['category' => ['Category', 100], 'brand' => ['Brand', 100], 'unit' => ['Unit', 50]] as $k => [$label, $max]) {
                $val = trim(preg_replace('/\s+/u', ' ', $r[$k]));
                if ($k === 'unit' && $val === '') {
                    $val = 'Nos';
                }
                if (mb_strlen($val) > $max) {
                    $err[] = $label . ' is longer than ' . $max . ' characters';
                }
                $v[$k] = $val;
            }

            $hsn = preg_replace('/\.0+$/', '', $r['hsn']);   // Excel may hand 997315 over as 997315.0
            if ($hsn === '') {
                $hsn = self::DEFAULT_HSN;
                $notes[] = 'HSN empty, ' . self::DEFAULT_HSN . ' used';
            } elseif (!preg_match('/^[0-9]{1,10}$/', $hsn)) {
                $err[] = 'HSN Code must be digits only';
            }
            $v['hsn'] = $hsn;

            try {
                $v['rent'] = self::money($r['rent'], 'Rental Price');
                $v['sale'] = self::money($r['sale'], 'Sale Price');
            } catch (RuntimeException $e) {
                $err[] = $e->getMessage();
                $v['rent'] = $v['sale'] = 0.0;
            }

            // GST: CGST + SGST, or IGST when the sheet gives it. Empty means 18.
            $gst = self::DEFAULT_GST;
            $c = str_replace('%', '', $r['cgst']);
            $s = str_replace('%', '', $r['sgst']);
            $i = str_replace('%', '', $r['igst']);
            if ($i !== '' || $c !== '' || $s !== '') {
                if (($i !== '' && !is_numeric($i)) || ($c !== '' && !is_numeric($c)) || ($s !== '' && !is_numeric($s))) {
                    $err[] = 'CGST, SGST and IGST must be numbers';
                } else {
                    $gst = $i !== '' && (float) $i > 0 ? (float) $i : ((float) $c + (float) $s);
                    if ($gst <= 0 || $gst > 28) {
                        $err[] = 'GST must be more than 0 and at most 28';
                    }
                }
            }
            $v['gst'] = $gst;

            try {
                $param = self::yn($r['param'], 'Parameterized');
                $bar = self::yn($r['barcode'], 'Generate Barcode');
                $stock = self::yn($r['stock'], 'Maintain Stock');
            } catch (RuntimeException $e) {
                $err[] = $e->getMessage();
                $param = $bar = $stock = null;
            }
            // Barcoded unless the sheet says otherwise.
            if ($bar !== null) {
                $tracked = $bar;
            } elseif ($param !== null) {
                $tracked = !$param;
            } else {
                $tracked = true;
            }
            if ($bar === true && $param === true) {
                $notes[] = 'Parameterized and Generate Barcode are both Yes: made a barcoded item';
            }
            $maintain = $stock ?? true;
            if ($tracked && !$maintain) {
                $notes[] = 'Barcoded items are always kept in stock';
                $maintain = true;
            }
            $v['tracked'] = $tracked;
            $v['maintain'] = $maintain;

            $status = 'ok';
            if (!$err && $name !== '') {
                $k = self::key($name);
                if (isset($seen[$k])) {
                    $status = 'skip';
                    $notes[] = 'Same name as line ' . $seen[$k] . ' of this file, skipped (the first one is kept)';
                } else {
                    $seen[$k] = $r['line'];
                    if (isset($existing[$k])) {
                        $status = 'skip';
                        $notes[] = 'Already in the Item master, skipped';
                    }
                }
            }
            if ($err) {
                $status = 'err';
            }
            $v['new_cat'] = $v['category'] !== '' && !isset($cats[self::key($v['category'])]);
            $v['new_brand'] = $v['brand'] !== '' && !isset($brands[self::key($v['brand'])]);
            $v['new_unit'] = !isset($units[self::key($v['unit'])]);
            $out[] = ['line' => $r['line'], 'name' => $name, 'category' => $v['category'], 'brand' => $v['brand'], 'status' => $status, 'err' => $err, 'notes' => $notes, 'v' => $v];
        }
        return $out;
    }
}
