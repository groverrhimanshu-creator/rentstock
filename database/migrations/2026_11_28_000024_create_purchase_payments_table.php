<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Money paid to a supplier. It is counted against the supplier's oldest unpaid bills first. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_payments')) {
            Schema::create('purchase_payments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->decimal('amount', 12, 2);
                $t->date('paid_on');
                $t->string('mode', 20)->default('Cash');
                $t->string('note', 255)->default('');
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
