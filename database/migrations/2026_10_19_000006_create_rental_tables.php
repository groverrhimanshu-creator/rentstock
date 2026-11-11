<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Rental challans and rental invoices, returns, replacements, upgrades (barcode consumption),
 * payment reminders and the reminder settings.
 * Uses only features that MySQL and MariaDB both have.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per document. kind = challan (items go out on rent) or invoice (monthly rental bill).
        Schema::create('rentals', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 10);                              // challan | invoice
            $t->string('no', 30)->unique();                      // "Ch 2143" or "KPI/26-27/001"
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $t->string('type', 20)->default('GST');
            $t->string('pay_mode', 20)->default('Cash');
            $t->string('remark', 255)->default('');
            $t->date('return_by')->nullable();                   // challan: expected return date
            $t->date('period_from')->nullable();                 // invoice: rent for this period
            $t->date('period_to')->nullable();
            $t->string('tax_mode', 6)->default('none');
            $t->decimal('taxable', 12, 2)->default(0);
            $t->decimal('cgst', 12, 2)->default(0);
            $t->decimal('sgst', 12, 2)->default(0);
            $t->decimal('igst', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->decimal('paid', 12, 2)->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['customer_id', 'kind']);
        });

        Schema::create('rental_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $t->unsignedInteger('code')->index();                // the barcode number of the piece
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->decimal('rent', 12, 2)->default(0);              // rent per month for this piece
            $t->decimal('price', 12, 2)->nullable();             // invoice only: the amount charged for the days out
            $t->unsignedSmallInteger('days')->nullable();        // invoice only: days out in the period
            $t->unsignedSmallInteger('period_days')->nullable(); // invoice only: days in the period
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->string('hsn', 10)->default('');
            $t->dateTime('from_at')->nullable();                 // challan only: when it went out
            $t->dateTime('returned_at')->nullable();             // challan only: when it came back (or was swapped)
            $t->unsignedBigInteger('return_id')->nullable()->index();
            $t->unsignedBigInteger('close_replacement_id')->nullable()->index();
            $t->unsignedBigInteger('open_replacement_id')->nullable()->index();
        });

        Schema::table('devices', function (Blueprint $t) {
            $t->string('rent_no', 30)->nullable();               // the challan the piece is out on
        });

        Schema::create('returns', function (Blueprint $t) {
            $t->id();
            $t->string('no', 30)->unique();
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $t->string('reason', 255)->default('');
            $t->string('notes', 255)->default('');
            $t->string('ref', 60)->default('');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('return_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $t->unsignedInteger('code')->index();
            $t->string('cond', 10)->default('Good');             // Good | Damaged
            $t->string('reason', 60)->default('');
            $t->string('note', 255)->default('');
            $t->unsignedBigInteger('hist_id')->nullable();       // the history row written for this piece
        });

        Schema::create('replacements', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 30);                              // Rental received | Sales received (credit note)
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $t->unsignedInteger('old_code')->index();
            $t->unsignedInteger('new_code')->index();
            $t->string('reason', 255)->default('');
            $t->string('cn', 30)->nullable();                    // credit note number (sales only)
            $t->decimal('rent', 12, 2)->nullable();              // rent carried over to the new piece (rental only)
            $t->date('old_return_by')->nullable();
            $t->unsignedBigInteger('old_hist_id')->nullable();
            $t->unsignedBigInteger('new_hist_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('consumptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('code')->index();
            $t->string('model', 150)->default('');
            $t->string('part', 30);
            $t->string('from_val', 60)->default('');
            $t->string('to_val', 60)->default('');
            $t->string('old_cfg', 255)->default('');
            $t->string('new_cfg', 255)->default('');
            $t->unsignedBigInteger('used_item_id')->nullable();
            $t->string('used_name', 150)->default('');
            $t->unsignedBigInteger('hist_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        // Every reminder e-mail that was sent. doc_kind = sale | rental.
        Schema::create('reminders', function (Blueprint $t) {
            $t->id();
            $t->string('doc_kind', 10);
            $t->unsignedBigInteger('doc_id');
            $t->string('doc_no', 30);
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('to_email', 150)->default('');
            $t->string('subject', 200)->default('');
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['doc_kind', 'doc_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('name', 40)->primary();
            $t->string('value', 255)->default('');
        });

        DB::table('settings')->insert([['name' => 'remind_on', 'value' => '1'], ['name' => 'remind_every', 'value' => '5']]);
        // The first challan made is Ch 2143.
        DB::table('counters')->insert(['name' => 'challan', 'value' => 2142]);
    }

    public function down(): void
    {
        foreach (['settings', 'reminders', 'consumptions', 'replacements', 'return_lines', 'returns', 'rental_lines'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn('rent_no');
        });
        Schema::dropIfExists('rentals');
        DB::table('counters')->where('name', 'challan')->delete();
    }
};
