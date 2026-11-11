<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $t->string('inv_no', 60)->default('');
            $t->date('inv_date')->nullable();
            $t->string('type', 20)->default('GST');
            $t->string('pay_mode', 20)->default('Cash');
            $t->foreignId('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('purchase_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->string('config', 150)->default('');
            $t->unsignedInteger('qty');
            $t->decimal('rate', 12, 2)->default(0);
            $t->boolean('barcoded')->default(true);
        });

        Schema::table('devices', function (Blueprint $t) {
            $t->unsignedBigInteger('purchase_id')->nullable()->index();
            $t->unsignedBigInteger('purchase_line_id')->nullable()->index();
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('no', 30)->unique();
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $t->string('type', 20)->default('GST');
            $t->string('pay_mode', 20)->default('Cash');
            $t->string('remark', 255)->default('');
            $t->string('tax_mode', 6)->default('none');
            $t->decimal('taxable', 12, 2)->default(0);
            $t->decimal('cgst', 12, 2)->default(0);
            $t->decimal('sgst', 12, 2)->default(0);
            $t->decimal('igst', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('paid', 12, 2)->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index('customer_id');
        });

        Schema::create('sale_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->unsignedInteger('code')->nullable()->index();   // set for a barcoded piece, empty for a quantity item
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('price', 12, 2)->default(0);
            $t->decimal('gst_percent', 5, 2)->default(18);       // copied from the item at the time of sale
            $t->string('hsn', 10)->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn(['purchase_id', 'purchase_line_id']);
        });
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
    }
};
