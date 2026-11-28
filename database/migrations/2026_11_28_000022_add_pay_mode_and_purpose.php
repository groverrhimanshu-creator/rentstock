<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Supplier: its usual payment method (Cash, Cheque/Online, Credit). Customer: what it is for (Rental, Sale or Both). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('suppliers', 'pay_mode')) {
            Schema::table('suppliers', fn (Blueprint $t) => $t->string('pay_mode', 20)->default('Cash'));
        }
        if (!Schema::hasColumn('customers', 'purpose')) {
            Schema::table('customers', fn (Blueprint $t) => $t->string('purpose', 10)->default('Both'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'pay_mode')) {
            Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('pay_mode'));
        }
        if (Schema::hasColumn('customers', 'purpose')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('purpose'));
        }
    }
};
