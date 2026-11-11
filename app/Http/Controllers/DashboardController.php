<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke()
    {
        // The stock tables arrive in the next step. Until then every count is 0.
        $c = ['in_stock' => 0, 'rented' => 0, 'repair' => 0, 'overdue' => 0, 'qty' => 0, 'pay_overdue' => 0];
        if (Schema::hasTable('devices')) {
            // One query for all four counts instead of four.
            $n = DB::table('devices')->selectRaw("sum(status = 'in_stock') as a, sum(status = 'rented') as b, sum(status = 'repair') as r, sum(status = 'rented' and return_by is not null and return_by < ?) as o", [now()->toDateString()])->first();
            $c['in_stock'] = (int) $n->a;
            $c['rented'] = (int) $n->b;
            $c['repair'] = (int) $n->r;
            $c['overdue'] = (int) $n->o;
        }
        $due = collect();
        if (Schema::hasTable('devices')) {
            $c['qty'] = (int) DB::table('item_qty')->sum('qty');
            $prefix = Barcodes::prefix();
            $due = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('customers as cu', 'cu.id', '=', 'd.customer_id')
                ->where('d.status', 'rented')->whereNotNull('d.return_by')->orderBy('d.return_by')->limit(5)
                ->get(['d.code', 'd.return_by', 'i.name as model', 'cu.name as customer'])
                ->each(fn ($r) => $r->bc = $prefix . $r->code);
        }
        $latest = collect();
        if (Schema::hasTable('rentals')) {
            $c['pay_overdue'] = count(array_filter(\App\Support\Dues::all(), fn ($x) => $x->late > 0));
            $latest = DB::table('rentals as r')->join('customers as cu', 'cu.id', '=', 'r.customer_id')->orderByDesc('r.id')->limit(5)
                ->select('r.id', 'r.no', 'r.created_at', 'cu.name as customer')
                ->selectRaw('(select count(*) from rental_lines l where l.rental_id = r.id) as items')->get();
        }
        return view('dashboard', ['c' => $c, 'due' => $due, 'latest' => $latest]);
    }
}
