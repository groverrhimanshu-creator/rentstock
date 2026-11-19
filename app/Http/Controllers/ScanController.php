<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* Scan a barcode with the phone camera (or type it, or use a Bluetooth scanner) and see the piece. Read-only. */
class ScanController extends Controller
{
    public function index()
    {
        return view('scan.index');
    }

    public function lookup(Request $request)
    {
        $code = Barcodes::parse((string) $request->input('code', ''));
        if (!$code) {
            return response()->json(['ok' => false, 'msg' => 'This is not a barcode of this system.']);
        }
        $d = DB::table('devices as d')
            ->join('items as i', 'i.id', '=', 'd.item_id')
            ->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->leftJoin('stores as s', 's.id', '=', 'd.store_id')
            ->where('d.code', $code)
            ->first(['d.code', 'd.status', 'd.config', 'd.brand_tag', 'd.rent_no', 'i.name as model', 'c.name as customer', 's.name as store']);
        if (!$d) {
            return response()->json(['ok' => false, 'msg' => Barcodes::text($code) . ' was not found.']);
        }
        $st = Stock::STATUS[$d->status] ?? [$d->status, 'idle'];
        return response()->json(['ok' => true, 'item' => [
            'text' => Barcodes::text($code), 'model' => $d->model, 'status' => $st[0], 'tone' => $st[1],
            'customer' => $d->customer, 'rent_no' => $d->rent_no, 'store' => $d->store, 'config' => $d->config, 'tag' => $d->brand_tag,
            'url' => route('stock.device', $code),
        ]]);
    }
}
