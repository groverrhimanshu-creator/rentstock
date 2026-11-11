<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/* The prototype has no screen for creating Admin or SubAdmin logins, so they are made here. */
class CreateLogin extends Command
{
    protected $signature = 'login:create {role : admin or subadmin} {username} {name : full name, in quotes}';

    protected $description = 'Create an Admin or SubAdmin login';

    public function handle(): int
    {
        $role = $this->argument('role');
        $username = strtolower($this->argument('username'));
        if ($role === 'subadmin' && !config('rentstock.subadmin_enabled')) {
            $this->error('The SubAdmin role is switched off for now (SUBADMIN_ENABLED in .env).');
            return self::FAILURE;
        }
        if (!in_array($role, ['admin', 'subadmin'], true)) {
            $this->error('Role must be admin or subadmin. Customer logins are made under Masters > Customer Logins.');
            return self::FAILURE;
        }
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
            $this->error('The username must be 3 to 40 small letters, digits, dots, dashes or underscores.');
            return self::FAILURE;
        }
        if (DB::table('users')->where('username', $username)->exists()) {
            $this->error('That username already exists.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('Password (at least 8 characters)');
        if (strlen($password) < 8) {
            $this->error('The password is too short.');
            return self::FAILURE;
        }
        User::create(['role' => $role, 'username' => $username, 'name' => $this->argument('name'), 'password' => $password, 'active' => true]);
        $this->info('Login created.');
        return self::SUCCESS;
    }
}
