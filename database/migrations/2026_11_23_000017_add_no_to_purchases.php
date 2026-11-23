<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Purchases get their own plain serial number (1, 2, 3 ...), apart from the database row id. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchases', 'no')) {
            Schema::table('purchases', function (Blueprint $t) {
                $t->unsignedInteger('no')->nullable()->after('id');
            });
        }
        $n = 0;
        foreach (DB::table('purchases')->orderBy('id')->pluck('id') as $id) {
            DB::table('purchases')->where('id', $id)->update(['no' => ++$n]);   // existing purchases are numbered 1, 2, 3 in the order they were made
        }
        Schema::table('purchases', function (Blueprint $t) {
            $t->unique('no');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropUnique(['no']);
            $t->dropColumn('no');
        });
    }
};
