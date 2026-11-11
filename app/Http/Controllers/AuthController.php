<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /* The roles that can log in. SubAdmin is left out while it is switched off (config rentstock.subadmin_enabled). */
    public static function roles(): array
    {
        return config('rentstock.subadmin_enabled') ? ['admin', 'subadmin', 'customer'] : ['admin', 'customer'];
    }

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', 'in:' . implode(',', self::roles())],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], ['required' => 'Please fill in :attribute.'], ['role' => 'Role', 'username' => 'Username', 'password' => 'Password']);

        $username = Str::lower(trim($data['username']));

        // Five wrong passwords from the same place for the same username lock it for ten minutes.
        $key = $username . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages(['login' => 'Too many wrong tries. Please try again in ' . $minutes . ' minute(s).']);
        }

        $user = User::where('username', $username)->first();
        if (!$user || !$user->active || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['login' => 'Wrong username or password.']);
        }
        if ($user->role === 'subadmin' && !config('rentstock.subadmin_enabled')) {
            throw ValidationException::withMessages(['login' => 'This login is not available right now. Please contact the administrator.']);
        }
        if ($user->role !== $data['role']) {
            $label = ['admin' => 'Admin', 'subadmin' => 'SubAdmin', 'customer' => 'Customer'][$data['role']];
            throw ValidationException::withMessages(['login' => 'This user is not a ' . $label . '. Pick the right role.']);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login' => now()])->save();

        return redirect()->intended($user->role === 'customer' ? route('page', 'portal') : route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
