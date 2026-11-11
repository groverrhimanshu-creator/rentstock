<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ChangePassword extends Command
{
    protected $signature = 'login:password {username}';

    protected $description = 'Set a new password for a login';

    public function handle(): int
    {
        $user = User::where('username', strtolower($this->argument('username')))->first();
        if (!$user) {
            $this->error('No login with that username.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('New password (at least 8 characters)');
        if (strlen($password) < 8) {
            $this->error('The password is too short.');
            return self::FAILURE;
        }
        $user->update(['password' => $password]);
        $this->info('Password changed.');
        return self::SUCCESS;
    }
}
