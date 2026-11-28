<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Customer: its payment method (Cash, Cheque/Online, Credit). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'pay_mode')) {
            Schema::table('customers', fn (Blueprint $t) => $t->string('pay_mode', 20)->default('Cash'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'pay_mode')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('pay_mode'));
        }
    }
};
