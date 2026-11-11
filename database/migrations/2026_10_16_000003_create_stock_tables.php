<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Stock. Every barcoded piece is one row in "devices". Its barcode is the company prefix plus devices.code
 * (for example KPI600001). Items counted by quantity live in "item_qty".
 */
return new class extends Migration
{
    public function up(): void
    {
        // One stock entry = one batch of pieces added together (an import now, a purchase later).
        Schema::create('stock_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $t->string('source', 20)->default('import');   // import | purchase
            $t->unsignedInteger('piece_count')->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('devices', function (Blueprint $t) {
            $t->unsignedInteger('code')->primary();         // the number part of the barcode
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->string('config', 255)->default('');
            $t->string('brand_tag', 60)->default('');
            $t->string('status', 12)->default('in_stock');  // in_stock | rented | repair | sold
            $t->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $t->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $t->foreignId('stock_entry_id')->nullable()->constrained('stock_entries')->nullOnDelete();
            $t->timestamp('rented_at')->nullable();
            $t->date('return_by')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('status');
            $t->index('brand_tag');
        });

        Schema::create('device_history', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('code');
            $t->timestamp('at')->useCurrent();
            $t->string('text', 255);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['code', 'at']);
            $t->foreign('code')->references('code')->on('devices')->cascadeOnDelete();
        });

        Schema::create('item_qty', function (Blueprint $t) {
            $t->foreignId('item_id')->primary()->constrained('items')->restrictOnDelete();
            $t->integer('qty')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['item_qty', 'device_history', 'devices', 'stock_entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
