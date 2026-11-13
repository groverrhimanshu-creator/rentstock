<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Docs;
use App\Support\Rentals;
use App\Support\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Rental: challans for items that go out, and the rental invoices made from Sale. */
class RentalController extends Controller
{
    /* A date typed as 2026-10-17, or null. */
    public static function ymd($v): ?string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return $v;
    }

    /*
     * Expected duration must be chosen. Short term needs the expected return date, Long term needs none.
     * Returns the date ('Y-m-d'), null for long term, or [message] when the date is missing or wrong.
     */
    private function returnBy(Request $request)
    {
        if ($request->input('duration') === 'long') {
            return null;
        }
        if ($request->input('duration') !== 'short') {
            return ['Choose the expected duration: Short term or Long term.'];
        }
        $d = self::ymd($request->input('return_by'));
        return $d ?: ['Short term needs the expected return date. Choose it, or set the duration to Long term.'];
    }

    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('rentals as r')->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->select('r.id', 'r.no', 'r.kind', 'r.created_at', 'c.name as customer')
            ->selectRaw('(select count(*) from rental_lines l where l.rental_id = r.id) as items')
            ->selectRaw('(select count(*) from rental_lines l where l.rental_id = r.id and l.returned_at is null) as still');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('r.no', 'like', $like)->orWhere('c.name', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('r.id')->paginate($n)->withQueryString();
        return view('rental.index', compact('rows', 'q', 'n'));
    }

    public function create()
    {
        return view('rental.form', [
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name']),
            'stockItems' => DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.status', 'in_stock')
                ->groupBy('i.id', 'i.name')->orderBy('i.name')->get(['i.id', 'i.name', DB::raw('count(*) as n')]),
            'prefix' => Barcodes::prefix(),
            'returnBy' => date('Y-m-d', strtotime('+30 days')),
        ]);
    }

    /* Save a challan: the pieces go on rent with the customer. */
    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $returnBy = $this->returnBy($request);
        if (is_array($returnBy)) {
            return $fail($returnBy[0]);
        }
        $lines = [];
        $seen = [];
        foreach ((array) $request->input('lines', []) as $l) {
            $code = (int) ($l['code'] ?? 0);
            $rent = (string) ($l['rent'] ?? '');
            if (!$code) {
                return $fail('Add at least one item.');
            }
            if ($rent === '' || !is_numeric($rent) || (float) $rent < 0) {
                return $fail('Every item needs a rent per month (zero or more).');
            }
            if (isset($seen[$code])) {
                return $fail('The same barcode is added twice.');
            }
            $seen[$code] = true;
            $lines[] = ['code' => $code, 'rent' => round((float) $rent, 2)];
        }
        if (!$lines) {
            return $fail('Add at least one item.');
        }
        if (count($lines) > 500) {
            return $fail('One challan can have 500 items at most. Split it into two challans.');
        }
        try {
            $id = Rentals::createChallan([
                'customer_id' => $customerId, 'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
                'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
                'remark' => mb_substr(trim((string) $request->input('remark', '')), 0, 255), 'return_by' => $returnBy, 'lines' => $lines,
            ], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $no = DB::table('rentals')->where('id', $id)->value('no');
        return redirect()->route('rental.show', $id)->with('ok', 'Rent saved. Challan ' . $no . ' created.');
    }

    /* The items to bill for a customer and period, for the rental invoice on the Sale screen. */
    public function bill(Request $request)
    {
        $cid = (int) $request->query('customer_id');
        $from = self::ymd($request->query('from'));
        $to = self::ymd($request->query('to'));
        if (!$cid || !$from || !$to) {
            return response()->json(['rows' => [], 'error' => 'Choose the customer and the dates.']);
        }
        if ($to < $from) {
            return response()->json(['rows' => [], 'error' => '"Bill to" cannot be before "Bill from".']);
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
            return response()->json(['rows' => [], 'error' => 'A rental invoice can cover 366 days at most.']);
        }
        $rows = Rentals::billLines($cid, $from, $to, $request->query('full', '1') !== '0');
        if ($rows) {
            return response()->json(['rows' => $rows]);
        }
        // Nothing to bill in these dates. Say why, so it does not look like the items are missing.
        $first = DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')
            ->where('r.kind', 'challan')->where('r.customer_id', $cid)->whereNull('l.returned_at')->min('l.from_at');
        $fmt = fn (string $d) => date('d/m/Y', strtotime($d));
        if (!$first) {
            return response()->json(['rows' => [], 'note' => 'This customer has no items on rent, so there is nothing to bill.']);
        }
        $start = substr((string) $first, 0, 10);
        return response()->json(['rows' => [], 'suggest' => ['from' => $start, 'to' => max($start, date('Y-m-d'))],
            'note' => 'This customer has items on rent, but none of them was out between ' . $fmt($from) . ' and ' . $fmt($to) . '. The earliest went on rent on ' . $fmt($start) . '. Change "Bill from" and "Bill to" to dates that include the rental, or press "Use these dates".']);
    }

    /*
     * The dates a new rental invoice starts with once the customer is chosen.
     * Bill from: the day after the customer's last rental invoice, or the day their first items went out
     * (or the day the customer was created, if nothing was ever given on rent). Bill to: today.
     */
    public function period(Request $request)
    {
        $cid = (int) $request->query('customer_id');
        $today = date('Y-m-d');
        $from = null;
        if ($cid) {
            $last = DB::table('rentals')->where('kind', 'invoice')->where('customer_id', $cid)->max('period_to');
            if ($last) {
                $from = date('Y-m-d', strtotime(substr((string) $last, 0, 10) . ' +1 day'));
            } else {
                $first = DB::table('rental_lines as l')->join('rentals as r', 'r.id', '=', 'l.rental_id')->where('r.kind', 'challan')->where('r.customer_id', $cid)->min('l.from_at');
                $from = $first ? substr((string) $first, 0, 10) : substr((string) DB::table('customers')->where('id', $cid)->value('created_at'), 0, 10);
            }
        }
        if (!$from || $from > $today) {
            $from = $today;
        }
        return response()->json(['from' => $from, 'to' => $today]);
    }

    public function show(int $id)
    {
        return $this->render($id, null);
    }

    /* A customer opening their own challan or rental invoice. */
    public function mine(Request $request, int $id)
    {
        return $this->render($id, (int) $request->user()->customer_id);
    }

    private function render(int $id, ?int $customerId)
    {
        $doc = Docs::rental($id, $customerId);
        abort_unless($doc, 404);
        $r = $doc['r'];
        $blocked = null;
        if ($customerId === null) {
            $blocked = $r->kind === 'invoice' ? Rentals::invoiceBlocker($id, true) : Rentals::challanDeleteBlocker($id);
        }
        return view('rental.show', [
            'r' => $r, 'groups' => $doc['groups'], 'sales' => $doc['sales'], 'count' => $doc['lines']->where('line_kind', 'rent')->count(), 'soldCount' => (int) $doc['lines']->where('line_kind', 'sale')->sum('qty'), 'company' => Docs::company(),
            'mine' => $customerId !== null, 'blocked' => $blocked,
        ]);
    }

    public function edit(int $id)
    {
        $r = DB::table('rentals')->where('id', $id)->first();
        abort_unless($r, 404);
        if ($r->kind === 'invoice' && ($m = Rentals::invoiceBlocker($id, false))) {
            return redirect()->route('rental.show', $id)->with('bad', $m);
        }
        $lines = DB::table('rental_lines as l')->join('items as i', 'i.id', '=', 'l.item_id')->where('l.rental_id', $id)->orderBy('l.line_kind')->orderBy('l.id')->get(['l.*', 'i.name']);
        $why = [];
        if ($r->kind === 'challan') {
            foreach ($lines as $l) {
                $why[$l->id] = Rentals::lineRemoveBlocker($r, $l);
            }
        }
        return view($r->kind === 'invoice' ? 'rental.edit-invoice' : 'rental.edit-challan', [
            'why' => $why, 'stockUrl' => route('sale.stock'), 'prefix' => \App\Support\Barcodes::prefix(),
            'r' => $r, 'lines' => $lines, 'customer' => (string) DB::table('customers')->where('id', $r->customer_id)->value('name'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        $r = DB::table('rentals')->where('id', $id)->first();
        abort_unless($r, 404);
        $common = [
            'type' => $request->input('type') === 'Bill of supply' ? 'Bill of supply' : 'GST',
            'pay_mode' => in_array($request->input('pay_mode'), ['Cash', 'Cheque/Online', 'Credit'], true) ? $request->input('pay_mode') : 'Cash',
            'remark' => mb_substr(trim((string) $request->input('remark', '')), 0, 255),
        ];
        try {
            if ($r->kind === 'invoice') {
                $prices = [];
                foreach ((array) $request->input('prices', []) as $lid => $price) {
                    if ((string) $price === '' || !is_numeric($price) || (float) $price < 0) {
                        return $fail('Every line needs an amount (zero or more).');
                    }
                    $prices[(int) $lid] = round((float) $price, 2);
                }
                Rentals::updateInvoice($id, $common + ['prices' => $prices], $request->user()->id);
                $msg = 'Invoice updated';
            } else {
                $returnBy = $this->returnBy($request);
                if (is_array($returnBy)) {
                    return $fail($returnBy[0]);
                }
                $rents = [];
                foreach ((array) $request->input('rents', []) as $lid => $rent) {
                    if ((string) $rent === '' || !is_numeric($rent) || (float) $rent < 0) {
                        return $fail('Every item needs a rent per month (zero or more).');
                    }
                    $rents[(int) $lid] = round((float) $rent, 2);
                }
                $add = [];
                foreach ((array) $request->input('add', []) as $a) {
                    $code = (int) ($a['code'] ?? 0);
                    $rent = (string) ($a['rent'] ?? '');
                    if (!$code) {
                        continue;
                    }
                    if ($rent === '' || !is_numeric($rent) || (float) $rent < 0) {
                        return $fail('Every added item needs a rent per month (zero or more).');
                    }
                    $add[] = ['code' => $code, 'rent' => round((float) $rent, 2)];
                }
                $remove = array_map('intval', (array) $request->input('remove', []));
                Rentals::updateChallan($id, $common + ['return_by' => $returnBy, 'rents' => $rents, 'remove' => $remove, 'add' => $add], $request->user()->id);
                $msg = 'Challan updated';
            }
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        return redirect()->route('rental.show', $id)->with('ok', $msg);
    }

    public function destroy(Request $request, int $id)
    {
        $r = DB::table('rentals')->where('id', $id)->first(['no', 'kind']);
        abort_unless($r, 404);
        try {
            if ($r->kind === 'invoice') {
                Rentals::deleteInvoice($id, $request->user()->id);
                $msg = 'Rental invoice ' . $r->no . ' deleted. Any items sold on it are back in stock.';
            } else {
                Rentals::deleteChallan($id, $request->user()->id);
                $msg = 'Challan ' . $r->no . ' deleted. Its items are back in stock.';
            }
        } catch (RuntimeException $e) {
            return redirect()->route('rental.show', $id)->with('bad', $e->getMessage());
        }
        return redirect()->route('rental.index')->with('ok', $msg);
    }
}
