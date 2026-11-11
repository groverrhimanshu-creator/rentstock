<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/* Customer logins: each customer gets a login to see what they have on rent. */
class LoginsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $n = \App\Support\Pager::size($request->query('n'));

        $query = DB::table('users as u')
            ->join('customers as c', 'c.id', '=', 'u.customer_id')
            ->where('u.role', 'customer')
            ->select('u.id', 'u.username', 'u.name', 'u.active', 'c.name as customer');
        $query->addSelect(DB::raw(Schema::hasTable('devices')
            ? "(select count(*) from devices d where d.customer_id = u.customer_id and d.status = 'rented') as on_rent"
            : '0 as on_rent'));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->orWhere('u.username', 'like', "%$q%")->orWhere('u.name', 'like', "%$q%")->orWhere('c.name', 'like', "%$q%");
            });
        }
        $rows = $query->orderBy('u.username')->paginate($n)->withQueryString();

        return view('logins.index', compact('rows', 'q', 'n'));
    }

    public function create()
    {
        return view('logins.form', ['customers' => DB::table('customers')->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:100'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'required' => 'Please fill in :attribute.',
            'username.regex' => 'The username must be 3 to 40 small letters, digits, dots, dashes or underscores.',
            'username.unique' => 'This username already exists.',
            'customer_id.required' => 'Please choose the customer.',
        ], ['username' => 'Username', 'name' => 'Contact name', 'customer_id' => 'Customer', 'password' => 'Password']);

        User::create($data + ['role' => 'customer', 'active' => true]);
        return redirect()->route('logins.index')->with('ok', 'Login created');
    }

    public function toggle(int $id)
    {
        $user = User::where('role', 'customer')->findOrFail($id);
        $user->update(['active' => !$user->active]);
        return redirect()->back();
    }

    public function edit(int $id)
    {
        $user = User::where('role', 'customer')->findOrFail($id);
        return view('logins.edit', ['u' => $user, 'customers' => DB::table('customers')->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::where('role', 'customer')->findOrFail($id);
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['required', 'string', 'max:100'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ], [
            'required' => 'Please fill in :attribute.',
            'username.regex' => 'The username must be 3 to 40 small letters, digits, dots, dashes or underscores.',
            'username.unique' => 'This username already exists.',
            'password.min' => 'The new password must have at least 8 characters.',
        ], ['username' => 'Username', 'name' => 'Contact name', 'customer_id' => 'Customer']);

        $fill = ['username' => $data['username'], 'name' => $data['name'], 'customer_id' => $data['customer_id'], 'active' => $request->boolean('active')];
        if (!empty($data['password'])) {
            $fill['password'] = $data['password'];   // hashed by the model
        }
        $user->update($fill);
        Audit::log('edit', 'customer login', $user->id, $user->username . (!empty($data['password']) ? ' (password changed)' : ''));
        return redirect()->route('logins.index')->with('ok', 'Login saved');
    }

    public function destroy(int $id)
    {
        $user = User::where('role', 'customer')->findOrFail($id);
        $name = $user->username;
        $user->delete();
        Audit::log('delete', 'customer login', $id, $name);
        return redirect()->route('logins.index')->with('ok', 'Login ' . $name . ' deleted');
    }
}
