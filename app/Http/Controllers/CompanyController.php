<?php

namespace App\Http\Controllers;

use App\Support\Barcodes;
use App\Support\Masters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* "These details print on every invoice and challan." */
class CompanyController extends Controller
{
    public function edit()
    {
        return view('company', ['c' => (array) DB::table('company_profile')->where('id', 1)->first()]);
    }

    public function update(Request $request)
    {
        $request->merge([
            'gstin' => strtoupper(trim((string) $request->input('gstin'))),
            'pan' => strtoupper(trim((string) $request->input('pan'))),
            'ifsc' => strtoupper(trim((string) $request->input('ifsc'))),
            'prefix' => strtoupper(trim((string) $request->input('prefix'))),
        ]);

        $check = fn (string $kind, string $label) => function (string $a, mixed $v, \Closure $fail) use ($kind, $label) {
            if ($msg = Masters::check($kind, (string) $v)) {
                $fail($label . ' ' . $msg . '.');
            }
        };

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', $check('gstin', 'GSTIN')],
            'pan' => ['nullable', $check('pan', 'PAN')],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'line1' => ['nullable', 'string', 'max:200'],
            'line2' => ['nullable', 'string', 'max:200'],
            'bank' => ['nullable', 'string', 'max:100'],
            'acc' => ['nullable', 'string', 'max:30'],
            'ifsc' => ['nullable', $check('ifsc', 'IFSC')],
            'prefix' => ['nullable', 'regex:/^[A-Z0-9]{1,10}$/'],
        ], [
            'required' => 'Please fill in :attribute.',
            'prefix.regex' => 'The document prefix can have only capital letters and digits, up to 10.',
        ], ['name' => 'Company name', 'prefix' => 'Document prefix']);

        // Empty boxes arrive as null; the columns want an empty string.
        $data = array_map(fn ($v) => $v ?? '', $data);
        if ($data['prefix'] === '') {
            $data['prefix'] = 'KPI';
        }
        // The first two digits of a GSTIN are the state code.
        if (preg_match('/^[0-9]{2}/', $data['gstin'], $mm)) {
            $data['state_code'] = $mm[0];
        }
        $data['updated_at'] = now();

        DB::table('company_profile')->where('id', 1)->update($data);
        Barcodes::forgetPrefix();

        return redirect()->route('company.edit')->with('ok', 'Company profile saved');
    }
}
