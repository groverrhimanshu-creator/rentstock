<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Moves;
use App\Support\RentMath;
use App\Support\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* Upgrade Assets: RAM or SSD is upgraded, or a part (battery, keyboard...) is replaced. The device keeps its barcode. */
class ConsumptionController extends Controller
{
    public function index(Request $request)
    {
        $n = \App\Support\Pager::size($request->query('n'));
        $q = trim((string) $request->query('q', ''));
        $prefix = Barcodes::prefix();
        $query = DB::table('consumptions as c')->select('c.*');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like, $prefix) {
                $w->whereRaw('concat(?, c.code) like ?', [$prefix, $like])->orWhere('c.model', 'like', $like)->orWhere('c.part', 'like', $like)
                    ->orWhere('c.to_val', 'like', $like)->orWhere('c.new_cfg', 'like', $like);
            });
        }
        $rows = $query->orderByDesc('c.id')->paginate($n)->withQueryString();
        return view('consumption.index', compact('rows', 'q', 'n', 'prefix'));
    }

    /* The upgrade log as a CSV file (all rows, or the rows that match the search). */
    public function export(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $prefix = Barcodes::prefix();
        $query = DB::table('consumptions as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->select('c.*', 'u.name as by_user');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like, $prefix) {
                $w->whereRaw('concat(?, c.code) like ?', [$prefix, $like])->orWhere('c.model', 'like', $like)->orWhere('c.part', 'like', $like)
                    ->orWhere('c.to_val', 'like', $like)->orWhere('c.new_cfg', 'like', $like);
            });
        }
        $name = 'upgrade-log-' . date('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($query, $prefix) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Date', 'Barcode', 'Device', 'Type', 'What changed', 'Config before', 'Config after', 'Part from stock', 'By'], ',', '"', '');
            foreach ($query->orderByDesc('c.id')->cursor() as $c) {
                fputcsv($h, [\Carbon\Carbon::parse($c->created_at)->format('d/m/Y H:i'), $prefix . $c->code, $c->model, $c->part, $c->from_val !== '' ? $c->from_val . ' to ' . $c->to_val : $c->to_val, $c->old_cfg, $c->new_cfg, $c->used_name, (string) $c->by_user], ',', '"', '');
            }
            fclose($h);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('consumption.form', [
            'lookupUrl' => route('consumption.lookup'),
            'prefix' => Barcodes::prefix(),
            'types' => array_map(fn ($p) => ['key' => $p, 'label' => RentMath::partLabel($p)], RentMath::PART_TYPES),
        ]);
    }

    /* Looks up one device by barcode and lists the parts that are in stock (JSON for the form). */
    public function lookup(Request $request)
    {
        $code = Barcodes::parse((string) $request->query('code'));
        $d = $code ? DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->where('d.code', $code)->first(['d.code', 'd.config', 'd.status', 'i.name as model', 'c.name as customer']) : null;
        if (!$d) {
            return response()->json(['found' => false]);
        }
        return response()->json([
            'found' => true,
            'device' => ['code' => (int) $d->code, 'bc' => Barcodes::text((int) $d->code), 'model' => $d->model, 'config' => $d->config, 'status' => $d->status,
                'status_label' => Stock::STATUS[$d->status][0] ?? $d->status, 'customer' => (string) ($d->customer ?? '')],
            'parts' => Moves::partsInStock(),
        ]);
    }

    public function store(Request $request)
    {
        $fail = fn (string $m) => back()->withInput()->withErrors(['form' => $m]);
        $code = Barcodes::parse((string) $request->input('code'));
        if (!$code) {
            return $fail('Find the device first.');
        }
        $ups = [];
        foreach ((array) $request->input('ups', []) as $u) {
            $ups[] = ['part' => (string) ($u['part'] ?? ''), 'item_id' => (int) ($u['item_id'] ?? 0), 'text' => (string) ($u['text'] ?? '')];
        }
        try {
            Moves::createUpgrade(['code' => $code, 'ups' => $ups], $request->user()->id);
        } catch (RuntimeException $e) {
            return $fail($e->getMessage());
        }
        $cfg = (string) DB::table('devices')->where('code', $code)->value('config');
        if ($request->boolean('print')) {
            return redirect()->route('barcodes.label', ['code' => $code, 'saved' => 1, 'back' => route('consumption.index')]);
        }
        return redirect()->route('consumption.index')->with('ok', 'Upgrade saved. ' . Barcodes::text($code) . ' is now ' . $cfg);
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless(DB::table('consumptions')->where('id', $id)->exists(), 404);
        try {
            Moves::deleteConsumption($id, $request->user()->id);
        } catch (RuntimeException $e) {
            return redirect()->route('consumption.index')->with('bad', $e->getMessage());
        }
        return redirect()->route('consumption.index')->with('ok', 'Upgrade deleted. The old config is back and any part taken from stock is back in stock.');
    }
}
