<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Moves;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Replacement: a faulty item comes back and a working one goes out in its place. */
class ReplacementController extends Controller
{
    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $prefix = Barcodes::prefix();
        $query = DB::table('replacements as r')->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->select('r.id', 'r.created_at', 'r.kind', 'r.old_code', 'r.new_code', 'r.cn', 'c.name as customer');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like, $prefix) {
                $w->where('c.name', 'like', $like)->orWhereRaw('concat(?, r.old_code) like ?', [$prefix, $like])->orWhereRaw('concat(?, r.new_code) like ?', [$prefix, $like])
                    ->orWhere('r.kind', 'like', $like)->orWhere('r.cn', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('r.id')->paginate($n)->withQueryString();
        return view('replacement.index', compact('rows', 'q', 'n', 'prefix'));
    }

    public function create()
    {
        return view('replacement.form', [
            'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name']),
            'kinds' => [Moves::KIND_RENTAL, Moves::KIND_SALE],
            'prefix' => Barcodes::prefix(),
            'dataUrl' => route('replacement.data'),
            'stockUrl' => route('sale.stock'),
        ]);
    }

    /*
     * JSON for the form. kind and customer_id give the items that can come back (faulty list).
     * old (a barcode number) gives the replacement choices: same model first, then the same category. Nothing outside that category is offered.
     */
    public function data(Request $request)
    {
        $prefix = Barcodes::prefix();
        $sold = $request->query('kind') === Moves::KIND_SALE;
        $cid = (int) $request->query('customer_id');
        $out = ['olds' => [], 'info' => null, 'same' => [], 'cat' => [], 'catTotal' => 0];
        if ($cid) {
            $out['olds'] = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.customer_id', $cid)->where('d.status', $sold ? 'sold' : 'rented')
                ->orderBy('d.code')->limit(1000)->get(['d.code', 'i.name as model'])
                ->map(fn ($r) => ['code' => (int) $r->code, 'label' => $prefix . $r->code . ' · ' . $r->model])->all();
        }
        $old = (int) $request->query('old');
        if ($cid && $old) {
            $od = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
                ->where('d.code', $old)->where('d.customer_id', $cid)->where('d.status', $sold ? 'sold' : 'rented')->first(['d.item_id', 'i.name', 'i.category_id', 'c.name as cat']);
            if ($od) {
                $out['info'] = ['model' => $od->name, 'cat' => (string) ($od->cat ?? ''), 'category_id' => (int) ($od->category_id ?? 0)];
                $shape = fn ($r) => ['code' => (int) $r->code, 'label' => $prefix . $r->code . ' · ' . $r->model . ' · ' . $r->config];
                $base = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.status', 'in_stock')->orderBy('d.code');
                $out['same'] = (clone $base)->where('d.item_id', $od->item_id)->limit(500)->get(['d.code', 'd.config', 'i.name as model'])->map($shape)->all();
                if ($od->category_id) {
                    $cq = (clone $base)->where('d.item_id', '!=', $od->item_id)->where('i.category_id', $od->category_id);
                    $out['catTotal'] = (clone $cq)->count();
                    $out['cat'] = $cq->limit(500)->get(['d.code', 'd.config', 'i.name as model'])->map($shape)->all();
                }
            }
        }
        return response()->json($out);
    }

    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        $customerId = (int) $request->input('customer_id');
        if (!$customerId || !DB::table('customers')->where('id', $customerId)->exists()) {
            return $fail('Please choose a customer.');
        }
        $kind = $request->input('kind') === Moves::KIND_SALE ? Moves::KIND_SALE : Moves::KIND_RENTAL;
        $old = (int) $request->input('old_code');
        $new = (int) $request->input('new_code');
        if (!$old || !$new) {
            return $fail('Choose the faulty item and its replacement.');
        }
        try {
            Moves::createReplacement([
                'kind' => $kind, 'customer_id' => $customerId, 'old_code' => $old, 'new_code' => $new,
                'reason' => mb_substr(trim((string) $request->input('reason', '')), 0, 255),
            ], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $cn = DB::table('replacements')->where('old_code', $old)->where('new_code', $new)->orderByDesc('id')->value('cn');
        return redirect()->route('replacement.index')->with('ok', 'Replacement saved.' . ($cn ? ' Credit note ' . $cn . ' created.' : ''));
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless(DB::table('replacements')->where('id', $id)->exists(), 404);
        try {
            Moves::deleteReplacement($id, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('replacement.index')->with('bad', $e->getMessage());
        }
        return redirect()->route('replacement.index')->with('ok', 'Replacement deleted. Both items are back where they were.');
    }
}
