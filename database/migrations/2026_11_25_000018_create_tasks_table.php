<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* "My tasks": each login keeps its own to-do list with due dates. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('notes')->nullable();
            $t->date('due_date');
            $t->string('status', 8)->default('open');        // open | done
            $t->timestamp('done_at')->nullable();
            $t->unsignedSmallInteger('moved')->default(0);     // how many times it was rescheduled
            $t->timestamps();
            $t->index(['user_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
