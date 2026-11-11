<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Barcodes;
use App\Support\Code128;
use App\Support\ConfigLog;
use App\Support\ImportCheck;
use App\Support\Stock;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Inventory, item details, stock entries and the stock import. */
class StockController extends Controller
{
    private function listSettings(Request $request): array
    {
        return [trim((string) $request->query('q', '')), \App\Support\Pager::size($request->query('n'))];
    }

    public function inventory(Request $request)
    {
        [$q, $n] = $this->listSettings($request);
        $status = (string) $request->query('status', '');
        if (!isset(Stock::STATUS[$status])) {
            $status = '';
        }

        $query = DB::table('devices as d')
            ->join('items as i', 'i.id', '=', 'd.item_id')
            ->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->select('d.code', 'd.brand_tag', 'd.config', 'd.status', 'i.name as model', 'c.name as customer');
        if ($status !== '') {
            $query->where('d.status', $status);
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('concat(?, d.code) like ?', [Barcodes::prefix(), $like])
                    ->orWhere('i.name', 'like', $like)->orWhere('d.brand_tag', 'like', $like)
                    ->orWhere('d.config', 'like', $like)->orWhere('c.name', 'like', $like);
            });
        }
        $rows = $query->orderBy('d.code')->paginate($n)->withQueryString();

        $qtyRows = DB::table('item_qty as q')
            ->join('items as i', 'i.id', '=', 'q.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.name', 'c.name as category', 'q.qty')->orderBy('i.name')->get();

        return view('stock.inventory', compact('rows', 'q', 'n', 'status', 'qtyRows'));
    }

    public function device(int $code)
    {
        $d = DB::table('devices as d')
            ->join('items as i', 'i.id', '=', 'd.item_id')
            ->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->where('d.code', $code)
            ->select('d.*', 'i.name as model', 'c.name as customer')->first();
        if (!$d) {
            return view('stock.device', ['d' => null]);
        }
        $text = Barcodes::text($code);
        $history = DB::table('device_history')->where('code', $code)->orderByDesc('at')->orderByDesc('id')->get();
        return view('stock.device', ['blocked' => Stock::deviceBlocker($code), 'd' => $d, 'text' => $text, 'history' => $history, 'cfglog' => ConfigLog::for($code), 'svg' => Code128::svgScreen($text, 50, 1.6)]);
    }

    public function deviceEdit(int $code)
    {
        $d = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.code', $code)->first(['d.*', 'i.name as model']);
        abort_unless($d, 404);
        return view('stock.device-edit', ['d' => $d, 'text' => Barcodes::text($code), 'stores' => DB::table('stores')->orderBy('id')->get(['id', 'name'])]);
    }

    public function deviceUpdate(Request $request, int $code)
    {
        $d = DB::table('devices')->where('code', $code)->first();
        abort_unless($d, 404);
        $tag = trim((string) $request->input('brand_tag', ''));
        $config = trim((string) $request->input('config', ''));
        $storeId = (int) $request->input('store_id');
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        if ($tag === '') {
            return $fail('Please fill in Brand tag.');
        }
        if (mb_strlen($tag) > 60 || mb_strlen($config) > 255) {
            return $fail('Brand tag can have 60 characters and Config 255 at most.');
        }
        if ($storeId && !DB::table('stores')->where('id', $storeId)->exists()) {
            return $fail('Please choose a store.');
        }
        $notes = [];
        if ($tag !== $d->brand_tag) {
            $notes[] = 'brand tag ' . $d->brand_tag . ' to ' . $tag;
        }
        if ($config !== $d->config) {
            $notes[] = 'config changed';
        }
        if ($storeId && (int) $d->store_id !== $storeId) {
            $notes[] = 'store changed';
        }
        DB::transaction(function () use ($code, $tag, $config, $storeId, $d, $notes, $request) {
            DB::table('devices')->where('code', $code)->update(['brand_tag' => $tag, 'config' => $config, 'store_id' => $storeId ?: $d->store_id]);
            if ($config !== $d->config) {
                ConfigLog::add($code, 'edit', $d->config, $config, 'Config edited on the item', $request->user()->id);
            }
            if ($notes) {
                Stock::addHistory($code, 'Details edited (' . implode(', ', $notes) . ')', $request->user()->id);
                Audit::log('edit', 'item piece', $code, Barcodes::text($code) . ': ' . implode('; ', $notes));
            }
        });
        if ($request->boolean('print')) {
            return redirect()->route('barcodes.label', ['code' => $code, 'saved' => 1, 'back' => route('stock.device', $code)]);
        }
        return redirect()->route('stock.device', $code)->with('ok', 'Item updated');
    }

    public function deviceDestroy(Request $request, int $code)
    {
        try {
            Stock::deleteDevice($code, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('stock.device', $code)->with('bad', $e->getMessage());
        }
        return redirect()->route('stock.inventory')->with('ok', Barcodes::text($code) . ' deleted');
    }

    public function entryEdit(int $id)
    {
        $e = DB::table('stock_entries')->where('id', $id)->first();
        abort_unless($e, 404);
        return view('stock.entry-edit', ['e' => $e, 'stores' => DB::table('stores')->orderBy('id')->get(['id', 'name'])]);
    }

    public function entryUpdate(Request $request, int $id)
    {
        $e = DB::table('stock_entries')->where('id', $id)->first();
        abort_unless($e, 404);
        $storeId = (int) $request->input('store_id');
        if (!DB::table('stores')->where('id', $storeId)->exists()) {
            return back()->withInput()->withErrors(['form' => 'Please choose a store.']);
        }
        DB::transaction(function () use ($id, $storeId, $e) {
            DB::table('stock_entries')->where('id', $id)->update(['store_id' => $storeId]);
            if ((int) $e->store_id !== $storeId) {
                // Items of this entry that are still on the shelf move with it.
                DB::table('devices')->where('stock_entry_id', $id)->where('status', 'in_stock')->update(['store_id' => $storeId]);
                Audit::log('edit', 'stock entry', $id, 'store changed');
            }
        });
        return redirect()->route('stock.entries')->with('ok', 'Stock entry updated');
    }

    public function entryDestroy(int $id)
    {
        try {
            Stock::deleteEntry($id);
        } catch (RuntimeException $e) {
            return redirect()->route('stock.entries')->with('bad', $e->getMessage());
        }
        return redirect()->route('stock.entries')->with('ok', 'Stock entry ' . $id . ' deleted');
    }

    public function entries(Request $request)
    {
        [$q, $n] = $this->listSettings($request);
        $query = DB::table('stock_entries as e')->leftJoin('stores as s', 's.id', '=', 'e.store_id')
            ->select('e.id', 'e.created_at', 's.name as store', 'e.piece_count');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('cast(e.id as char) like ?', [$like])->orWhere('s.name', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('e.id')->paginate($n)->withQueryString();
        return view('stock.entries', compact('rows', 'q', 'n'));
    }

    /* ---------- import ---------- */

    public function importShow()
    {
        return view('stock.import', $this->importData('', null));
    }

    /* "Choose file": reads a CSV or Excel file and puts its rows in the text box, like the prototype. */
    public function importRead(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:' . (ImportCheck::MAX_BYTES / 1024)]], [
            'file.required' => 'Choose a file first.', 'file.max' => 'The file is too big (10 MB at most).',
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        try {
            if ($ext === 'xlsx') {
                $table = ImportCheck::tableFromXlsx($file->getRealPath());
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $table = ImportCheck::tableFromText((string) file_get_contents($file->getRealPath()));
            } else {
                throw new RuntimeException($ext === 'xls'
                    ? 'Old .xls files cannot be read. Save the sheet as .xlsx or CSV and choose it again.'
                    : 'Choose a CSV or Excel (.xlsx) file.');
            }
        } catch (RuntimeException $e) {
            return view('stock.import', $this->importData('', null, $e->getMessage()));
        }
        return view('stock.import', $this->importData(ImportCheck::textFromTable($table), null));
    }

    public function importCheck(Request $request)
    {
        $text = (string) $request->input('text', '');
        if (trim($text) === '') {
            return view('stock.import', $this->importData('', null, 'Add the file contents first.'));
        }
        try {
            $report = $this->report($text);
        } catch (RuntimeException $e) {
            return view('stock.import', $this->importData($text, null, $e->getMessage()));
        }
        return view('stock.import', $this->importData($text, $report));
    }

    /* Downloads the rows that have a problem, as a CSV in the same columns as the stock sheet plus a "Problem" column. */
    public function importErrors(Request $request)
    {
        $text = (string) $request->input('text', '');
        try {
            $bad = array_values(array_filter($this->report($text), fn ($r) => $r['err']));
        } catch (RuntimeException $e) {
            return view('stock.import', $this->importData($text, null, $e->getMessage()));
        }
        if (!$bad) {
            return view('stock.import', $this->importData($text, $this->report($text), 'There are no problem rows to download.'));
        }
        return response()->streamDownload(function () use ($bad) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");   // so Excel reads it as UTF-8
            fputcsv($h, ['Line', 'Quantity', 'Model/Product', 'Brand Tag', 'Config', 'Barcode', 'Material Center', 'Problem'], ',', '"', '');
            foreach ($bad as $r) {
                fputcsv($h, [$r['line'], $r['qty'], $r['model'], $r['tag'], $r['cfg'], $r['raw'], $r['store'], implode('; ', $r['err'])], ',', '"', '');
            }
            fclose($h);
        }, 'import-errors-' . date('Y-m-d-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCommit(Request $request)
    {
        $text = (string) $request->input('text', '');
        try {
            // The rows are checked again now, in case someone added the same barcodes since "Check file".
            $good = array_values(array_filter($this->report($text), fn ($r) => !$r['err']));
        } catch (RuntimeException $e) {
            return view('stock.import', $this->importData($text, null, $e->getMessage()));
        }
        if (!$good) {
            return view('stock.import', $this->importData($text, $this->report($text), 'Nothing to import.'));
        }
        try {
            [, $count] = Stock::importRows($good, $request->user()->id);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return view('stock.import', $this->importData($text, $this->report($text), 'Some of these barcodes were added by someone else a moment ago. The check has been run again.'));
            }
            throw $e;
        }
        return redirect()->route('stock.entries')->with('ok', $count . ' items added to stock');
    }

    /* The sample sheet: the column names and up to 3 example rows made from real items in the Item master. */
    private function sampleLines(): array
    {
        $prefix = Barcodes::prefix();
        $sample = [ImportCheck::HEADERS];
        $next = Barcodes::peek();
        $items = DB::table('items')->where('tracked', 1)->orderBy('id')->limit(3)->get(['name']);
        foreach ($items as $i => $it) {
            $sample[] = '1,' . $it->name . ',' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)) . ',' . $it->name . ',' . $prefix . ($next + $i) . ',Main Store';
        }
        if ($items->isEmpty()) {
            $sample[] = '1,Latitude 7490,ABC1234,i5/8th/16gb/256,' . $prefix . $next . ',Main Store';
        }
        return $sample;
    }

    /* "Download sample file": a CSV with the columns the import needs. */
    public function importSample()
    {
        $lines = $this->sampleLines();
        return response()->streamDownload(function () use ($lines) {
            echo "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";   // BOM so Excel reads it as UTF-8
        }, 'stock-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function importData(string $text, ?array $report, ?string $error = null): array
    {
        $req = request();
        $fname = $req->hasFile('file') ? $req->file('file')->getClientOriginalName() : (string) $req->input('fname', '');
        return ['per' => \App\Support\Pager::size($req->input('per')), 'page' => (int) $req->input('page', 1), 'text' => $text, 'report' => $report, 'error' => $error, 'headers' => ImportCheck::HEADERS, 'sample' => implode("\n", $this->sampleLines()), 'fname' => mb_substr($fname, 0, 150)];
    }

    private function report(string $text): array
    {
        $rows = ImportCheck::rows(ImportCheck::tableFromText($text));

        $items = [];
        foreach (DB::table('items')->orderBy('id')->get(['id', 'name', 'tracked']) as $i) {
            $items[ImportCheck::normName($i->name)] ??= ['id' => $i->id, 'name' => $i->name, 'tracked' => (bool) $i->tracked];
        }
        $stores = [];
        foreach (DB::table('stores')->orderBy('id')->get(['id', 'name']) as $s) {
            $stores[mb_strtolower($s->name)] ??= $s->id;
        }
        $prefix = Barcodes::prefix();
        $codes = [];
        foreach ($rows as $r) {
            if (($c = ImportCheck::parseCode($r['raw'], $prefix)) !== null) {
                $codes[$c] = true;
            }
        }
        $existing = [];
        foreach (array_chunk(array_keys($codes), 1000) as $chunk) {
            foreach (DB::table('devices')->whereIn('code', $chunk)->pluck('code') as $code) {
                $existing[(int) $code] = true;
            }
        }
        return ImportCheck::validate($rows, $items, $stores, $existing, $prefix);
    }
}
