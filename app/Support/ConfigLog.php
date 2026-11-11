<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/*
 * The configuration log of a barcoded item: its config when it came in and every change after that.
 * Events: start (purchase or opening stock), upgrade, edit, purchase_edit, upgrade_undo.
 */
class ConfigLog
{
    public const EVENTS = [
        'start' => 'Received',
        'upgrade' => 'Upgraded',
        'part' => 'Part replaced',
        'edit' => 'Edited',
        'purchase_edit' => 'Edited with purchase',
        'upgrade_undo' => 'Upgrade undone',
    ];

    public static function add(int $code, string $event, ?string $old, string $new, string $note, ?int $userId, $at = null): void
    {
        DB::table('device_config_log')->insert(['code' => $code, 'at' => $at ?: now(), 'event' => $event, 'old_config' => $old, 'new_config' => $new, 'note' => $note, 'user_id' => $userId]);
    }

    /* Many rows at once. Each: code, event, old, new, note, user_id, at. */
    public static function addMany(array $rows): void
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['code' => $r['code'], 'at' => $r['at'] ?? now(), 'event' => $r['event'], 'old_config' => $r['old'] ?? null, 'new_config' => $r['new'], 'note' => $r['note'] ?? '', 'user_id' => $r['user_id'] ?? null];
        }
        foreach (array_chunk($out, 500) as $c) {
            DB::table('device_config_log')->insert($c);
        }
    }

    /* The log of one item, oldest first, with the name of the user who made each change. */
    public static function for(int $code)
    {
        return DB::table('device_config_log as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')->where('l.code', $code)
            ->orderBy('l.at')->orderBy('l.id')->get(['l.at', 'l.event', 'l.old_config', 'l.new_config', 'l.note', 'u.name as user']);
    }

    public static function label(string $event): string
    {
        return self::EVENTS[$event] ?? $event;
    }
}
