<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The supplier's invoice (PDF or photo) can be attached to a purchase. The file is kept in storage/app/private/purchase-invoices. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchases', 'invoice_file')) {
            Schema::table('purchases', function (Blueprint $t) {
                $t->string('invoice_file', 255)->nullable();
                $t->string('invoice_file_name', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'invoice_file')) {
            Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['invoice_file', 'invoice_file_name']));
        }
    }
};
