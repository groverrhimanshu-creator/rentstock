<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Masters, company profile and the barcode counter. Uses only features that MySQL and MariaDB both have. */
return new class extends Migration
{
    public function up(): void
    {
        // The company printed on every invoice and challan. One row, id 1. "prefix" is the document and barcode prefix.
        Schema::create('company_profile', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->string('name', 150);
            $t->string('line1', 200)->default('');
            $t->string('line2', 200)->default('');
            $t->string('phone', 40)->default('');
            $t->string('email', 150)->default('');
            $t->string('web', 150)->default('');
            $t->string('bank', 100)->default('');
            $t->string('acc', 30)->default('');
            $t->string('ifsc', 11)->default('');
            $t->string('gstin', 15)->default('');
            $t->string('pan', 10)->default('');
            $t->char('state_code', 2)->default('03');
            $t->string('prefix', 10)->default('KPI');
            $t->timestamp('updated_at')->nullable();
        });

        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('popular_name', 100)->default('');
            $t->string('description', 255)->default('');
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->string('description', 255)->default('');
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('name', 50)->unique();
            $t->string('symbol', 20)->default('');
            $t->string('description', 255)->default('');
        });

        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
        });

        foreach (['customers', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name', 150)->unique();
                $t->string('address', 255)->default('');
                $t->string('city', 80)->default('');
                $t->string('state', 60)->default('');
                $t->char('state_code', 2)->default('');
                $t->string('gstin', 15)->default('');
                $t->string('contact', 100)->default('');
                $t->string('phone', 30)->default('');
                $t->string('email', 150)->default('');
                $t->timestamp('created_at')->useCurrent();
            });
        }

        Schema::create('banks', function (Blueprint $t) {
            $t->id();
            $t->string('account_no', 30)->unique();
            $t->string('bank_name', 100);
            $t->string('branch', 100)->default('');
            $t->string('ifsc', 11)->default('');
            $t->string('account_type', 20)->default('Current');
        });

        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $t->string('hsn', 10)->default('');
            $t->decimal('gst_percent', 5, 2)->default(18);
            $t->decimal('rent_per_month', 12, 2)->default(0);
            $t->decimal('sale_price', 12, 2)->default(0);
            $t->boolean('tracked')->default(true);   // true = every piece gets a barcode, false = counted by quantity
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('counters', function (Blueprint $t) {
            $t->string('name', 40)->primary();
            $t->unsignedBigInteger('value');
        });

        // The first barcode made is 600001.
        DB::table('counters')->insert(['name' => 'barcode', 'value' => 600000]);
        // Placeholder details, the same as the prototype. Replace them under Company Profile.
        DB::table('company_profile')->insert([
            'id' => 1, 'name' => 'Your Company Name', 'line1' => 'Shop No 1, Address line, City',
            'line2' => 'State (PB - 03), PIN Code 000000, India', 'phone' => '+91-0000000000',
            'email' => 'accounts@example.com', 'web' => 'www.example.com', 'bank' => 'Bank Name',
            'acc' => '00000000000', 'ifsc' => 'ABCD0000000', 'gstin' => '03AAAAA0000A1Z5', 'pan' => 'AAAAA0000A',
            'state_code' => '03', 'prefix' => 'KPI',
        ]);
    }

    public function down(): void
    {
        foreach (['counters', 'items', 'banks', 'suppliers', 'customers', 'stores', 'units', 'categories', 'brands', 'company_profile'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
