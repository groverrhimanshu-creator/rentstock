<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Barcodes;
use App\Support\Deps;
use App\Support\Masters;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/* One controller for every master: brand, category, unit, store, customer, supplier, item and bank. */
class MasterController extends Controller
{
    public function index(Request $request, string $key)
    {
        $m = Masters::def($key);
        $q = trim((string) $request->query('q', ''));
        $n = \App\Support\Pager::size($request->query('n'));

        $query = DB::table($m['alias'] ? $m['table'] . ' as ' . $m['alias'] : $m['table']);
        foreach ($m['joins'] as [$table, $a, $b]) {
            $query->leftJoin($table, $a, '=', $b);
        }
        $query->select($m['select']);
        foreach ($m['extra'] as $sql) {
            $query->addSelect(DB::raw($sql));
        }
        if ($m['count']) {
            // Counts that come from the stock tables. They show 0 until those tables exist.
            $alias = $m['count'][0];
            $sql = $m['count'][1];
            $always = !empty($m['count'][2]);   // counts that only need the items table, not the stock tables
            $query->addSelect(DB::raw(($always || Schema::hasTable('devices')) ? $sql . ' as ' . $alias : '0 as ' . $alias));
        }
        if ($q !== '') {
            $query->where(function ($w) use ($m, $q) {
                foreach ($m['search'] as $col) {
                    $w->orWhere($col, 'like', '%' . $q . '%');
                }
            });
        }
        $rows = $query->orderBy($m['order'])->paginate($n)->withQueryString();

        return view('masters.index', compact('m', 'key', 'rows', 'q', 'n'));
    }

    /* Items that use a brand or a category: shown in a pop-up on the list and exported from there. */
    private function assocRows(string $key, int $id): array
    {
        if (!in_array($key, ['brand', 'category'], true)) {
            abort(404);
        }
        $m = Masters::def($key);
        $row = DB::table($m['table'])->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }
        $col = $key === 'brand' ? 'i.brand_id' : 'i.category_id';
        $items = DB::table('items as i')
            ->leftJoin('brands as b', 'b.id', '=', 'i.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where($col, $id)
            ->orderBy('i.name')
            ->get(['i.id', 'i.name', 'b.name as brand', 'c.name as category', 'i.hsn', 'i.rent_per_month', 'i.sale_price', 'i.tracked']);
        $ids = $items->pluck('id');
        $pieces = DB::table('devices as d')->leftJoin('customers as cu', 'cu.id', '=', 'd.customer_id')
            ->whereIn('d.item_id', $ids)->orderBy('d.code')
            ->get(['d.code', 'd.item_id', 'd.brand_tag', 'd.config', 'd.status', 'cu.name as customer'])->groupBy('item_id');
        $qty = DB::table('item_qty')->whereIn('item_id', $ids)->pluck('qty', 'item_id');
        $label = ['in_stock' => 'In stock', 'rented' => 'On rent', 'repair' => 'In repair', 'sold' => 'Sold'];
        $out = [];
        foreach ($items as $i) {
            $base = [
                'name' => $i->name, 'brand' => (string) $i->brand, 'category' => (string) $i->category, 'hsn' => (string) $i->hsn,
                'rent' => number_format((float) $i->rent_per_month, 2, '.', ''), 'sale' => number_format((float) $i->sale_price, 2, '.', ''),
                'kind' => $i->tracked ? 'Barcoded' : 'By quantity',
            ];
            if (!$i->tracked) {
                $out[] = $base + ['barcode' => '', 'tag' => '', 'config' => '', 'status' => (int) ($qty[$i->id] ?? 0) . ' in stock', 'with' => ''];
            } elseif (empty($pieces[$i->id])) {
                $out[] = $base + ['barcode' => '', 'tag' => '', 'config' => '', 'status' => 'No pieces yet', 'with' => ''];
            } else {
                foreach ($pieces[$i->id] as $p) {
                    $out[] = $base + ['barcode' => Barcodes::text((int) $p->code), 'tag' => (string) $p->brand_tag, 'config' => (string) $p->config,
                        'status' => $label[$p->status] ?? $p->status, 'with' => (string) $p->customer];
                }
            }
        }
        return [$m['title'], (string) ($row->name ?? ''), $out];
    }

    public function assoc(string $key, int $id)
    {
        [$title, $name, $items] = $this->assocRows($key, $id);
        return response()->json(['title' => $title, 'name' => $name, 'items' => $items]);
    }

    /* The whole Item list as it is filtered on screen (same search), as a CSV. */
    public function itemsExport(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $m = Masters::def('item');
        $query = DB::table('items as i')->leftJoin('brands as b', 'b.id', '=', 'i.brand_id')->leftJoin('categories as c', 'c.id', '=', 'i.category_id');
        if ($q !== '') {
            $query->where(function ($w) use ($m, $q) {
                foreach ($m['search'] as $col) {
                    $w->orWhere($col, 'like', '%' . $q . '%');
                }
            });
        }
        $query->orderBy('i.name')->select('i.name', 'b.name as brand', 'c.name as category', 'i.hsn', 'i.gst_percent', 'i.rent_per_month', 'i.sale_price', 'i.tracked', 'i.maintain_stock');
        return response()->streamDownload(function () use ($query) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Item', 'Brand', 'Category', 'HSN', 'GST %', 'Rent / month', 'Sale price', 'Kind', 'Maintain stock'], ',', '"', '');
            foreach ($query->cursor() as $i) {
                fputcsv($h, [$i->name, $i->brand, $i->category, $i->hsn, $i->gst_percent, $i->rent_per_month, $i->sale_price,
                    $i->tracked ? 'Barcoded' : 'By quantity', $i->tracked || $i->maintain_stock ? 'Yes' : 'No'], ',', '"', '');
            }
            fclose($h);
        }, 'items-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function assocExport(string $key, int $id)
    {
        [$title, $name, $items] = $this->assocRows($key, $id);
        $file = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($title . '-' . $name)) . '-items-' . date('Y-m-d') . '.csv';
        return response()->streamDownload(function () use ($items) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");   // so Excel reads it as UTF-8
            fputcsv($h, ['Item', 'Brand', 'Category', 'Barcode', 'Brand tag', 'Config', 'Status', 'With customer', 'HSN', 'Rent / month', 'Sale price', 'Kind'], ',', '"', '');
            foreach ($items as $i) {
                fputcsv($h, [$i['name'], $i['brand'], $i['category'], $i['barcode'], $i['tag'], $i['config'], $i['status'], $i['with'], $i['hsn'], $i['rent'], $i['sale'], $i['kind']], ',', '"', '');
            }
            fclose($h);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(string $key)
    {
        $m = Masters::def($key);
        $values = [];
        foreach ($m['fields'] as $f) {
            $values[$f['name']] = $f['default'] ?? '';
        }
        return $this->form($key, $m, $values, 0);
    }

    public function edit(string $key, int $id)
    {
        $m = Masters::def($key);
        $row = DB::table($m['table'])->where('id', $id)->first();
        if (!$row) {
            return redirect()->route('masters.index', $key)->with('bad', 'That record was not found.');
        }
        return $this->form($key, $m, (array) $row, $id);
    }

    public function store(Request $request, string $key)
    {
        return $this->save($request, $key, 0);
    }

    public function update(Request $request, string $key, int $id)
    {
        return $this->save($request, $key, $id);
    }

    public function destroy(string $key, int $id)
    {
        $m = Masters::def($key);
        $row = DB::table($m['table'])->where('id', $id)->first();
        if (!$row) {
            return redirect()->route('masters.index', $key)->with('bad', 'That record was not found.');
        }
        if ($msg = Deps::master($key, $id)) {
            return redirect()->route('masters.edit', [$key, $id])->with('bad', $msg);
        }
        $label = (string) ($row->name ?? $row->account_no ?? $id);
        try {
            DB::transaction(function () use ($key, $m, $id, $label) {
                if ($key === 'item') {
                    DB::table('item_qty')->where('item_id', $id)->delete();   // a zero count row, if any
                }
                DB::table($m['table'])->where('id', $id)->delete();
                Audit::log('delete', strtolower($m['title']), $id, $label);
            });
        } catch (QueryException $e) {
            // 1451 = something still points at this record (a safety net behind the checks above).
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                return redirect()->route('masters.edit', [$key, $id])->with('bad', 'This ' . strtolower($m['title']) . ' is still used somewhere else, so it cannot be deleted.');
            }
            throw $e;
        }
        return redirect()->route('masters.index', $key)->with('ok', $m['title'] . ' deleted');
    }

    private function form(string $key, array $m, array $values, int $id)
    {
        $lookups = [];
        foreach ($m['fields'] as $f) {
            if ($f['type'] === 'lookup') {
                $col = Masters::lookupColumn($f['from']);
                $lookups[$f['name']] = DB::table($f['from'])->orderBy($col)->get(['id', $col . ' as name']);
            }
        }
        $blocked = $id ? Deps::master($key, $id) : null;   // shown as an alert if the person presses Delete
        // For a customer: the login it already has, if any (the form offers to create one when there is none).
        $login = ($key === 'customer' && $id) ? DB::table('users')->where('role', 'customer')->where('customer_id', $id)->first(['id', 'username']) : null;
        return view('masters.form', compact('m', 'key', 'values', 'id', 'lookups', 'blocked', 'login'));
    }

    private function save(Request $request, string $key, int $id)
    {
        $m = Masters::def($key);
        $vals = [];
        $rules = [];
        $labels = [];
        foreach ($m['fields'] as $f) {
            $name = $f['name'];
            $v = trim((string) $request->input($name, ''));
            if ($v === '' && $f['type'] === 'select') {
                $v = (string) ($f['default'] ?? '');
            }
            if (!empty($f['upper'])) {
                $v = strtoupper($v);
            }
            $vals[$name] = $v;
            $labels[$name] = $f['label'];
            $rules[$name] = $this->rulesFor($f, $m['table'], $id);
        }

        Validator::make($vals, $rules, [
            'required' => 'Please fill in :attribute.',
            'unique' => 'This :attribute already exists.',
        ], $labels)->validate();

        // Customer only: "Create a login for this customer" (username and password), saved together with the customer.
        $login = null;
        if ($key === 'customer' && $request->boolean('make_login')) {
            $request->merge(['login_username' => strtolower(trim((string) $request->input('login_username')))]);
            $login = $request->validate([
                'login_username' => ['required', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')],
                'login_password' => ['required', 'string', 'min:8', 'max:100'],
            ], [
                'required' => 'Please fill in :attribute.',
                'login_username.regex' => 'The login username must be 3 to 40 small letters, digits, dots, dashes or underscores.',
                'login_username.unique' => 'This login username already exists.',
                'login_password.min' => 'The login password must have at least 8 characters.',
            ], ['login_username' => 'Login username', 'login_password' => 'Login password']);
        }

        foreach ($m['fields'] as $f) {
            $name = $f['name'];
            if ($f['type'] === 'lookup') {
                $vals[$name] = $vals[$name] === '' ? null : (int) $vals[$name];
            } elseif ($f['type'] === 'yesno') {
                $vals[$name] = (int) $vals[$name];
            }
        }
        if (array_key_exists('state', $vals)) {
            $vals['state_code'] = $vals['state'] !== '' ? Masters::states()[$vals['state']] : '';
        }
        if ($key === 'item' && !$id) {
            // The prototype's item form has no unit box. New items start with the unit "Nos".
            $vals['unit_id'] = DB::table('units')->where('name', 'Nos')->value('id');
        }

        try {
            DB::transaction(function () use ($m, $id, $vals, $login, $key) {
                $cid = $id;
                if ($id) {
                    DB::table($m['table'])->where('id', $id)->update($vals);
                } elseif ($key === 'customer') {
                    $cid = DB::table($m['table'])->insertGetId($vals);
                } else {
                    DB::table($m['table'])->insert($vals);
                }
                if ($login && $key === 'customer' && !DB::table('users')->where('role', 'customer')->where('customer_id', $cid)->exists()) {
                    User::create([
                        'username' => $login['login_username'],
                        'name' => $vals['contact'] !== '' ? $vals['contact'] : $vals['name'],
                        'customer_id' => $cid,
                        'password' => $login['login_password'],   // hashed by the model
                        'role' => 'customer',
                        'active' => true,
                    ]);
                    Audit::log('add', 'customer login', $cid, $login['login_username']);
                }
            });
        } catch (QueryException $e) {
            // 1062 = duplicate value in a unique column (two people saving the same name at once).
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return back()->withInput($request->except(['login_password']))->withErrors(['name' => 'This ' . strtolower($m['title']) . ' already exists.']);
            }
            throw $e;
        }

        return redirect()->route('masters.index', $key)->with('ok', $m['title'] . ' saved' . ($login ? ' and login created' : ''));
    }

    private function rulesFor(array $f, string $table, int $id): array
    {
        $rules = [];
        $type = $f['type'];
        $rules[] = (!empty($f['req']) || $type === 'number') ? 'required' : 'nullable';

        if ($type === 'text') {
            $rules[] = 'string';
            $rules[] = 'max:' . $f['max'];
        } elseif ($type === 'number') {
            $rules[] = 'numeric';
            $rules[] = 'min:' . ($f['min'] ?? 0);
            $rules[] = 'max:' . ($f['max'] ?? 99999999);
        } elseif ($type === 'select') {
            $rules[] = Rule::in($f['options']);
        } elseif ($type === 'state') {
            $rules[] = Rule::in(array_keys(Masters::states()));
        } elseif ($type === 'yesno') {
            $rules[] = Rule::in(['0', '1']);
        } elseif ($type === 'lookup') {
            $rules[] = 'integer';
            $rules[] = Rule::exists($f['from'], 'id');
        }

        if (!empty($f['check'])) {
            $kind = $f['check'];
            $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($kind) {
                if ($msg = Masters::check($kind, (string) $value)) {
                    $fail(':attribute ' . $msg . '.');
                }
            };
        }
        if (!empty($f['unique'])) {
            $rules[] = Rule::unique($table, $f['name'])->ignore($id ?: null);
        }
        return $rules;
    }
}
