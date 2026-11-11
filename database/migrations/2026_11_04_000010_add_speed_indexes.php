<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Speed only: extra indexes on columns the screens filter and sort by. No data or behaviour changes. */
return new class extends Migration
{
    private function list(): array
    {
        return [
            ['devices', ['status', 'return_by'], 'devices_status_return_idx'],
            ['devices', ['item_id', 'status'], 'devices_item_status_idx'],
            ['rental_lines', ['rental_id', 'returned_at'], 'rl_rental_returned_idx'],
            ['rental_lines', ['code', 'returned_at'], 'rl_code_returned_idx'],
            ['rentals', ['kind', 'created_at'], 'rentals_kind_created_idx'],
            ['purchases', ['inv_date'], 'purchases_inv_date_idx'],
            ['reminders', ['doc_kind', 'doc_id'], 'reminders_doc_idx'],
        ];
    }

    public function up(): void
    {
        foreach ($this->list() as [$table, $cols, $name]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $c) {
                if (!Schema::hasColumn($table, $c)) {
                    continue 2;
                }
            }
            Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
        }
    }

    public function down(): void
    {
        foreach ($this->list() as [$table, , $name]) {
            if (Schema::hasTable($table)) {
                try { Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name)); } catch (\Throwable $e) {}
            }
        }
    }
};
