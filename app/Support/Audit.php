<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $action, string $subject, int|string $id, string $detail = ''): void
    {
        $u = auth()->user();
        DB::table('audit_log')->insert([
            'user_id' => $u?->id, 'username' => (string) ($u?->username ?? ''), 'action' => $action, 'subject' => $subject,
            'subject_id' => (string) $id, 'detail' => mb_substr($detail, 0, 2000), 'created_at' => now(),
        ]);
    }

    /* Notes that a document was made: looks up its number, party and total from the table, so every "add" line reads the same. */
    public static function created(string $subject, string $table, int $id): void
    {
        $r = DB::table($table)->where('id', $id)->first();
        if (!$r) {
            return;
        }
        $parts = [];
        if (!empty($r->customer_id)) {
            $parts[] = 'Customer ' . DB::table('customers')->where('id', $r->customer_id)->value('name');
        }
        if (!empty($r->supplier_id)) {
            $parts[] = 'Supplier ' . DB::table('suppliers')->where('id', $r->supplier_id)->value('name');
        }
        if (!empty($r->inv_no)) {
            $parts[] = 'invoice ' . $r->inv_no;
        }
        if (!empty($r->part)) {
            $parts[] = Barcodes::text((int) $r->code) . ' · ' . $r->part . ' ' . $r->from_val . ' to ' . $r->to_val;
        }
        if (isset($r->total) && (float) $r->total > 0) {
            $parts[] = 'total ' . $r->total;
        }
        self::log('add', $subject, $r->no ?? $id, implode(' · ', $parts));
    }
}
