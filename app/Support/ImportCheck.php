<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/*
 * Reading and checking a stock sheet (CSV or Excel .xlsx). No database in here: the checks work on lists that the
 * caller hands in, so they can be tested on their own. Nothing is saved by this class.
 */
class ImportCheck
{
    public const MAX_ROWS = 20000;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const HEADERS = 'Quantity,Model/Product,Brand Tag,Config,Barcode,Material Center';

    /* ---------- reading ---------- */

    /* CSV text to a table (list of rows). Handles a BOM, Windows-1252 text and comma, semicolon or tab separators. */
    public static function tableFromText(string $text): array
    {
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $first = strtok($text, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) {
                $delim = $d;
            }
        }
        $rows = [];
        foreach (explode("\n", $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $r = array_map(fn ($v) => trim((string) $v), str_getcsv($line, $delim, '"', ''));
            if (count(array_filter($r, fn ($v) => $v !== '')) === 0) {
                continue;
            }
            $rows[] = $r;
            if (count($rows) > self::MAX_ROWS + 1) {
                throw new RuntimeException('The file has too many rows. Split it into files of ' . self::MAX_ROWS . ' rows or fewer.');
            }
        }
        return $rows;
    }

    /* Table back to CSV text, so an uploaded Excel file can be shown in the text box like pasted rows. */
    public static function textFromTable(array $table): string
    {
        $h = fopen('php://memory', 'w+');
        foreach ($table as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        rewind($h);
        $out = stream_get_contents($h);
        fclose($h);
        return rtrim((string) $out, "\n");
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split($m[0] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /* First sheet of an .xlsx file. Needs the PHP zip extension. */
    public static function tableFromXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is not enabled. Enable extension=zip in php.ini, or save the sheet as CSV.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This does not look like a valid .xlsx file.');
        }
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $x = simplexml_load_string($ss, SimpleXMLElement::class, LIBXML_NONET);
            if ($x) {
                foreach ($x->si as $si) {
                    if (isset($si->t)) {
                        $t = (string) $si->t;
                    } else {
                        $t = '';
                        foreach ($si->r as $r) {
                            $t .= (string) $r->t;
                        }
                    }
                    $shared[] = $t;
                }
            }
        }
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $w = simplexml_load_string($wb, SimpleXMLElement::class, LIBXML_NONET);
            $rl = simplexml_load_string($rels, SimpleXMLElement::class, LIBXML_NONET);
            if ($w && $rl && isset($w->sheets->sheet[0])) {
                $rid = (string) $w->sheets->sheet[0]->attributes('r', true)['id'];
                foreach ($rl->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = (string) $rel['Target'];
                        $sheetPath = strncmp($t, '/', 1) === 0 ? ltrim($t, '/') : 'xl/' . $t;
                    }
                }
            }
        }
        $stat = $zip->statName($sheetPath);
        if (!$stat) {
            throw new RuntimeException('Could not find the first sheet in the Excel file.');
        }
        if ($stat['size'] > 60 * 1024 * 1024) {
            throw new RuntimeException('The sheet is too large.');
        }
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        $sx = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_PARSEHUGE);
        if (!$sx) {
            throw new RuntimeException('The sheet could not be read.');
        }
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $r = [];
            foreach ($row->c as $c) {
                $i = self::colIndex((string) $c['r']);
                if ($i > 40) {
                    continue;
                }
                $t = (string) $c['t'];
                if ($t === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $v = isset($c->is->t) ? (string) $c->is->t : '';
                    if ($v === '' && isset($c->is->r)) {
                        foreach ($c->is->r as $rr) {
                            $v .= (string) $rr->t;
                        }
                    }
                } else {
                    $v = (string) $c->v;
                    if ($t === '' && is_numeric($v) && (float) $v == floor((float) $v) && abs((float) $v) < 1e15) {
                        $v = (string) (int) $v;
                    }
                }
                while (count($r) < $i) {
                    $r[] = '';
                }
                $r[$i] = trim($v);
            }
            if (count(array_filter($r, fn ($v) => $v !== '')) === 0) {
                continue;
            }
            $rows[] = $r;
            if (count($rows) > self::MAX_ROWS + 1) {
                throw new RuntimeException('The file has too many rows. Split it into files of ' . self::MAX_ROWS . ' rows or fewer.');
            }
        }
        return $rows;
    }

    /* ---------- turning the table into rows ---------- */

    private static function normHeader(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    /* Same rule the prototype uses to match a model name to the Item master. */
    public static function normName(string $s): string
    {
        return preg_replace('/[\s"\'\-_.]+/u', '', mb_strtolower($s));
    }

    /* The first row is the column names. Columns are found by name, so their order in the sheet does not matter. */
    public static function rows(array $table): array
    {
        if (count($table) < 2) {
            throw new RuntimeException('The file has no data rows.');
        }
        $aliases = [
            'qty' => ['quantity', 'qty'],
            'model' => ['modelproduct', 'model', 'product', 'item', 'modelname', 'productname'],
            'tag' => ['brandtag', 'tag'],
            'cfg' => ['config', 'configuration'],
            'code' => ['barcode', 'code', 'barcodeno'],
            'store' => ['materialcenter', 'store', 'location', 'storename'],
        ];
        $idx = [];
        foreach ($table[0] as $i => $h) {
            foreach ($aliases as $k => $list) {
                if (!isset($idx[$k]) && in_array(self::normHeader((string) $h), $list, true)) {
                    $idx[$k] = $i;
                }
            }
        }
        foreach (['model' => 'Model/Product', 'code' => 'Barcode'] as $k => $label) {
            if (!isset($idx[$k])) {
                throw new RuntimeException('The first row must be the column names. The column "' . $label . '" was not found. Columns needed: ' . self::HEADERS . '.');
            }
        }
        $rows = [];
        foreach ($table as $n => $r) {
            if ($n === 0) {
                continue;
            }
            $g = fn ($k) => isset($idx[$k]) ? trim((string) ($r[$idx[$k]] ?? '')) : '';
            $rows[] = ['line' => $n + 1, 'qty' => $g('qty'), 'model' => $g('model'), 'tag' => $g('tag'), 'cfg' => $g('cfg'), 'raw' => $g('code'), 'store' => $g('store')];
        }
        return $rows;
    }

    /* ---------- checking the rows ---------- */

    /* "KPI600001", "kpi 600001" or "600001" give 600001. Anything else gives null. */
    public static function parseCode(string $raw, string $prefix): ?int
    {
        $s = strtoupper(preg_replace('/\s+/', '', trim($raw)));
        $p = strtoupper($prefix);
        if ($p !== '' && strncmp($s, $p, strlen($p)) === 0) {
            $s = substr($s, strlen($p));
        }
        return preg_match('/^[0-9]{4,9}$/', $s) ? (int) $s : null;
    }

    /*
     * $items:    normalised item name => ['id' => .., 'name' => .., 'tracked' => bool]
     * $stores:   lower-case store name => store id
     * $existing: [code => true] for barcodes that are already in stock
     * Each returned row has the original fields plus code, item, store_id and err (a list of problems).
     * The checks and their wording follow the prototype.
     */
    public static function validate(array $rows, array $items, array $stores, array $existing, string $prefix, string $defaultStore = 'Main Store'): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $err = [];
            $r['code'] = self::parseCode($r['raw'], $prefix);
            $item = $items[self::normName($r['model'])] ?? null;
            $r['item'] = $item;
            $storeName = $r['store'] !== '' ? $r['store'] : $defaultStore;
            $r['store'] = $storeName;
            $r['store_id'] = $stores[mb_strtolower($storeName)] ?? null;

            $qty = $r['qty'];
            if (!is_numeric($qty) || (int) $qty != 1 || (float) $qty != 1.0) {
                $err[] = 'Quantity must be 1 (one row per barcoded item)';
            }
            if (!$item) {
                $err[] = 'Model not found in Item master';
            } elseif (!$item['tracked']) {
                $err[] = 'This item is counted by quantity, not by barcode';
            }
            if ($r['tag'] === '') {
                $err[] = 'Brand tag is missing';
            }
            if ($r['code'] === null) {
                $err[] = 'Barcode must look like ' . $prefix . '600001';
            } elseif (isset($existing[$r['code']])) {
                $err[] = 'Barcode already in stock';
            } elseif (isset($seen[$r['code']])) {
                $err[] = 'Barcode repeated in this file (line ' . $seen[$r['code']] . ')';
            }
            if ($r['code'] !== null && !isset($seen[$r['code']])) {
                $seen[$r['code']] = $r['line'];
            }
            if ($r['store_id'] === null) {
                $err[] = 'Store not found in Store master';
            }
            $r['err'] = $err;
            $out[] = $r;
        }
        return $out;
    }
}
