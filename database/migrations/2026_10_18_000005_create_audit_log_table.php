<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* A record of every edit and delete: who did it, when, and what it was. Nothing in the app reads it yet; it is for the owner to look at. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('username', 60)->default('');
            $t->string('action', 20);          // edit | delete
            $t->string('subject', 40);         // brand, item, purchase, sale, stock entry, ...
            $t->string('subject_id', 40)->default('');
            $t->text('detail')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject', 'subject_id']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
