<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The scanned DC copy of a sale, rental (challan or rental invoice), return or replacement.
 * The file (PDF, JPG or PNG, 5 MB at most) is kept in storage/app/private/dc-copies and is only opened by a signed-in user.
 */
class DcCopy
{
    public const MSG = 'The DC copy must be a PDF, JPG or PNG file of 5 MB or less.';
    public const KINDS = ['sale' => 'sales', 'rental' => 'rentals', 'return' => 'returns', 'replacement' => 'replacements'];

    /* True when a file was chosen but it is not acceptable. */
    public static function bad(Request $request, string $field = 'dc_file'): bool
    {
        if (!$request->hasFile($field)) {
            return false;
        }
        $f = $request->file($field);
        return !$f->isValid() || $f->getSize() > 5 * 1024 * 1024
            || !in_array(strtolower((string) $f->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png'], true)
            || !in_array((string) $f->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true);
    }

    /* Keeps the chosen file for a record (and removes the one it replaces). */
    public static function attach(Request $request, string $table, int $id, string $field = 'dc_file'): void
    {
        if (!$request->hasFile($field) || !in_array($table, self::KINDS, true)) {
            return;
        }
        $f = $request->file($field);
        $old = DB::table($table)->where('id', $id)->value('dc_file');
        $path = $f->store('dc-copies');
        DB::table($table)->where('id', $id)->update(['dc_file' => $path, 'dc_file_name' => mb_substr($f->getClientOriginalName(), 0, 150)]);
        if ($old) {
            Storage::delete($old);
        }
    }

    public static function remove(string $table, int $id): void
    {
        $old = DB::table($table)->where('id', $id)->value('dc_file');
        DB::table($table)->where('id', $id)->update(['dc_file' => null, 'dc_file_name' => null]);
        if ($old) {
            Storage::delete($old);
        }
    }

    /* Call just before a record is deleted so its file goes too. */
    public static function purge(string $table, int $id): void
    {
        $old = DB::table($table)->where('id', $id)->value('dc_file');
        if ($old) {
            Storage::delete($old);
        }
    }
}
