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
}
