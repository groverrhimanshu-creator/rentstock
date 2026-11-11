<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Adds what this system needs to Laravel's own users table: a username to log in with,
 * a role (admin, subadmin or customer), the customer a customer login belongs to, and an on/off switch.
 * Runs after the foundation tables because customer_id points at customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 40)->nullable()->unique()->after('id');
            $t->string('role', 20)->default('subadmin')->after('username');
            $t->foreignId('customer_id')->nullable()->after('role')->constrained('customers')->restrictOnDelete();
            $t->boolean('active')->default(true)->after('customer_id');
            $t->timestamp('last_login')->nullable();
        });

        // SubAdmin logins do not need an email address.
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_id');
            $t->dropUnique(['username']);
            $t->dropColumn(['username', 'role', 'active', 'last_login']);
        });
    }
};
