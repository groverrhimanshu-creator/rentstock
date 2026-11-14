<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Moves;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Return: items come back from a customer. Good ones go to stock, damaged ones go to repair. */
class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('returns as r')->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->select('r.id', 'r.no', 'r.created_at', 'r.reason', 'r.dc_file', 'c.name as customer')
            ->selectRaw('(select count(*) from return_lines l where l.return_id = r.id) as items');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('r.no', 'like', $like)->orWhere('c.name', 'like', $like)->orWhere('r.reason', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('r.id')->paginate($n)->withQueryString();
        return view('return.index', compact('rows', 'q', 'n'));
    }

    public function create()
    {
        return view('return.form', [
            'customers' => DB::table('customers as c')->orderBy('c.name')
                ->get(['c.id', 'c.name', DB::raw("(select count(*) from devices d where d.customer_id = c.id and d.status = 'rented') as on_rent")]),
            'reasons' => Moves::REASONS,
            'prefix' => Barcodes::prefix(),
        ]);
    }

    /* The items on rent with a customer (JSON for the form). */
    public function items(Request $request)
    {
        return response()->json(['rows' => Moves::onRent((int) $request->query('customer_id'))]);
    }

    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        if (\App\Support\DcCopy::bad($request)) {
            return $fail(\App\Support\DcCopy::MSG);
        }
        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $items = [];
        foreach ((array) $request->input('items', []) as $code => $x) {
            $code = (int) $code;
            if (!$code) {
                continue;
            }
            $reason = trim((string) ($x['reason'] ?? ''));
            $items[] = [
                'code' => $code,
                'cond' => ($x['cond'] ?? '') === 'Damaged' ? 'Damaged' : 'Good',
                'reason' => in_array($reason, Moves::REASONS, true) ? $reason : Moves::REASONS[0],
                'note' => mb_substr(trim((string) ($x['note'] ?? '')), 0, 255),
            ];
        }
        if (!$items) {
            return $fail('Tick at least one item.');
        }
        // A Damaged/Faulty item needs a note saying what is wrong with it.
        $noNote = array_values(array_filter($items, fn ($i) => $i['cond'] === 'Damaged' && $i['note'] === ''));
        if ($noNote) {
            return $fail('Please write a note for each Damaged/Faulty item: ' . implode(', ', array_map(fn ($i) => \App\Support\Barcodes::text($i['code']), array_slice($noNote, 0, 5))) . (count($noNote) > 5 ? ' and more' : '') . '.');
        }
        if (count($items) > 500) {
            return $fail('One return can have 500 items at most. Split it into two returns.');
        }
        try {
            $id = Moves::createReturn(['customer_id' => $customerId, 'items' => $items, 'notes' => ''], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $no = DB::table('returns')->where('id', $id)->value('no');
        \App\Support\DcCopy::attach($request, 'returns', $id);
        return redirect()->route('return.index')->with('ok', 'Return ' . $no . ' saved. ' . count($items) . ' items back in the system.');
    }

    public function destroy(Request $request, int $id)
    {
        $no = (string) DB::table('returns')->where('id', $id)->value('no');
        abort_unless($no !== '', 404);
        try {
            Moves::deleteReturn($id, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('return.index')->with('bad', $e->getMessage());
        }
        return redirect()->route('return.index')->with('ok', 'Return ' . $no . ' deleted. Its items are back on rent with the customer.');
    }
}
