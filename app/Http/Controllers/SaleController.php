<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Rentals;
use App\Support\Sales;
use App\Support\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')
            ->select('s.id', 's.no', 's.created_at', 's.total', 'c.name as customer')
            ->selectRaw('(select coalesce(sum(qty), 0) from sale_lines l where l.sale_id = s.id) as items');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('s.no', 'like', $like)->orWhere('c.name', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('s.id')->paginate($n)->withQueryString();
        return view('sale.index', compact('rows', 'q', 'n'));
    }

    public function create()
    {
        return view('sale.form', [
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name', 'state_code']),
            'items' => DB::table('items')->get(['id', 'name', 'gst_percent', 'sale_price', 'hsn'])->keyBy('id'),
            'stockItems' => DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.status', 'in_stock')
                ->groupBy('i.id', 'i.name')->orderBy('i.name')->get(['i.id', 'i.name', DB::raw('count(*) as n')]),
            'qtyItems' => DB::table('item_qty as q')->join('items as i', 'i.id', '=', 'q.item_id')->where('q.qty', '>', 0)->orderBy('i.name')->get(['i.id', 'i.name', 'q.qty']),
            'companyState' => (string) DB::table('company_profile')->where('id', 1)->value('state_code'),
            'prefix' => Barcodes::prefix(),
            'billFrom' => date('Y-m-d'),
            'billTo' => date('Y-m-d'),
            'billUrl' => route('rental.bill'),
            'periodUrl' => route('rental.period'),
            'rentSac' => (string) config('rentstock.rent_sac', '997315'),
        ]);
    }

    /* Finds barcoded pieces that are in stock, for the scan box and the tick list. */
    public function stock(Request $request)
    {
        $prefix = Barcodes::prefix();
        $base = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')
            ->leftJoin('brands as b', 'b.id', '=', 'i.brand_id')->leftJoin('categories as ct', 'ct.id', '=', 'i.category_id');
        $shape = fn ($r) => ['code' => (int) $r->code, 'bc' => $prefix . $r->code, 'item_id' => (int) $r->item_id, 'model' => $r->model, 'brand' => (string) ($r->brand ?? ''), 'config' => $r->config, 'price' => (float) $r->sale_price, 'rent' => (float) $r->rent_per_month];
        $cols = ['d.code', 'd.item_id', 'd.config', 'd.status', 'i.name as model', 'i.sale_price', 'i.rent_per_month', 'b.name as brand'];

        // An exact barcode: say what it is, even when it is not in stock.
        if ($request->filled('code')) {
            $code = Barcodes::parse((string) $request->query('code'));
            $r = $code ? (clone $base)->where('d.code', $code)->first($cols) : null;
            if (!$r) {
                return response()->json(['found' => false]);
            }
            return response()->json(['found' => true, 'status' => $r->status, 'status_label' => Stock::STATUS[$r->status][0] ?? $r->status, 'row' => $shape($r)]);
        }

        $q = (clone $base)->where('d.status', 'in_stock');
        if ($request->filled('item')) {
            $q->where('d.item_id', (int) $request->query('item'));
        }
        if ($request->filled('category')) {
            $q->where('i.category_id', (int) $request->query('category'));   // the replacement screen only offers the faulty item's category
        }
        foreach (preg_split('/\s+/', trim((string) $request->query('q', '')), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $like = '%' . $w . '%';
            $q->where(function ($x) use ($like, $prefix) {
                $x->whereRaw('concat(?, d.code) like ?', [$prefix, $like])->orWhere('i.name', 'like', $like)->orWhere('b.name', 'like', $like)
                    ->orWhere('ct.name', 'like', $like)->orWhere('d.config', 'like', $like)->orWhere('d.brand_tag', 'like', $like);
            });
        }
        $exclude = array_filter(array_map('intval', explode(',', (string) $request->query('exclude', ''))));
        if ($exclude) {
            $q->whereNotIn('d.code', $exclude);
        }
        $limit = min(max((int) $request->query('limit', 8), 1), 40);
        $total = (clone $q)->count();
        $rows = $q->orderBy('d.code')->limit($limit)->get($cols)->map($shape)->values();
        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);

        if ($request->input('mode') === 'rentinv') {
            return $this->storeRentalInvoice($request, $fail);
        }

        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $type = $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST';
        $pay = in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash';

        $read = $this->readLines($request);
        if (is_string($read)) {
            return $fail($read);
        }
        $lines = $read;
        if (!$lines) {
            return $fail('Add at least one item.');
        }
        if (count($lines) > 500) {
            return $fail('One invoice can have 500 lines at most. Split it into two invoices.');
        }

        try {
            $id = Sales::create(['customer_id' => $customerId, 'type' => $type, 'pay_mode' => $pay, 'remark' => mb_substr(trim((string) $request->input('remark', '')), 0, 255), 'lines' => $lines], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $no = DB::table('sales')->where('id', $id)->value('no');
        return redirect()->route('sale.show', $id)->with('ok', 'Sale saved. Stock reduced. Invoice ' . $no . ' created.');
    }

    /* The sold lines of the form (barcoded pieces and quantity items). Returns the lines, or a plain message when one is wrong. */
    private function readLines(Request $request)
    {
        $lines = [];
        $seen = [];
        foreach ((array) $request->input('lines', []) as $l) {
            $price = (string) ($l['price'] ?? '');
            if ($price === '' || !is_numeric($price) || (float) $price < 0) {
                return 'Every item needs a price (zero or more).';
            }
            $code = (int) ($l['code'] ?? 0);
            if ($code) {
                if (isset($seen[$code])) {
                    return 'The same barcode is added twice.';
                }
                $seen[$code] = true;
                $itemId = (int) DB::table('devices')->where('code', $code)->value('item_id');
                if (!$itemId) {
                    return 'Barcode ' . Barcodes::text($code) . ' is not in the system.';
                }
                $lines[] = ['code' => $code, 'item_id' => $itemId, 'qty' => 1, 'price' => round((float) $price, 2)];
            } else {
                $itemId = (int) ($l['item_id'] ?? 0);
                $qty = (string) ($l['qty'] ?? '');
                if (!$itemId || !DB::table('items')->where('id', $itemId)->exists() || !ctype_digit($qty) || (int) $qty < 1) {
                    return 'Quantity must be a whole number of 1 or more.';
                }
                $lines[] = ['code' => null, 'item_id' => $itemId, 'qty' => (int) $qty, 'price' => round((float) $price, 2)];
            }
        }
        return $lines;
    }

    /* "Rental invoice" on the Sale screen: the items on rent are billed for the period, and items can be sold on the same invoice. */
    private function storeRentalInvoice(Request $request, \Closure $fail)
    {
        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $from = RentalController::ymd($request->input('period_from'));
        $to = RentalController::ymd($request->input('period_to'));
        if (!$from || !$to) {
            return $fail('Choose the "Bill from" and "Bill to" dates.');
        }
        if ($to < $from) {
            return $fail('"Bill to" cannot be before "Bill from".');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
            return $fail('A rental invoice can cover 366 days at most.');
        }
        $prices = [];
        foreach ((array) $request->input('rent', []) as $l) {
            $code = (int) ($l['code'] ?? 0);
            $price = (string) ($l['price'] ?? '');
            if (!$code) {
                continue;
            }
            if ($price === '' || !is_numeric($price) || (float) $price < 0) {
                return $fail('Every line needs an amount (zero or more).');
            }
            $prices[$code] = round((float) $price, 2);
        }
        if (!$prices) {
            return $fail('Add at least one item.');
        }
        // Items sold on the same invoice (optional).
        $sale = $this->readLines($request);
        if (is_string($sale)) {
            return $fail($sale);
        }
        if (count($prices) + count($sale) > 500) {
            return $fail('One invoice can have 500 lines at most. Split it into two invoices.');
        }
        $clash = array_intersect_key($prices, array_flip(array_filter(array_column($sale, 'code'))));
        if ($clash) {
            return $fail(Barcodes::text((int) array_key_first($clash)) . ' is on rent with this customer, so it cannot also be sold on this invoice.');
        }
        try {
            $id = Rentals::createInvoice([
                'customer_id' => $customerId, 'period_from' => $from, 'period_to' => $to, 'prices' => $prices, 'sale_lines' => $sale, 'full' => $request->boolean('full'),
                'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
                'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
                'remark' => mb_substr(trim((string) $request->input('remark', '')), 0, 255),
            ], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $no = DB::table('rentals')->where('id', $id)->value('no');
        return redirect()->route('rental.show', $id)->with('ok', 'Rental invoice ' . $no . ' created.');
    }

    public function edit(int $id)
    {
        $s = DB::table('sales')->where('id', $id)->first();
        abort_unless($s, 404);
        if ($m = Sales::blocker($id, false)) {
            return redirect()->route('sale.show', $id)->with('bad', $m);
        }
        return view('sale.edit', [
            's' => $s,
            'lines' => DB::table('sale_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->where('l.sale_id', $id)->orderBy('l.id')->get(['l.*', 'i.name']),
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name', 'state_code']),
            'companyState' => (string) DB::table('company_profile')->where('id', 1)->value('state_code'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        abort_unless(DB::table('sales')->where('id', $id)->exists(), 404);
        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $prices = [];
        foreach ((array) $request->input('prices', []) as $lid => $price) {
            if ((string) $price === '' || !is_numeric($price) || (float) $price < 0) {
                return $fail('Every item needs a price (zero or more).');
            }
            $prices[(int) $lid] = round((float) $price, 2);
        }
        try {
            Sales::update($id, [
                'customer_id' => $customerId, 'prices' => $prices,
                'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
                'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
                'remark' => mb_substr(trim((string) $request->input('remark', '')), 0, 255),
            ], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        return redirect()->route('sale.show', $id)->with('ok', 'Invoice updated');
    }

    public function destroy(Request $request, int $id)
    {
        $no = (string) DB::table('sales')->where('id', $id)->value('no');
        try {
            Sales::delete($id, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('sale.show', $id)->with('bad', $e->getMessage());
        }
        return redirect()->route('sale.index')->with('ok', 'Invoice ' . $no . ' deleted. Its items are back in stock.');
    }

    public function show(int $id)
    {
        return $this->render($id, null);
    }

    /* A customer opening their own sale invoice. */
    public function mine(Request $request, int $id)
    {
        return $this->render($id, (int) $request->user()->customer_id);
    }

    private function render(int $id, ?int $customerId)
    {
        $s = DB::table('sales as s')->join('customers as c', 'c.id', '=', 's.customer_id')->where('s.id', $id)
            ->select('s.*', 'c.name as cname', 'c.address', 'c.city', 'c.state', 'c.state_code', 'c.gstin', 'c.phone')->first();
        abort_unless($s && ($customerId === null || (int) $s->customer_id === $customerId), 404);
        $lines = DB::table('sale_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->where('l.sale_id', $id)->orderBy('l.id')->get(['l.*', 'i.name']);
        return view('sale.show', ['mine' => $customerId !== null, 'blocked' => $customerId === null ? Sales::blocker($id, true) : null, 's' => $s, 'groups' => Sales::groupLines($lines), 'count' => $lines->sum('qty'), 'company' => DB::table('company_profile')->where('id', 1)->first()]);
    }
}
