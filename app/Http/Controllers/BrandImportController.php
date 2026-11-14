<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\ImportCheck;
use App\Support\ItemImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Masters > Brand > Import brands: many brands at once from a CSV or Excel sheet. Existing brands are skipped. */
class BrandImportController extends Controller
{
    private const HEADERS = 'Brand Name, Popular Name, Description';
    private const ALIAS = ['name' => ['brandname', 'brand', 'name', 'brandtag', 'tag'], 'popular' => ['popularname', 'popular'], 'desc' => ['description', 'desc']];

    public function show()
    {
        return view('brands.import', ['report' => null, 'text' => '', 'error' => null, 'fname' => '', 'headers' => self::HEADERS]);
    }

    public function sample()
    {
        $csv = "\xEF\xBB\xBF" . "Brand Name,Popular Name,Description\r\nDell,Dell,Laptops and monitors\r\nHP,Hewlett-Packard,\r\nLenovo,,ThinkPad and IdeaPad\r\n";
        return response()->streamDownload(fn () => print($csv), 'brand-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function read(Request $request)
    {
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Choose a file first.', 'file.file' => 'The file could not be uploaded. Try again.']);
        $file = $request->file('file');
        if ($file->getSize() > ImportCheck::MAX_BYTES) {
            return view('brands.import', ['report' => null, 'text' => '', 'error' => 'The file is too big (10 MB at most).', 'fname' => '', 'headers' => self::HEADERS]);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        try {
            if ($ext === 'xlsx') {
                $table = ImportCheck::tableFromXlsx($file->getRealPath());
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $table = ImportCheck::tableFromText((string) file_get_contents($file->getRealPath()));
            } else {
                throw new RuntimeException($ext === 'xls' ? 'Old .xls files cannot be read. Save the sheet as .xlsx or CSV.' : 'Choose a CSV or Excel (.xlsx) file.');
            }
            $text = ImportCheck::textFromTable($table);
            $report = $this->analyse($table);
        } catch (RuntimeException $e) {
            return view('brands.import', ['report' => null, 'text' => '', 'error' => $e->getMessage(), 'fname' => $file->getClientOriginalName(), 'headers' => self::HEADERS]);
        }
        return view('brands.import', ['report' => $report, 'text' => $text, 'error' => null, 'fname' => mb_substr($file->getClientOriginalName(), 0, 150), 'headers' => self::HEADERS]);
    }

    /* Rows of {line, name, popular, desc, status ok|skip|err, why}. */
    private function analyse(array $table): array
    {
        if (count($table) < 2) {
            throw new RuntimeException('The file has no data rows.');
        }
        $idx = [];
        foreach ($table[0] as $i => $h) {
            $n = preg_replace('/[^a-z0-9]/', '', strtolower((string) $h));
            foreach (self::ALIAS as $k => $list) {
                if (!isset($idx[$k]) && in_array($n, $list, true)) {
                    $idx[$k] = $i;
                }
            }
        }
        if (!isset($idx['name'])) {
            throw new RuntimeException('The first row must be the column names. The column "Brand Name" was not found. Columns: ' . self::HEADERS . '.');
        }
        $existing = [];
        foreach (DB::table('brands')->pluck('name') as $n) {
            $existing[ItemImport::key((string) $n)] = true;
        }
        $seen = [];
        $out = [];
        foreach (array_slice($table, 1, 20000, true) as $n => $r) {
            $g = fn ($k) => isset($idx[$k]) ? trim((string) ($r[$idx[$k]] ?? '')) : '';
            $name = trim(preg_replace('/\s+/u', ' ', $g('name')));
            $pop = trim(preg_replace('/\s+/u', ' ', $g('popular')));
            $desc = $g('desc');
            if ($name === '' && $pop === '' && $desc === '') {
                continue;   // blank line
            }
            $status = 'ok';
            $why = '';
            if ($name === '') {
                $status = 'err';
                $why = 'Brand Name is empty';
            } elseif (mb_strlen($name) > 100 || mb_strlen($pop) > 100 || mb_strlen($desc) > 255) {
                $status = 'err';
                $why = 'Too long (name and popular name 100 characters, description 255)';
            } else {
                $k = ItemImport::key($name);
                if (isset($existing[$k])) {
                    $status = 'skip';
                    $why = 'Already in the Brand master';
                } elseif (isset($seen[$k])) {
                    $status = 'skip';
                    $why = 'Same name as line ' . $seen[$k] . ' of this file';
                } else {
                    $seen[$k] = $n + 1;
                }
            }
            $out[] = ['line' => $n + 1, 'name' => $name, 'popular' => $pop, 'desc' => $desc, 'status' => $status, 'why' => $why];
        }
        if (!$out) {
            throw new RuntimeException('The file has no brands in it.');
        }
        return $out;
    }

    public function commit(Request $request)
    {
        try {
            $report = $this->analyse(ImportCheck::tableFromText((string) $request->input('text', '')));
        } catch (RuntimeException $e) {
            return redirect()->route('brands.import')->with('bad', $e->getMessage());
        }
        $add = array_values(array_filter($report, fn ($r) => $r['status'] === 'ok'));
        if (!$add) {
            return redirect()->route('brands.import')->with('bad', 'There is nothing to import.');
        }
        DB::transaction(function () use ($add) {
            foreach (array_chunk($add, 500) as $chunk) {
                DB::table('brands')->insertOrIgnore(array_map(fn ($r) => ['name' => $r['name'], 'popular_name' => $r['popular'], 'description' => $r['desc']], $chunk));
            }
        });
        Audit::log('add', 'brands import', 'brand', count($add) . ' brands imported');
        return redirect()->route('masters.index', 'brand')->with('ok', count($add) . ' brands imported.');
    }
}
