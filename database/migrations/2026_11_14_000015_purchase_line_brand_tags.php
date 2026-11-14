<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Brand tags typed for a "No barcode" purchase line (barcoded pieces keep their tag on the piece itself). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_lines', 'brand_tags')) {
            Schema::table('purchase_lines', fn (Blueprint $t) => $t->text('brand_tags')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_lines', 'brand_tags')) {
            Schema::table('purchase_lines', fn (Blueprint $t) => $t->dropColumn('brand_tags'));
        }
    }
};
