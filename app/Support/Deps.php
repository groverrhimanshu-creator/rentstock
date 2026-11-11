<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Finds out whether a master record is still in use. A record that is in use cannot be deleted. */
class Deps
{
    private static function plural(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }

    private static function count(string $table, string $col, int $id): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->where($col, $id)->count() : 0;
    }

    private static function sentence(string $title, array $parts): ?string
    {
        if (!$parts) {
            return null;
        }
        $what = strtolower($title);
        $list = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : $parts[0];
        return 'This ' . $what . ' cannot be deleted because it is already used by ' . $list . '. Change or remove those first, then delete it.';
    }

    /* Item names to show as examples, for example "Latitude 7490, OptiPlex 3040, Dell 19" and 9 more". */
    private static function itemExamples(string $col, int $id, int $n): string
    {
        $names = DB::table('items')->where($col, $id)->orderBy('name')->limit(3)->pluck('name')->all();
        return self::plural($n, 'item', 'items') . ' (' . implode(', ', $names) . ($n > 3 ? ' and ' . ($n - 3) . ' more' : '') . ')';
    }

    /* A plain message when the record cannot be deleted, or null when it can. */
    public static function master(string $key, int $id): ?string
    {
        $title = Masters::def($key)['title'];
        $parts = [];

        switch ($key) {
            case 'brand':
            case 'category':
            case 'unit':
                $col = $key . '_id';
                if (($n = self::count('items', $col, $id)) > 0) {
                    $parts[] = self::itemExamples($col, $id, $n);
                }
                if ($key === 'unit' && DB::table('units')->where('id', $id)->value('name') === 'Nos') {
                    return 'The unit "Nos" is the default unit for new items and cannot be deleted.';
                }
                break;

            case 'store':
                if (DB::table('stores')->count() <= 1) {
                    return 'This is the only store. At least one store must stay, so it cannot be deleted.';
                }
                if (($n = self::count('devices', 'store_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'barcoded piece', 'barcoded pieces');
                }
                if (($n = self::count('stock_entries', 'store_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'stock entry', 'stock entries');
                }
                if (($n = self::count('purchases', 'store_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'purchase', 'purchases');
                }
                break;

            case 'customer':
                if (($n = self::count('sales', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'sale invoice', 'sale invoices');
                }
                if (($n = self::count('devices', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'barcoded piece (sold or on rent)', 'barcoded pieces (sold or on rent)');
                }
                if (($n = self::count('users', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'customer login', 'customer logins');
                }
                if (($n = self::count('rentals', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'rental document', 'rental documents');
                }
                if (($n = self::count('returns', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'return', 'returns');
                }
                if (($n = self::count('replacements', 'customer_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'replacement', 'replacements');
                }
                break;

            case 'supplier':
                if (($n = self::count('purchases', 'supplier_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'purchase', 'purchases');
                }
                break;

            case 'item':
                if (($n = self::count('devices', 'item_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'barcoded piece', 'barcoded pieces');
                }
                if (Schema::hasTable('item_qty') && ($n = (int) DB::table('item_qty')->where('item_id', $id)->value('qty')) > 0) {
                    $parts[] = self::plural($n, 'piece counted by quantity', 'pieces counted by quantity');
                }
                if (($n = self::count('purchase_lines', 'item_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'purchase line', 'purchase lines');
                }
                if (($n = self::count('sale_lines', 'item_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'sale line', 'sale lines');
                }
                if (($n = self::count('rental_lines', 'item_id', $id)) > 0) {
                    $parts[] = self::plural($n, 'rental line', 'rental lines');
                }
                break;
        }
        return self::sentence($title, $parts);
    }
}
