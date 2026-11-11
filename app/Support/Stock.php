<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/* Stock changes that touch several tables at once. */
class Stock
{
    /* Status => [label, pill colour], as in the prototype. */
    public const STATUS = [
        'in_stock' => ['In stock', 'ok'],
        'rented' => ['On rent', 'info'],
        'repair' => ['In repair', 'warn'],
        'sold' => ['Sold', 'idle'],
    ];

    public static function pill(string $status): string
    {
        $s = self::STATUS[$status] ?? [$status, 'idle'];
        return '<span class="pill ' . $s[1] . '">' . e($s[0]) . '</span>';
    }

    /* Writes one line in the history of a piece and returns its id. */
    public static function addHistory(int $code, string $text, ?int $userId = null): int
    {
        return (int) DB::table('device_history')->insertGetId(['code' => $code, 'at' => now(), 'text' => $text, 'user_id' => $userId]);
    }

    /*
     * Adds the good rows of a checked import as one stock entry. Either everything is saved or nothing is.
     * Returns [entry number, pieces added].
     */
    public static function importRows(array $good, ?int $userId): array
    {
        return DB::transaction(function () use ($good, $userId) {
            $entryId = DB::table('stock_entries')->insertGetId([
                'store_id' => $good[0]['store_id'],
                'source' => 'import',
                'piece_count' => count($good),
                'user_id' => $userId,
                'created_at' => now(),
            ]);
            $now = now();
            $devices = [];
            $history = [];
            $cfglog = [];
            $max = 0;
            foreach ($good as $r) {
                $devices[] = [
                    'code' => $r['code'], 'item_id' => $r['item']['id'],
                    'config' => $r['cfg'] !== '' ? $r['cfg'] : $r['item']['name'],
                    'brand_tag' => $r['tag'], 'status' => 'in_stock', 'store_id' => $r['store_id'],
                    'stock_entry_id' => $entryId, 'created_at' => $now,
                ];
                $history[] = ['code' => $r['code'], 'at' => $now, 'text' => 'Opening stock (stock no ' . $entryId . ')', 'user_id' => $userId];
                $cfglog[] = ['code' => $r['code'], 'event' => 'start', 'new' => $r['cfg'] !== '' ? $r['cfg'] : $r['item']['name'], 'note' => 'Opening stock (stock no ' . $entryId . ')', 'user_id' => $userId, 'at' => $now];
                $max = max($max, $r['code']);
            }
            foreach (array_chunk($devices, 500) as $chunk) {
                DB::table('devices')->insert($chunk);
            }
            foreach (array_chunk($history, 500) as $chunk) {
                DB::table('device_history')->insert($chunk);
            }
            ConfigLog::addMany($cfglog);
            // Barcodes from the old sheet may be higher than our counter. Move the counter up so no number is used twice.
            Barcodes::raise($max);
            return [$entryId, count($good)];
        });
    }

    /* ---------- edit and delete ---------- */

    /* History lines that are normal for a piece that never left the shelf. Anything else (rented, returned, repaired...) counts as movement. */
    private const QUIET = ['Opening stock%', 'Purchased from%', 'Details edited%', 'Sold to%', 'Sale cancelled%', 'Invoice %'];

    /* How many of the pieces in $codes (a list of codes or a sub-query) have history beyond the quiet lines above. */
    public static function movedCount($codes): int
    {
        return (int) DB::table('device_history')->whereIn('code', $codes)
            ->where(function ($w) {
                foreach (self::QUIET as $like) {
                    $w->where('text', 'not like', $like);
                }
            })->distinct()->count('code');
    }

    /* Why a piece cannot be deleted, or null when it can. */
    public static function deviceBlocker(int $code): ?string
    {
        $d = DB::table('devices')->where('code', $code)->first(['code', 'status']);
        if (!$d) {
            return 'That item was not found.';
        }
        $bc = Barcodes::text($code);
        if ($d->status !== 'in_stock') {
            return $bc . ' is ' . strtolower(self::STATUS[$d->status][0] ?? $d->status) . ', so it cannot be deleted. Only items that are in stock can be deleted.';
        }
        if (DB::table('sale_lines')->where('code', $code)->exists()) {
            return $bc . ' appears on a sale invoice, so it cannot be deleted. Delete that invoice first.';
        }
        if (self::movedCount([$code]) > 0) {
            return $bc . ' has been rented, returned or changed before, so it cannot be deleted. Its history must stay.';
        }
        return null;
    }

    public static function deleteDevice(int $code, ?int $userId): void
    {
        DB::transaction(function () use ($code) {
            DB::table('devices')->where('code', $code)->lockForUpdate()->first();
            if ($m = self::deviceBlocker($code)) {
                throw new \RuntimeException($m);
            }
            $d = DB::table('devices as d')->join('items as i', 'i.id', '=', 'd.item_id')->where('d.code', $code)->first(['i.name', 'd.brand_tag']);
            DB::table('devices')->where('code', $code)->delete();   // its history goes with it
            Audit::log('delete', 'item piece', $code, Barcodes::text($code) . ' · ' . $d->name . ' · tag ' . $d->brand_tag);
        });
    }

    /* Why a stock entry cannot be deleted, or null when it can. */
    public static function entryBlocker(int $id): ?string
    {
        $codes = DB::table('devices')->where('stock_entry_id', $id)->select('code');
        $away = DB::table('devices')->where('stock_entry_id', $id)->where('status', '!=', 'in_stock')->count();
        if ($away) {
            return 'Cannot delete this stock entry. ' . $away . ' of its items are already sold, on rent or in repair.';
        }
        if (DB::table('sale_lines')->whereIn('code', $codes)->exists()) {
            return 'Cannot delete this stock entry. Some of its items appear on a sale invoice.';
        }
        if (($n = self::movedCount($codes)) > 0) {
            return 'Cannot delete this stock entry. ' . $n . ' of its items have been rented, returned or changed before.';
        }
        return null;
    }

    public static function deleteEntry(int $id): void
    {
        DB::transaction(function () use ($id) {
            $e = DB::table('stock_entries')->where('id', $id)->lockForUpdate()->first();
            if (!$e) {
                throw new \RuntimeException('That stock entry was not found.');
            }
            DB::table('devices')->where('stock_entry_id', $id)->lockForUpdate()->pluck('code');
            if ($m = self::entryBlocker($id)) {
                throw new \RuntimeException($m);
            }
            $n = DB::table('devices')->where('stock_entry_id', $id)->count();
            DB::table('devices')->where('stock_entry_id', $id)->delete();
            DB::table('stock_entries')->where('id', $id)->delete();
            Audit::log('delete', 'stock entry', $id, $n . ' items removed');
        });
    }
}
