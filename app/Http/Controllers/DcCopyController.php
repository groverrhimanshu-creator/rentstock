<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\DcCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/* Open, add, replace or remove the DC copy of a sale, rental, return or replacement. */
class DcCopyController extends Controller
{
    private function table(string $kind): string
    {
        abort_unless(isset(DcCopy::KINDS[$kind]), 404);
        return DcCopy::KINDS[$kind];
    }

    public function show(string $kind, int $id)
    {
        $t = $this->table($kind);
        $r = DB::table($t)->where('id', $id)->first(['dc_file', 'dc_file_name']);
        abort_unless($r && $r->dc_file && Storage::exists($r->dc_file), 404);
        return Storage::response($r->dc_file, $r->dc_file_name ?: basename($r->dc_file));
    }

    public function upload(Request $request, string $kind, int $id)
    {
        $t = $this->table($kind);
        abort_unless(DB::table($t)->where('id', $id)->exists(), 404);
        if (!$request->hasFile('dc_file')) {
            return back()->with('bad', 'Choose the DC copy file first.');
        }
        if (DcCopy::bad($request)) {
            return back()->with('bad', DcCopy::MSG);
        }
        DcCopy::attach($request, $t, $id);
        Audit::log('edit', $kind, (string) $id, 'DC copy uploaded');
        return back()->with('ok', 'DC copy saved.');
    }

    public function destroy(string $kind, int $id)
    {
        $t = $this->table($kind);
        abort_unless(DB::table($t)->where('id', $id)->exists(), 404);
        DcCopy::remove($t, $id);
        Audit::log('edit', $kind, (string) $id, 'DC copy removed');
        return back()->with('ok', 'DC copy removed.');
    }
}
