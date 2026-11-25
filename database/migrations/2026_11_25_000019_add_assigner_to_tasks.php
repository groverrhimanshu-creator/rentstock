<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* A task can be given to another login: user_id is who has to do it, created_by is who gave it. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tasks', 'created_by')) {
            Schema::table('tasks', function (Blueprint $t) {
                $t->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            });
        }
        DB::table('tasks')->whereNull('created_by')->update(['created_by' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('created_by');
        });
    }
};
