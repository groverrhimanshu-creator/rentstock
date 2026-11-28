<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* What happened to each task and when: added, done, moved to another date (with the reason), opened again, edited. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('task_events')) {
            Schema::create('task_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('task_id')->index();
                $t->string('title', 200)->default('');
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // who did it
                $t->unsignedBigInteger('for_user_id')->nullable();                            // whose task it is
                $t->string('action', 12);                                                      // added | done | moved | reopened | edited
                $t->string('note', 500)->nullable();
                $t->date('from_date')->nullable();
                $t->date('to_date')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_events');
    }
};
