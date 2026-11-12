<?php

namespace App\Http\Controllers;

use App\Support\Pager;
use App\Support\Reports;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function tab(Request $request): int
    {
        $t = (int) $request->query('tab', 0);
        return $t >= 0 && $t < count(Reports::TABS) ? $t : 0;
    }

    public function index(Request $request)
    {
        $t = $this->tab($request);
        $n = Pager::size($request->query('n'));
        $data = Reports::data($t, $request->query());
        // Only one page of rows is drawn; "Download CSV" still has every row.
        $rows = Pager::ofArray($data['rows'], $n, $request);
        $first = ($rows->currentPage() - 1) * $n;   // position of the first drawn row, to find its id in $data['meta']
        return view('reports.index', ['tab' => $t, 'tabs' => Reports::TABS, 'data' => $data, 'rows' => $rows, 'n' => $n, 'first' => $first]);
    }

    public function csv(Request $request)
    {
        $data = Reports::data($this->tab($request), $request->query());
        return response(Reports::csv($data), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $data['name'] . '-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /* The items a customer has on rent, for the pop-up behind the count. */
    public function onRent(Request $request, int $id)
    {
        [$name, $items] = Reports::onRentItems($id, $request->query());
        abort_if($name === '', 404);
        return response()->json(['name' => $name, 'items' => $items]);
    }

    public function onRentExport(Request $request, int $id)
    {
        [$name, $items] = Reports::onRentItems($id, $request->query());
        abort_if($name === '', 404);
        $file = 'on-rent-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($name)), '-') . '-' . date('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($items, $name) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Customer', 'Barcode', 'Item', 'Category', 'Brand tag', 'Config', 'Challan', 'On rent since', 'Return by', 'Rent / month'], ',', '"', '');
            foreach ($items as $i) {
                fputcsv($h, [$name, $i['barcode'], $i['item'], $i['category'], $i['tag'], $i['config'], $i['challan'], $i['since'], $i['due'], $i['rent']], ',', '"', '');
            }
            fclose($h);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* What is inside one purchase, for the pop-up behind the Items count. */
    public function purchaseOne(int $id)
    {
        $lines = Reports::purchaseLines([$id]);
        abort_if(!$lines && !\Illuminate\Support\Facades\DB::table('purchases')->where('id', $id)->exists(), 404);
        $head = $lines[0] ?? null;
        return response()->json(['inv_no' => $head['inv_no'] ?? '', 'supplier' => $head['supplier'] ?? '', 'date' => $head['date'] ?? '', 'items' => $lines]);
    }

    /* One row per purchase line for every purchase the report shows (same filters). */
    public function purchaseLinesCsv(Request $request)
    {
        $one = (int) $request->query('id');   // one purchase (from its pop-up) or every purchase of the report
        $lines = Reports::purchaseLines($one ? [$one] : Reports::purchaseIds($request->query()));
        return response()->streamDownload(function () use ($lines) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Invoice date', 'Invoice no', 'Supplier', 'Item', 'Config', 'Qty', 'Rate', 'Amount', 'GST', 'Kind', 'Barcodes made'], ',', '"', '');
            foreach ($lines as $l) {
                fputcsv($h, [$l['date'], $l['inv_no'], $l['supplier'], $l['item'], $l['config'], $l['qty'], $l['rate'], $l['amount'], $l['gst'], $l['kind'], $l['barcodes']], ',', '"', '');
            }
            fclose($h);
        }, 'purchase-report-lines-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
