<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* Print barcodes: labels for stickers (50 x 25 mm) and the history of one barcode. */
class BarcodeController extends Controller
{
    /* Adds the words of a search to a query on devices (d), items (i), brands (b), categories (ct) and customers (cu). */
    private function search($q, string $text)
    {
        $prefix = Barcodes::prefix();
        foreach (preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $like = '%' . $w . '%';
            $q->where(function ($x) use ($like, $prefix) {
                $x->whereRaw('concat(?, d.code) like ?', [$prefix, $like])->orWhere('i.name', 'like', $like)->orWhere('b.name', 'like', $like)
                    ->orWhere('ct.name', 'like', $like)->orWhere('d.config', 'like', $like)->orWhere('d.brand_tag', 'like', $like)->orWhere('cu.name', 'like', $like)
                    ->orWhereRaw("(case d.status when 'in_stock' then 'In stock' when 'rented' then 'On rent' when 'repair' then 'In repair' else 'Sold' end) like ?", [$like]);
            });
        }
        return $q;
    }

    private function base()
    {
        return DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->leftJoin('brands as b', 'b.id', '=', 'i.brand_id')
            ->leftJoin('categories as ct', 'ct.id', '=', 'i.category_id')->leftJoin('customers as cu', 'cu.id', '=', 'd.customer_id');
    }

    public function index(Request $request)
    {
        $tab = (int) $request->query('tab', 0) === 1 ? 1 : 0;
        $q = trim((string) $request->query('q', ''));
        $data = ['tab' => $tab, 'q' => $q, 'prefix' => Barcodes::prefix()];
        $cols = ['d.code', 'd.config', 'd.status', 'd.brand_tag', 'i.name as model', 'cu.name as customer'];

        if ($tab === 0) {
            $item = (int) $request->query('item', 0);
            $list = $this->search($this->base()->where('d.status', 'in_stock'), $q);
            if ($item) {
                $list->where('d.item_id', $item);
            }
            $data['item'] = $item;
            $data['total'] = (clone $list)->count();
            $data['rows'] = $list->orderBy('d.code')->limit(40)->get($cols);
            $data['items'] = DB::table('items')->where('tracked', 1)->orderBy('name')->get(['id', 'name']);
            return view('barcodes.index', $data);
        }

        $code = $request->filled('one') ? (int) $request->query('one') : Barcodes::parse($q);
        $one = $code ? $this->base()->where('d.code', $code)->first($cols) : null;
        $data['one'] = $one;
        if ($one) {
            $data['history'] = DB::table('device_history')->where('code', $one->code)->orderByDesc('id')->get(['at', 'text']);
            $data['cfglog'] = \App\Support\ConfigLog::for((int) $one->code);
        } elseif (mb_strlen($q) >= 2) {
            $list = $this->search($this->base(), $q);
            $data['total'] = (clone $list)->count();
            $data['rows'] = $list->orderBy('d.code')->limit(50)->get($cols);
        }
        return view('barcodes.index', $data);
    }

    /* Labels for the ticked barcodes, ready to print. */
    public function labels(Request $request)
    {
        $codes = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('codes', [])))));
        if (!$codes) {
            return redirect()->route('barcodes.index')->with('bad', 'Tick at least one item to make labels.');
        }
        if (count($codes) > 500) {
            return redirect()->route('barcodes.index')->with('bad', 'Make at most 500 labels at a time.');
        }
        return $this->showLabels($codes, false, '', false);
    }

    /*
     * One label, opened after a config is changed (item edit or upgrade) so the new sticker can be printed.
     * The label also shows the config. "back" is a page of this site to return to; anything else is ignored.
     */
    public function label(Request $request, int $code)
    {
        $back = (string) $request->query('back', '');
        $back = ($back !== '' && str_starts_with($back, url('/'))) ? $back : '';
        return $this->showLabels([$code], true, $back, $request->boolean('saved'));
    }

    private function showLabels(array $codes, bool $withConfig, string $back, bool $saved)
    {
        $rows = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->whereIn('d.code', $codes)->get(['d.code', 'd.brand_tag', 'd.config', 'i.name as model'])->keyBy('code');
        $labels = [];
        foreach ($codes as $c) {
            if (isset($rows[$c])) {
                $labels[] = $rows[$c];
            }
        }
        if (!$labels) {
            return redirect()->route('barcodes.index')->with('bad', 'None of those barcodes were found.');
        }
        $w = max(30.0, (float) config('rentstock.label.w', 50));
        $h = max(18.0, (float) config('rentstock.label.h', 25));
        return view('barcodes.labels', ['w' => $w, 'h' => $h, 's' => round(min($w / 50, $h / 25), 2), 'title' => trim((string) config('rentstock.label.title', '')), 'labels' => $labels, 'prefix' => Barcodes::prefix(), 'withConfig' => $withConfig, 'back' => $back, 'saved' => $saved]);
    }
}
