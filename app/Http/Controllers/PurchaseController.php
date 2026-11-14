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
            ->select('p.id', 'p.created_at', 'p.inv_no', 's.name as supplier')
            ->selectRaw('(select coalesce(sum(qty), 0) from purchase_lines l where l.purchase_id = p.id) as items');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('cast(p.id as char) like ?', [$like])->orWhere('p.inv_no', 'like', $like)->orWhere('s.name', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('p.id')->paginate($n)->withQueryString();
        return view('purchase.index', compact('rows', 'q', 'n'));
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
        $lines = [];
        foreach ((array) $request->input('lines', []) as $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $qty = (string) ($l['qty'] ?? '');
            if (!$itemId || $qty === '' || (float) $qty <= 0) {
                continue;   // an empty row is ignored, as in the prototype
            }
            if (!ctype_digit($qty) || (int) $qty > 5000) {
                return $fail('Quantity must be a whole number from 1 to 5000.');
            }
            $rate = (string) ($l['rate'] ?? '');
            if ($rate !== '' && (!is_numeric($rate) || (float) $rate < 0)) {
                return $fail('Rate must be a number, zero or more.');
            }
            if (!DB::table('items')->where('id', $itemId)->exists()) {
                return $fail('One of the items is not in the Item master.');
            }
            $hsnIn = trim((string) ($l['hsn'] ?? ''));
            if ($hsnIn !== '' && !preg_match('/^[0-9]{1,10}$/', $hsnIn)) {
                return $fail('HSN code must be digits only (10 at most).');
            }
            $bc = ($l['bc'] ?? '') === 'yes' ? true : (($l['bc'] ?? '') === 'no' ? false : null);
            $tags = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($l['tags'] ?? ''))), fn ($t) => $t !== ''));
            $tracked = $bc === null ? (bool) DB::table('items')->where('id', $itemId)->value('tracked') : $bc;
            if ($tracked) {
                $name = (string) DB::table('items')->where('id', $itemId)->value('name');
                if (count($tags) !== (int) $qty) {
                    return $fail('Brand tag: ' . $name . ' needs ' . (int) $qty . ' brand tag' . ((int) $qty === 1 ? '' : 's') . ' (one for each piece, separated by commas). You entered ' . count($tags) . '.');
                }
                foreach ($tags as $t) {
                    if (mb_strlen($t) > 60) {
                        return $fail('A brand tag can have 60 characters at most.');
                    }
                }
            } else {
                $tags = [];
            }
            $lines[] = [
                'hsn' => $hsnIn, 'tags' => $tags, 'item_id' => $itemId, 'config' => mb_substr(trim((string) ($l['config'] ?? '')), 0, 150), 'qty' => (int) $qty,
                'rate' => $rate === '' ? 0 : round((float) $rate, 2),
                'barcode' => ($l['bc'] ?? '') === 'yes' ? true : (($l['bc'] ?? '') === 'no' ? false : null),
            ];
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
            'items' => DB::table('items')->orderBy('name')->get(['id', 'name', 'tracked']),
            'codes' => DB::table('devices')->where('purchase_id', $id)->orderBy('code')->get(['code', 'purchase_line_id'])->groupBy('purchase_line_id'),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
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
        try {
            Purchases::update($id, [
                'supplier_id' => $supplierId, 'inv_no' => mb_substr(trim((string) $request->input('inv_no', '')), 0, 60), 'inv_date' => $date,
                'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
                'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
                'store_id' => $storeId, 'lines' => $lines,
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
        $codes = DB::table('devices')->where('purchase_id', $id)->orderBy('code')->get(['code', 'purchase_line_id'])->groupBy('purchase_line_id');
        $barcoded = $lines->sum(fn ($l) => count($codes[$l->id] ?? []));
        $qtyAdded = $lines->filter(fn ($l) => !$l->barcoded)->sum('qty');
        $company = DB::table('company_profile')->where('id', 1)->first();
        $blocked = Purchases::deleteBlocker($id);
        return view('purchase.show', compact('p', 'lines', 'codes', 'barcoded', 'qtyAdded', 'company', 'blocked'));
    }
}
