<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* "Maintain stock": a quantity item with No is not counted in stock (no quantity kept, nothing to run out of). Every existing item stays Yes. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'maintain_stock')) {
            Schema::table('items', function (Blueprint $t) {
                $t->boolean('maintain_stock')->default(true)->after('tracked');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'maintain_stock')) {
            Schema::table('items', fn (Blueprint $t) => $t->dropColumn('maintain_stock'));
        }
    }
};
