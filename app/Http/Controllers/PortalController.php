<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Dues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* What a customer sees after logging in: what they have on rent, their documents and their returns. */
class PortalController extends Controller
{
    private function n(Request $request, string $key = 'n'): int
    {
        return \App\Support\Pager::size($request->query($key));
    }

    public function portal(Request $request)
    {
        $cid = (int) $request->user()->customer_id;
        $prefix = Barcodes::prefix();
        $n = $this->n($request);
        $q = trim((string) $request->query('q', ''));
        $base = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.status', 'rented')->where('d.customer_id', $cid);
        $count = (clone $base)->count();
        $byModel = (clone $base)->groupBy('i.name')->orderBy('i.name')->get(['i.name', DB::raw('count(*) as n')]);
        $past = (clone $base)->whereNotNull('d.return_by')->where('d.return_by', '<', date('Y-m-d'))->count();
        $list = clone $base;
        if ($q !== '') {
            foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                $like = '%' . $w . '%';
                $list->where(function ($x) use ($like, $prefix) {
                    $x->whereRaw('concat(?, d.code) like ?', [$prefix, $like])->orWhere('i.name', 'like', $like)->orWhere('d.config', 'like', $like);
                });
            }
        }
        $rows = $list->orderBy('d.code')->select('d.code', 'd.config', 'd.rented_at', 'd.return_by', 'd.rent_no', 'i.name as model')->paginate($n)->withQueryString();
        $dues = Dues::all($cid);
        return view('portal.rentals', [
            'name' => (string) DB::table('customers')->where('id', $cid)->value('name'),
            'count' => $count, 'byModel' => $byModel, 'past' => $past, 'rows' => $rows, 'q' => $q, 'n' => $n, 'prefix' => $prefix,
            'dues' => $dues, 'dueTotal' => array_sum(array_map(fn ($x) => $x->bal, $dues)),
        ]);
    }

    public function invoices(Request $request)
    {
        $cid = (int) $request->user()->customer_id;
        $n1 = $this->n($request, 'n1');
        $n2 = $this->n($request, 'n2');
        $q1 = trim((string) $request->query('q1', ''));
        $q2 = trim((string) $request->query('q2', ''));
        $a = DB::table('rentals as r')->where('r.customer_id', $cid)->select('r.id', 'r.no', 'r.kind', 'r.created_at')
            ->selectRaw('(select count(*) from rental_lines l where l.rental_id = r.id) as items');
        if ($q1 !== '') {
            $a->where('r.no', 'like', '%' . $q1 . '%');
        }
        $b = DB::table('sales as s')->where('s.customer_id', $cid)->select('s.id', 's.no', 's.created_at')
            ->selectRaw('(select count(*) from sale_lines l where l.sale_id = s.id) as items');
        if ($q2 !== '') {
            $b->where('s.no', 'like', '%' . $q2 . '%');
        }
        return view('portal.invoices', [
            'rentals' => $a->orderByDesc('r.id')->paginate($n1, ['*'], 'p1')->withQueryString(),
            'sales' => $b->orderByDesc('s.id')->paginate($n2, ['*'], 'p2')->withQueryString(),
            'n1' => $n1, 'n2' => $n2, 'q1' => $q1, 'q2' => $q2,
        ]);
    }

    public function returns(Request $request)
    {
        $cid = (int) $request->user()->customer_id;
        $n = $this->n($request);
        $q = trim((string) $request->query('q', ''));
        $query = DB::table('returns as r')->where('r.customer_id', $cid)->select('r.id', 'r.no', 'r.created_at', 'r.reason')
            ->selectRaw('(select count(*) from return_lines l where l.return_id = r.id) as items');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('r.no', 'like', $like)->orWhere('r.reason', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('r.id')->paginate($n)->withQueryString();
        return view('portal.returns', compact('rows', 'q', 'n'));
    }
}
