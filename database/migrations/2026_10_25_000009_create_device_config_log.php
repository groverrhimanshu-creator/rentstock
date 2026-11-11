<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Configuration log of every barcoded item: what the config was when it came in (purchase or opening stock)
 * and every change after that (upgrade, edit, purchase edit, upgrade undone).
 * Rows for items that already exist are built from what the system knows: the first config comes from the first upgrade
 * (its "old config"), and each upgrade becomes a row. Manual edits made before this update cannot be recovered.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_config_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('code');
            $t->timestamp('at')->useCurrent();
            $t->string('event', 20);                       // start, upgrade, edit, purchase_edit, upgrade_undo
            $t->string('old_config', 255)->nullable();
            $t->string('new_config', 255)->default('');
            $t->string('note', 255)->default('');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['code', 'at']);
            $t->foreign('code')->references('code')->on('devices')->cascadeOnDelete();
        });

        // 1. The starting config of every existing item.
        DB::statement("INSERT INTO device_config_log (code, at, event, old_config, new_config, note)
            SELECT d.code, d.created_at, 'start', NULL,
                COALESCE((SELECT c.old_cfg FROM consumptions c WHERE c.code = d.code ORDER BY c.id LIMIT 1), d.config),
                CASE WHEN d.purchase_id IS NOT NULL THEN CONCAT('Purchased (purchase no ', d.purchase_id, ')') ELSE 'Opening stock' END
            FROM devices d");

        // 2. Every upgrade done so far.
        DB::statement("INSERT INTO device_config_log (code, at, event, old_config, new_config, note, user_id)
            SELECT c.code, c.created_at, 'upgrade', c.old_cfg, c.new_cfg, CONCAT(c.part, ': ', c.from_val, ' to ', c.to_val), c.user_id
            FROM consumptions c JOIN devices d ON d.code = c.code");
    }

    public function down(): void
    {
        Schema::dropIfExists('device_config_log');
    }
};
