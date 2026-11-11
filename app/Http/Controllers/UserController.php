<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/* Logins for admin, staff and customers. */
class UserController extends Controller
{
    public function index()
    {
        $users = DB::table('users as u')
            ->leftJoin('customers as c', 'c.id', '=', 'u.customer_id')
            ->select('u.*', 'c.name as customer')
            ->orderBy('u.username')
            ->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return $this->form(new User(['role' => 'staff', 'active' => true]));
    }

    public function edit(int $id)
    {
        return $this->form(User::findOrFail($id));
    }

    public function store(Request $request)
    {
        User::create($this->validated($request, null));
        return redirect()->route('users.index')->with('ok', 'Login created.');
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id) {
            // You cannot lock yourself out.
            $data['role'] = 'admin';
            $data['active'] = true;
            $data['customer_id'] = null;
        }
        $user->update($data);
        return redirect()->route('users.index')->with('ok', 'Login saved.');
    }

    private function form(User $user)
    {
        $customers = DB::table('customers')->orderBy('name')->get(['id', 'name']);
        return view('users.form', compact('user', 'customers'));
    }

    private function validated(Request $request, ?User $user): array
    {
        $id = $user?->id;
        $v = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:/^[A-Za-z0-9._-]{3,40}$/', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(['admin', 'staff', 'customer'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ], [
            'username.regex' => 'The username must be 3 to 40 letters, digits, dots, dashes or underscores.',
        ]);

        if ($v['role'] === 'customer' && empty($v['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'Choose which customer this login belongs to.']);
        }
        if ($v['role'] !== 'customer') {
            $v['customer_id'] = null;
        }
        $v['email'] = $v['email'] ?: null;
        $v['active'] = $request->boolean('active');
        if (empty($v['password'])) {
            unset($v['password']);
        }
        return $v;
    }
}
