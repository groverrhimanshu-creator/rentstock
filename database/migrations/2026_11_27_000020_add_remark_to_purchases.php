<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* A remark on a purchase, like the one on a sale. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchases', 'remark')) {
            Schema::table('purchases', function (Blueprint $t) {
                $t->string('remark', 255)->default('');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'remark')) {
            Schema::table('purchases', function (Blueprint $t) {
                $t->dropColumn('remark');
            });
        }
    }
};
