<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* A replacement records whether the item that came back was Damaged or Faulty. Older replacements stay blank. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('replacements', 'fault')) {
            Schema::table('replacements', function (Blueprint $t) {
                $t->string('fault', 20)->default('')->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('replacements', 'fault')) {
            Schema::table('replacements', fn (Blueprint $t) => $t->dropColumn('fault'));
        }
    }
};
