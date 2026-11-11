<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A rental invoice can now also carry items that are sold to the customer (like the client's sample invoice).
 *  - rental_lines.line_kind: 'rent' (the old lines) or 'sale' (a sold item).  rental_lines.qty: 1 for rent lines.
 *  - rental_lines.code may be empty, because a sold quantity item (no barcode) has no code.
 *  - Rent lines carry the fixed rent code (SAC 997315) instead of the HSN of the item.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('rental_lines', function (Blueprint $t) {
            $t->string('line_kind', 4)->default('rent');
            $t->unsignedInteger('qty')->default(1);
        });
        Schema::table('rental_lines', function (Blueprint $t) {
            $t->unsignedInteger('code')->nullable()->change();
        });
        DB::table('rental_lines')->update(['hsn' => '997315']);
    }

    public function down(): void
    {
        Schema::table('rental_lines', function (Blueprint $t) {
            $t->dropColumn(['line_kind', 'qty']);
        });
    }
};
