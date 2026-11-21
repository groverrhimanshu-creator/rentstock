<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* Staff logins (Admin and SubAdmin) and what each SubAdmin may do. Admin only. Customer logins are on their own page. */
class UsersController extends Controller
{
    private function staff(int $id): User
    {
        return User::whereIn('role', ['admin', 'subadmin'])->findOrFail($id);
    }

    /* The last active Admin can never be switched off, demoted or deleted, so the system always has somebody who can sign in and manage it. */
    private function lastAdmin(User $u): bool
    {
        return $u->role === 'admin' && $u->active && User::where('role', 'admin')->where('active', true)->where('id', '!=', $u->id)->doesntExist();
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $n = \App\Support\Pager::size($request->query('n'));
        $query = User::whereIn('role', ['admin', 'subadmin']);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->orWhere('username', 'like', "%$q%")->orWhere('name', 'like', "%$q%");
            });
        }
        $rows = $query->orderByRaw("role = 'admin' desc")->orderBy('username')->paginate($n)->withQueryString();
        return view('users.index', compact('rows', 'q', 'n'));
    }

    public function create()
    {
        return view('users.form', ['presets' => Perm::presets()]);
    }

    public function store(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['subadmin', 'admin'])],
            'preset' => ['nullable', Rule::in(array_keys(Perm::presets()))],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'required' => 'Please fill in :attribute.',
            'username.regex' => 'The username must be 3 to 40 small letters, digits, dots, dashes or underscores.',
            'username.unique' => 'This username already exists.',
            'password.min' => 'The password must have at least 8 characters.',
        ], ['username' => 'Username', 'name' => 'Name', 'role' => 'Role', 'password' => 'Password']);

        $perms = null;
        if ($data['role'] === 'subadmin') {
            $perms = json_encode(Perm::normalize(Perm::presets()[$data['preset'] ?? 'usual'][1]));
        }
        $user = User::create(['username' => $data['username'], 'name' => $data['name'], 'role' => $data['role'], 'password' => $data['password'], 'active' => true, 'permissions' => $perms]);
        Audit::log('create', 'user', $user->id, $user->username . ' (' . $user->role . ')');
        return redirect()->route('users.edit', $user->id)->with('ok', $user->role === 'admin' ? 'Admin login created' : 'Login created. Now switch on what this person may do.');
    }

    public function edit(int $id)
    {
        $u = $this->staff($id);
        return view('users.edit', ['u' => $u, 'presets' => Perm::presets(), 'granted' => Perm::granted($u), 'self' => $u->id === auth()->id()]);
    }

    public function update(Request $request, int $id)
    {
        $u = $this->staff($id);
        $self = $u->id === auth()->id();
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,40}$/', Rule::unique('users', 'username')->ignore($u->id)],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['subadmin', 'admin'])],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'perm' => ['nullable', 'array'],
            'perm.*' => ['string', 'max:40'],
        ], [
            'required' => 'Please fill in :attribute.',
            'username.regex' => 'The username must be 3 to 40 small letters, digits, dots, dashes or underscores.',
            'username.unique' => 'This username already exists.',
            'password.min' => 'The new password must have at least 8 characters.',
        ], ['username' => 'Username', 'name' => 'Name', 'role' => 'Role']);

        $role = $self ? $u->role : $data['role'];             // nobody changes their own role or switches themselves off
        $active = $self ? true : $request->boolean('active');
        if (!$self && $this->lastAdmin($u) && ($role !== 'admin' || !$active)) {
            return back()->withInput()->withErrors(['role' => 'This is the only active Admin. Make another Admin first.']);
        }

        $before = Perm::granted($u);
        $perms = $role === 'subadmin' ? Perm::normalize((array) ($data['perm'] ?? [])) : null;
        $fill = ['username' => $data['username'], 'name' => $data['name'], 'role' => $role, 'active' => $active, 'permissions' => $perms === null ? null : json_encode($perms)];
        if (!empty($data['password'])) {
            $fill['password'] = $data['password'];   // hashed by the model
        }
        $u->update($fill);

        $detail = $u->username . ' (' . $role . ')';
        if ($role === 'subadmin') {
            $add = array_values(array_diff($perms, $before));
            $rem = array_values(array_diff($before, $perms));
            $detail .= ($add ? '; switched on: ' . implode(', ', $add) : '') . ($rem ? '; switched off: ' . implode(', ', $rem) : '');
        }
        Audit::log('edit', 'user', $u->id, $detail . (!empty($data['password']) ? '; password changed' : ''));
        return redirect()->route('users.index')->with('ok', 'Saved');
    }

    public function toggle(int $id)
    {
        $u = $this->staff($id);
        if ($u->id === auth()->id()) {
            return back()->with('bad', 'You cannot switch off your own login.');
        }
        if ($this->lastAdmin($u)) {
            return back()->with('bad', 'This is the only active Admin.');
        }
        $u->update(['active' => !$u->active]);
        Audit::log('edit', 'user', $u->id, $u->username . ($u->active ? ' switched on' : ' switched off'));
        return back();
    }

    public function destroy(int $id)
    {
        $u = $this->staff($id);
        if ($u->id === auth()->id()) {
            return back()->with('bad', 'You cannot delete your own login.');
        }
        if ($this->lastAdmin($u)) {
            return back()->with('bad', 'This is the only active Admin.');
        }
        $name = $u->username;
        $u->delete();
        Audit::log('delete', 'user', $id, $name);
        return redirect()->route('users.index')->with('ok', 'Login ' . $name . ' deleted');
    }
}
