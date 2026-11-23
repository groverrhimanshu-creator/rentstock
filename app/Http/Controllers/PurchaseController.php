<?php

namespace App\Http\Controllers;

use App\Support\Purchases;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    private const INVOICE_MSG = 'The supplier invoice must be a PDF, JPG or PNG file of 5 MB or less.';

    /* The uploaded supplier invoice, checked. Returns null when none was chosen, or false when it is not acceptable. */
    private function invoiceUpload(Request $request)
    {
        if (!$request->hasFile('invoice_file')) {
            return null;
        }
        $f = $request->file('invoice_file');
        if (!$f->isValid() || $f->getSize() > 5 * 1024 * 1024 || !in_array(strtolower((string) $f->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png'], true)
            || !in_array((string) $f->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return false;
        }
        return $f;
    }

    private function saveInvoice(int $id, $file): void
    {
        $old = DB::table('purchases')->where('id', $id)->value('invoice_file');
        $path = $file->store('purchase-invoices');
        DB::table('purchases')->where('id', $id)->update(['invoice_file' => $path, 'invoice_file_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
        if ($old) {
            \Illuminate\Support\Facades\Storage::delete($old);
        }
    }

    /* New purchase rows from the form (rows with no item or no quantity are ignored). Returns the rows, or an error message as a string. */
    private function parseNewLines(array $raw)
    {
        $lines = [];
        foreach ($raw as $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $qty = (string) ($l['qty'] ?? '');
            if (!$itemId || $qty === '' || (float) $qty <= 0) {
                continue;   // an empty row is ignored, as in the prototype
            }
            if (!ctype_digit($qty) || (int) $qty > 5000) {
                return ('Quantity must be a whole number from 1 to 5000.');
            }
            $rate = (string) ($l['rate'] ?? '');
            if ($rate !== '' && (!is_numeric($rate) || (float) $rate < 0)) {
                return ('Rate must be a number, zero or more.');
            }
            if (!DB::table('items')->where('id', $itemId)->exists()) {
                return ('One of the items is not in the Item master.');
            }
            $hsnIn = trim((string) ($l['hsn'] ?? ''));
            if ($hsnIn !== '' && !preg_match('/^[0-9]{1,10}$/', $hsnIn)) {
                return ('HSN code must be digits only (10 at most).');
            }
            $bc = ($l['bc'] ?? '') === 'yes' ? true : (($l['bc'] ?? '') === 'no' ? false : null);
            $tags = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($l['tags'] ?? ''))), fn ($t) => $t !== ''));
            $tracked = $bc === null ? (bool) DB::table('items')->where('id', $itemId)->value('tracked') : $bc;
            if ($tracked) {
                $name = (string) DB::table('items')->where('id', $itemId)->value('name');
                if (count($tags) !== (int) $qty) {
                    return ('Brand tag: ' . $name . ' needs ' . (int) $qty . ' brand tag' . ((int) $qty === 1 ? '' : 's') . ' (one for each piece, separated by commas). You entered ' . count($tags) . '.');
                }
                foreach ($tags as $t) {
                    if (mb_strlen($t) > 60) {
                        return ('A brand tag can have 60 characters at most.');
                    }
                }
            } elseif ($tags) {
                $tags = array_map(fn ($t) => mb_substr($t, 0, 60), array_slice($tags, 0, 500));   // kept as a note on the line
            }
            $lines[] = [
                'hsn' => $hsnIn, 'tags' => $tags, 'item_id' => $itemId, 'config' => mb_substr(trim((string) ($l['config'] ?? '')), 0, 150), 'qty' => (int) $qty,
                'rate' => $rate === '' ? 0 : round((float) $rate, 2),
                'barcode' => ($l['bc'] ?? '') === 'yes' ? true : (($l['bc'] ?? '') === 'no' ? false : null),
            ];
        }
        return $lines;
    }

    /*
     * Reads a CSV or Excel (.xlsx) file of pieces and returns purchase rows. Every file row is one piece:
     * Model/Product (an item of the Item master), Brand Tag, Config, HSN, Rate. Pieces with the same item, config, HSN and rate become one row.
     * By-quantity items add up a "Quantity" column (1 when empty).
     */
    public function importRows(Request $request)
    {
        $bad = fn (string $m) => response()->json(['ok' => false, 'error' => $m], 422);
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return $bad('Choose a CSV or Excel (.xlsx) file.');
        }
        $file = $request->file('file');
        if ($file->getSize() > \App\Support\ImportCheck::MAX_BYTES) {
            return $bad('The file is too big (10 MB at most).');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        try {
            if ($ext === 'xlsx') {
                $table = \App\Support\ImportCheck::tableFromXlsx($file->getRealPath());
            } elseif (in_array($ext, ['csv', 'txt'], true)) {
                $table = \App\Support\ImportCheck::tableFromText((string) file_get_contents($file->getRealPath()));
            } else {
                return $bad($ext === 'xls' ? 'Old .xls files cannot be read. Save the sheet as .xlsx or CSV.' : 'Choose a CSV or Excel (.xlsx) file.');
            }
        } catch (RuntimeException $e) {
            return $bad($e->getMessage());
        }
        if (count($table) < 2) {
            return $bad('The file has no data rows.');
        }
        $alias = ['item' => ['modelproduct', 'model', 'product', 'productname', 'itemname', 'item', 'name'], 'tag' => ['brandtag', 'tag', 'srno', 'serialno', 'serial'],
            'config' => ['config', 'configuration'], 'hsn' => ['hsncode', 'hsn'], 'rate' => ['rate', 'price', 'purchaseprice', 'purchaserate'], 'qty' => ['quantity', 'qty']];
        $idx = [];
        foreach ($table[0] as $i => $h) {
            $n = preg_replace('/[^a-z0-9]/', '', strtolower((string) $h));
            foreach ($alias as $k => $list) {
                if (!isset($idx[$k]) && in_array($n, $list, true)) {
                    $idx[$k] = $i;
                }
            }
        }
        if (!isset($idx['item'])) {
            return $bad('The first row must be the column names. The column "Model/Product" was not found. Columns: Model/Product, Brand Tag, Config, HSN, Rate, Quantity.');
        }
        $key = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        $items = [];
        foreach (DB::table('items')->get(['id', 'name', 'tracked', 'hsn']) as $it) {
            $items[$key($it->name)] = $it;
        }
        $groups = [];
        $unknown = [];
        $rows = 0;
        foreach (array_slice($table, 1, 5000) as $r) {
            $g = fn ($k) => isset($idx[$k]) ? trim((string) ($r[$idx[$k]] ?? '')) : '';
            $name = $g('item');
            if ($name === '') {
                continue;
            }
            $it = $items[$key($name)] ?? null;
            if (!$it) {
                $unknown[$name] = true;
                continue;
            }
            $rate = str_replace(',', '', $g('rate'));
            $rate = is_numeric($rate) && (float) $rate >= 0 ? round((float) $rate, 2) : 0;
            $hsn = preg_replace('/\.0+$/', '', $g('hsn'));
            $hsn = preg_match('/^[0-9]{1,10}$/', $hsn) ? $hsn : (string) $it->hsn;
            $cfg = mb_substr($g('config'), 0, 150);
            $k = $it->id . '|' . $cfg . '|' . $hsn . '|' . $rate;
            $groups[$k] = $groups[$k] ?? ['item_id' => (int) $it->id, 'config' => $cfg, 'hsn' => $hsn, 'rate' => $rate, 'bc' => $it->tracked ? 'yes' : 'no', 'qty' => 0, 'tags' => []];
            $q = ctype_digit($g('qty')) && (int) $g('qty') > 0 ? (int) $g('qty') : 1;
            if ($it->tracked) {
                $groups[$k]['qty']++;                                   // one file row = one piece
                $groups[$k]['tags'][] = mb_substr($g('tag'), 0, 60);
            } else {
                $groups[$k]['qty'] += $q;
                if ($g('tag') !== '') {
                    $groups[$k]['tags'][] = mb_substr($g('tag'), 0, 60);
                }
            }
            $rows++;
        }
        $lines = [];
        $missing = 0;
        foreach ($groups as $g) {
            if ($g['bc'] === 'yes') {
                $missing += count(array_filter($g['tags'], fn ($t) => $t === ''));
                $g['tags'] = array_values(array_filter($g['tags'], fn ($t) => $t !== ''));
            }
            $g['tags'] = implode(', ', $g['tags']);
            $lines[] = $g;
        }
        return response()->json(['ok' => true, 'rows' => $rows, 'lines' => array_slice($lines, 0, 200), 'unknown' => array_slice(array_keys($unknown), 0, 20), 'unknown_count' => count($unknown), 'missing_tags' => $missing]);
    }

    /* Opens the attached supplier invoice (PDF or image) in the browser. */
    public function invoice(int $id)
    {
        $p = DB::table('purchases')->where('id', $id)->first(['invoice_file', 'invoice_file_name']);
        abort_unless($p && $p->invoice_file && \Illuminate\Support\Facades\Storage::exists($p->invoice_file), 404);
        return \Illuminate\Support\Facades\Storage::response($p->invoice_file, $p->invoice_file_name ?: basename($p->invoice_file));
    }

    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('purchases as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->select('p.id', 'p.no', 'p.created_at', 'p.inv_no', 's.name as supplier')
            ->selectRaw('(select coalesce(sum(qty), 0) from purchase_lines l where l.purchase_id = p.id) as items');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('cast(p.no as char) like ?', [$like])->orWhere('p.inv_no', 'like', $like)->orWhere('s.name', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('p.no')->paginate($n)->withQueryString();
        return view('purchase.index', compact('rows', 'q', 'n'));
    }

    /* The same supplier cannot have the same invoice number twice (a double click or a second upload of one bill). Returns a message, or null if fine. */
    private function duplicateInvoice(int $supplierId, string $invNo, int $exceptId = 0): ?string
    {
        $invNo = trim($invNo);
        if ($invNo === '') {
            return null;
        }
        $q = DB::table('purchases')->where('supplier_id', $supplierId)->whereRaw('lower(trim(inv_no)) = ?', [mb_strtolower($invNo)]);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        $hit = $q->first(['no']);
        return $hit ? 'Supplier invoice ' . $invNo . ' of this supplier is already entered as purchase ' . $hit->no . '. The same bill cannot be added twice.' : null;
    }

    public function create()
    {
        return view('purchase.form', [
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name', 'state_code']),
            'stores' => DB::table('stores')->orderBy('id')->get(['id', 'name']),
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name', 'tracked', 'gst_percent', 'hsn']),
            'brands' => DB::table('brands')->orderBy('name')->get(['id', 'name']),
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'companyState' => (string) DB::table('company_profile')->where('id', 1)->value('state_code'),
        ]);
    }

    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);

        $supplierId = (int) $request->input('supplier_id');
        if (!$supplierId || !DB::table('suppliers')->where('id', $supplierId)->exists()) {
            return $fail('Please choose a supplier.');
        }
        if ($dup = $this->duplicateInvoice($supplierId, (string) $request->input('inv_no', ''))) {
            return $fail($dup);
        }
        $type = $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST';
        $pay = in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash';
        $storeId = (int) $request->input('store_id');
        if (!DB::table('stores')->where('id', $storeId)->exists()) {
            $storeId = (int) DB::table('stores')->orderBy('id')->value('id');
        }
        $date = (string) $request->input('inv_date', '');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fail('The invoice date is not valid.');
        }

        $file = $this->invoiceUpload($request);
        if ($file === false) {
            return $fail(self::INVOICE_MSG);
        }
        $lines = $this->parseNewLines((array) $request->input('lines', []));
        if (is_string($lines)) {
            return $fail($lines);
        }
        if (!$lines) {
            return $fail('Add at least one item.');
        }

        [$id, $added] = Purchases::create([
            'supplier_id' => $supplierId, 'inv_no' => mb_substr(trim((string) $request->input('inv_no', '')), 0, 60), 'inv_date' => $date,
            'type' => $type, 'pay_mode' => $pay, 'store_id' => $storeId, 'lines' => $lines,
        ], $request->user()->id);

        if ($file) {
            $this->saveInvoice($id, $file);
        }
        return redirect()->route('purchase.show', $id)->with('ok', 'Purchase saved. ' . $added . ' barcoded items added to stock.');
    }

    /* Adds a new item (and a new brand or category if needed) while a purchase is being entered. Returns the item as JSON. */
    public function quickItem(Request $request)
    {
        $bad = fn (string $m) => response()->json(['ok' => false, 'error' => $m], 422);
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->input('name', '')));
        if ($name === '') {
            return $bad('Please fill in Item name.');
        }
        if (mb_strlen($name) > 150) {
            return $bad('Item name is too long (150 characters at most).');
        }
        if (DB::table('items')->where('name', $name)->exists()) {
            return $bad('An item with this name already exists. Choose it from the list.');
        }
        $hsn = trim((string) $request->input('hsn', '8471'));
        if ($hsn !== '' && !preg_match('/^[0-9]{4,8}$/', $hsn)) {
            return $bad('HSN code must be 4 to 8 digits.');
        }
        $gst = (string) $request->input('gst', '18');
        if (!is_numeric($gst) || (float) $gst < 0 || (float) $gst > 28) {
            return $bad('GST % must be a number from 0 to 28.');
        }

        $pick = function (string $table, string $field) use ($request) {
            $new = trim(preg_replace('/\s+/', ' ', (string) $request->input($field . '_new', '')));
            if ($new !== '') {
                if (mb_strlen($new) > 100) {
                    return false;
                }
                $id = DB::table($table)->where('name', $new)->value('id');
                return $id ?: DB::table($table)->insertGetId(['name' => $new]);
            }
            $id = (int) $request->input($field . '_id');
            return $id && DB::table($table)->where('id', $id)->exists() ? $id : null;
        };

        try {
            $id = DB::transaction(function () use ($request, $name, $hsn, $gst, $pick) {
                $brand = $pick('brands', 'brand');
                $category = $pick('categories', 'category');
                if ($brand === false || $category === false) {
                    throw new \RuntimeException('The new brand or category name is too long (100 characters at most).');
                }
                return DB::table('items')->insertGetId([
                    'name' => $name, 'brand_id' => $brand, 'category_id' => $category,
                    'unit_id' => DB::table('units')->where('name', 'Nos')->value('id'),
                    'hsn' => $hsn, 'gst_percent' => (float) $gst, 'rent_per_month' => 0, 'sale_price' => 0,
                    'tracked' => $request->input('kind') === 'qty' ? 0 : 1,
                ]);
            });
        } catch (\RuntimeException $e) {
            return $bad($e->getMessage());
        }
        $it = DB::table('items')->where('id', $id)->first(['id', 'name', 'tracked', 'gst_percent']);
        return response()->json(['ok' => true, 'item' => ['id' => $it->id, 'name' => $it->name, 'tracked' => (bool) $it->tracked, 'gst' => (float) $it->gst_percent]]);
    }

    public function edit(int $id)
    {
        $p = DB::table('purchases')->where('id', $id)->first();
        abort_unless($p, 404);
        return view('purchase.edit', [
            'p' => $p,
            'lines' => DB::table('purchase_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->where('l.purchase_id', $id)->orderBy('l.id')->get(['l.*', 'i.name', 'i.hsn as item_hsn']),
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name', 'tracked', 'hsn', 'gst_percent']),
            'companyState' => (string) DB::table('company_profile')->where('id', 1)->value('state_code'),
            'why' => DB::table('purchase_lines')->where('purchase_id', $id)->get()->mapWithKeys(fn ($l) => [$l->id => Purchases::lineRemoveBlocker($l)])->all(),
            'codes' => DB::table('devices')->where('purchase_id', $id)->orderBy('code')->get(['code', 'purchase_line_id'])->groupBy('purchase_line_id'),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name', 'state_code']),
            'stores' => DB::table('stores')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        abort_unless(DB::table('purchases')->where('id', $id)->exists(), 404);

        $supplierId = (int) $request->input('supplier_id');
        if (!$supplierId || !DB::table('suppliers')->where('id', $supplierId)->exists()) {
            return $fail('Please choose a supplier.');
        }
        if ($dup = $this->duplicateInvoice($supplierId, (string) $request->input('inv_no', ''), $id)) {
            return $fail($dup);
        }
        $storeId = (int) $request->input('store_id');
        if (!DB::table('stores')->where('id', $storeId)->exists()) {
            return $fail('Please choose a store.');
        }
        $date = (string) $request->input('inv_date', '');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fail('The invoice date is not valid.');
        }
        $file = $this->invoiceUpload($request);
        if ($file === false) {
            return $fail(self::INVOICE_MSG);
        }
        $lines = [];
        foreach ((array) $request->input('lines', []) as $lid => $l) {
            $rate = (string) ($l['rate'] ?? '');
            if ($rate === '' || !is_numeric($rate) || (float) $rate < 0) {
                return $fail('Rate must be a number, zero or more.');
            }
            $hsn = trim((string) ($l['hsn'] ?? ''));
            if ($hsn !== '' && !preg_match('/^[0-9]{1,10}$/', $hsn)) {
                return $fail('HSN code must be digits only (10 at most).');
            }
            $qty = (string) ($l['qty'] ?? '');
            if ($qty !== '' && (!ctype_digit($qty) || (int) $qty < 1 || (int) $qty > 5000)) {
                return $fail('Quantity must be a whole number from 1 to 5000.');
            }
            $itemId = (int) ($l['item_id'] ?? 0);
            if ($itemId && !DB::table('items')->where('id', $itemId)->exists()) {
                return $fail('One of the items is not in the Item master.');
            }
            $tags = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($l['tags'] ?? ''))), fn ($t) => $t !== ''));
            foreach ($tags as $t) {
                if (mb_strlen($t) > 60) {
                    return $fail('A brand tag can have 60 characters at most.');
                }
            }
            $lines[(int) $lid] = ['config' => mb_substr(trim((string) ($l['config'] ?? '')), 0, 150), 'rate' => round((float) $rate, 2),
                'hsn' => $hsn, 'item_id' => $itemId, 'qty' => $qty === '' ? 0 : (int) $qty, 'tags' => $tags];
        }
        $add = $this->parseNewLines((array) $request->input('add', []));
        if (is_string($add)) {
            return $fail($add);
        }
        $remove = array_map('intval', (array) $request->input('remove', []));
        try {
            Purchases::update($id, [
                'supplier_id' => $supplierId, 'inv_no' => mb_substr(trim((string) $request->input('inv_no', '')), 0, 60), 'inv_date' => $date,
                'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
                'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
                'store_id' => $storeId, 'lines' => $lines, 'remove' => $remove, 'add' => $add,
            ], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        if ($file) {
            $this->saveInvoice($id, $file);
        } elseif ($request->boolean('remove_invoice')) {
            $old = DB::table('purchases')->where('id', $id)->value('invoice_file');
            DB::table('purchases')->where('id', $id)->update(['invoice_file' => null, 'invoice_file_name' => null]);
            if ($old) {
                \Illuminate\Support\Facades\Storage::delete($old);
            }
        }
        return redirect()->route('purchase.show', $id)->with('ok', 'Purchase updated');
    }

    public function destroy(Request $request, int $id)
    {
        $oldFile = DB::table('purchases')->where('id', $id)->value('invoice_file');
        try {
            Purchases::delete($id, $request->user()->id);
            if ($oldFile) {
                \Illuminate\Support\Facades\Storage::delete($oldFile);
            }
        } catch (RuntimeException $e) {
            return redirect()->route('purchase.show', $id)->with('bad', $e->getMessage());
        }
        return redirect()->route('purchase.index')->with('ok', 'Purchase ' . $id . ' deleted');
    }

    public function show(int $id)
    {
        $p = DB::table('purchases as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')->leftJoin('stores as st', 'st.id', '=', 'p.store_id')
            ->where('p.id', $id)->select('p.*', 's.name as sname', 's.address', 's.city', 's.state', 's.state_code', 's.gstin', 's.phone', 'st.name as store')->first();
        abort_unless($p, 404);
        $lines = DB::table('purchase_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->where('l.purchase_id', $id)->orderBy('l.id')->get(['l.*', 'i.name', 'i.hsn as item_hsn']);
        $codes = DB::table('devices')->where('purchase_id', $id)->orderBy('code')->get(['code', 'purchase_line_id', 'brand_tag'])->groupBy('purchase_line_id');
        $barcoded = $lines->sum(fn ($l) => count($codes[$l->id] ?? []));
        $qtyAdded = $lines->filter(fn ($l) => !$l->barcoded)->sum('qty');
        $company = DB::table('company_profile')->where('id', 1)->first();
        $blocked = Purchases::deleteBlocker($id);
        return view('purchase.show', compact('p', 'lines', 'codes', 'barcoded', 'qtyAdded', 'company', 'blocked'));
    }
}
