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
        $data = Reports::data($t);
        // Only one page of rows is drawn; "Download CSV" still has every row.
        $rows = Pager::ofArray($data['rows'], $n, $request);
        return view('reports.index', ['tab' => $t, 'tabs' => Reports::TABS, 'data' => $data, 'rows' => $rows, 'n' => $n]);
    }

    public function csv(Request $request)
    {
        $data = Reports::data($this->tab($request));
        return response(Reports::csv($data), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $data['name'] . '-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
