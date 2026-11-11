<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\ImportCheck;
use App\Support\ItemImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Masters > Item > Import items: the client's Item master sheet (CSV or Excel) goes in, missing categories and brands are made on the way. */
class ItemImportController extends Controller
{
    private const SAMPLE = [
        ['Product Name', 'Category', 'Brand', 'Unit', 'HSN Code', 'Rental Price', 'Sale Price', 'Parameterized', 'Generate Barcode', 'Maintain Stock'],
        ['Latitude 7490', 'Basic Laptop', 'Dell', 'Nos', '997315', '2000', '48000', 'Yes', 'Yes', 'Yes'],
        ['HDMI Cable', 'Cables', 'Generic', 'Nos', '8544', '0', '150', 'Yes', 'No', 'Yes'],
    ];

    /* "Download sample file": a CSV with the columns the import needs. */
    public function sample()
    {
        return response()->streamDownload(function () {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");   // BOM so Excel reads it as UTF-8
            foreach (self::SAMPLE as $row) {
                fputcsv($h, $row, ',', '"', '');
            }
            fclose($h);
        }, 'item-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show()
    {
        return view('items.import', $this->data('', null));
    }

    public function read(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:' . (ImportCheck::MAX_BYTES / 1024)]], [
            'file.required' => 'Choose a file first.', 'file.max' => 'The file is too big (10 MB at most). If it is an Excel file with many empty rows, delete them or save it as CSV.',
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        try {
            if ($ext === 'xlsx') {
                $table = ImportCheck::tableFromXlsx($file->getRealPath());
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $table = ImportCheck::tableFromText((string) file_get_contents($file->getRealPath()));
            } else {
                throw new RuntimeException($ext === 'xls' ? 'Old .xls files cannot be read. Save the sheet as .xlsx or CSV and choose it again.' : 'Choose a CSV or Excel (.xlsx) file.');
            }
        } catch (RuntimeException $e) {
            return view('items.import', $this->data('', null, $e->getMessage()));
        }
        return view('items.import', $this->data(ImportCheck::textFromTable($table), null));
    }

    public function check(Request $request)
    {
        $text = (string) $request->input('text', '');
        if (trim($text) === '') {
            return view('items.import', $this->data('', null, 'Add the file contents first.'));
        }
        try {
            $report = $this->report($text);
        } catch (RuntimeException $e) {
            return view('items.import', $this->data($text, null, $e->getMessage()));
        }
        return view('items.import', $this->data($text, $report));
    }

    /* The rows that need fixing, as a CSV with the reason. */
    public function errors(Request $request)
    {
        $text = (string) $request->input('text', '');
        try {
            $bad = array_values(array_filter($this->report($text), fn ($r) => $r['status'] === 'err'));
        } catch (RuntimeException $e) {
            return view('items.import', $this->data($text, null, $e->getMessage()));
        }
        return response()->streamDownload(function () use ($bad) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Line', 'Product Name', 'Category', 'Brand', 'Problem'], ',', '"', '');
            foreach ($bad as $r) {
                fputcsv($h, [$r['line'], $r['name'], $r['category'], $r['brand'], implode('; ', $r['err'])], ',', '"', '');
            }
            fclose($h);
        }, 'item-import-errors-' . date('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function commit(Request $request)
    {
        $text = (string) $request->input('text', '');
        try {
            // Checked again now, in case someone added the same names a moment ago.
            $report = $this->report($text);
        } catch (RuntimeException $e) {
            return view('items.import', $this->data($text, null, $e->getMessage()));
        }
        $good = array_values(array_filter($report, fn ($r) => $r['status'] === 'ok'));
        if (!$good) {
            return view('items.import', $this->data($text, $report, 'Nothing new to import.'));
        }

        [$added, $cats, $brands, $units] = DB::transaction(function () use ($good) {
            $maps = [];
            $made = ['categories' => 0, 'brands' => 0, 'units' => 0];
            foreach (['categories' => 'category', 'brands' => 'brand', 'units' => 'unit'] as $table => $field) {
                $maps[$table] = [];
                foreach (DB::table($table)->get(['id', 'name']) as $x) {
                    $maps[$table][ItemImport::key($x->name)] = $x->id;
                }
                foreach ($good as $r) {
                    $name = $r['v'][$field];
                    if ($name === '' || isset($maps[$table][ItemImport::key($name)])) {
                        continue;
                    }
                    $maps[$table][ItemImport::key($name)] = DB::table($table)->insertGetId(['name' => $name]);
                    $made[$table]++;
                }
            }
            $now = now();
            $rows = [];
            foreach ($good as $r) {
                $v = $r['v'];
                $rows[] = [
                    'name' => $v['name'],
                    'brand_id' => $v['brand'] !== '' ? $maps['brands'][ItemImport::key($v['brand'])] : null,
                    'category_id' => $v['category'] !== '' ? $maps['categories'][ItemImport::key($v['category'])] : null,
                    'unit_id' => $maps['units'][ItemImport::key($v['unit'])] ?? null,
                    'hsn' => $v['hsn'], 'gst_percent' => $v['gst'], 'rent_per_month' => $v['rent'], 'sale_price' => $v['sale'],
                    'tracked' => $v['tracked'] ? 1 : 0, 'maintain_stock' => $v['maintain'] ? 1 : 0, 'created_at' => $now,
                ];
            }
            $added = 0;
            foreach (array_chunk($rows, 200) as $chunk) {
                $added += DB::table('items')->insertOrIgnore($chunk);   // a name added a moment ago by someone else is skipped, not an error
            }
            return [$added, $made['categories'], $made['brands'], $made['units']];
        });
        Audit::log('add', 'item import', 'items', $added . ' items, ' . $cats . ' categories and ' . $brands . ' brands created');

        return redirect()->route('masters.index', 'item')->with('ok', $added . ' items imported' . ($cats || $brands ? ' (' . $cats . ' new categories, ' . $brands . ' new brands)' : ''));
    }

    private function report(string $text): array
    {
        $rows = ItemImport::rows(ImportCheck::tableFromText($text));
        $existing = [];
        foreach (DB::table('items')->pluck('name') as $n) {
            $existing[ItemImport::key($n)] = true;
        }
        $look = function (string $table) {
            $o = [];
            foreach (DB::table($table)->pluck('name') as $n) {
                $o[ItemImport::key($n)] = $n;
            }
            return $o;
        };
        return ItemImport::validate($rows, $existing, $look('categories'), $look('brands'), $look('units'));
    }

    private function data(string $text, ?array $report, ?string $error = null): array
    {
        $req = request();
        $sample = implode("\n", array_map(fn ($r) => implode(',', $r), self::SAMPLE));
        $fname = $req->hasFile('file') ? $req->file('file')->getClientOriginalName() : (string) $req->input('fname', '');
        $new = ['cat' => 0, 'brand' => 0];
        if ($report) {
            $c = $b = [];
            foreach ($report as $r) {
                if ($r['status'] === 'ok') {
                    if ($r['v']['new_cat']) {
                        $c[ItemImport::key($r['v']['category'])] = 1;
                    }
                    if ($r['v']['new_brand']) {
                        $b[ItemImport::key($r['v']['brand'])] = 1;
                    }
                }
            }
            $new = ['cat' => count($c), 'brand' => count($b)];
        }
        return ['per' => \App\Support\Pager::size($req->input('per')), 'page' => (int) $req->input('page', 1), 'text' => $text, 'report' => $report, 'error' => $error, 'headers' => ItemImport::HEADERS, 'sample' => $sample, 'new' => $new, 'fname' => mb_substr($fname, 0, 150)];
    }
}
