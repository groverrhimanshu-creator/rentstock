<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Leaves Company Profile empty while the project is a self-study / demo build, so nobody mistakes it for a client's data.
 * Only clears the profile while it still holds the placeholder or the earlier KPI details; anything typed by hand is kept.
 * The state code (needed for the GST split) and the document prefix are left as they are.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('company_profile')->where('id', 1)
            ->where(function ($q) {
                $q->whereIn('name', ['Your Company Name', 'KPI SOLUTIONS', ''])
                  ->orWhereIn('gstin', ['03AAAAA0000A1Z5', '03ILOPK3302J1Z4']);
            })
            ->update([
                'name' => '', 'line1' => '', 'line2' => '', 'phone' => '', 'email' => '', 'web' => '',
                'bank' => '', 'acc' => '', 'ifsc' => '', 'gstin' => '', 'pan' => '',
            ]);
    }

    public function down(): void
    {
    }
};
