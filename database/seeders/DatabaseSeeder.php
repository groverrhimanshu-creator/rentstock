<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // First login. Change the password at once with:  php artisan login:password admin
        User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'role' => 'admin',
            'active' => true,
            'password' => 'Admin@12345',
        ]);

        // A few starting values so the item form is not empty. Add or change them under Masters.
        DB::table('units')->insertOrIgnore([['name' => 'Nos', 'symbol' => 'Nos'], ['name' => 'Pcs', 'symbol' => 'Pcs']]);
        DB::table('stores')->insertOrIgnore([['name' => 'Main Store']]);
    }
}
