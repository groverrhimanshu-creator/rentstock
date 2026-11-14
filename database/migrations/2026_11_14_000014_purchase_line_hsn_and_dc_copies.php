<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * - purchase_lines.hsn: the HSN code of the purchased item on this purchase (it starts as the item's HSN and can be corrected on the purchase).
 * - dc_file / dc_file_name on sales, rentals (challans and rental invoices), returns and replacements: the scanned DC copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_lines', 'hsn')) {
            Schema::table('purchase_lines', fn (Blueprint $t) => $t->string('hsn', 10)->nullable());
        }
        foreach (['sales', 'rentals', 'returns', 'replacements'] as $table) {
            if (!Schema::hasColumn($table, 'dc_file')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('dc_file', 255)->nullable();
                    $t->string('dc_file_name', 150)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_lines', 'hsn')) {
            Schema::table('purchase_lines', fn (Blueprint $t) => $t->dropColumn('hsn'));
        }
        foreach (['sales', 'rentals', 'returns', 'replacements'] as $table) {
            if (Schema::hasColumn($table, 'dc_file')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['dc_file', 'dc_file_name']));
            }
        }
    }
};
