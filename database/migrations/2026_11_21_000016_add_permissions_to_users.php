<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What a staff login may do. A JSON list of permission keys such as "rental.create". Empty (null) means "the usual SubAdmin rights"
 * so the logins that already exist keep working exactly as they did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('permissions')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('permissions');
        });
    }
};
