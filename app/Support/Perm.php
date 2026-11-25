<?php

namespace App\Support;

/*
 * What each staff login may do.
 *   Admin     : everything, always (and only the Admin sees Users, Customer logins and Company Profile).
 *   SubAdmin  : only the permissions ticked for that login on the Users page.
 *   Customer  : none of these (the customer portal has its own routes).
 * A permission is "module.action", for example "rental.create". Routes check it with the "perm" middleware, menus and buttons with Perm::has().
 */
class Perm
{
    public const MODULES = [
        'purchase' => ['Purchase', ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete']],
        'sale' => ['Sale and rental invoice', ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete']],
        'rental' => ['Rental challan', ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete']],
        'return' => ['Return', ['view' => 'View', 'create' => 'Create', 'delete' => 'Delete']],
        'replacement' => ['Replacement', ['view' => 'View', 'create' => 'Create', 'delete' => 'Delete']],
        'consumption' => ['Upgrade assets', ['view' => 'View', 'create' => 'Create', 'delete' => 'Delete']],
        'stock' => ['Stock (inventory and entries)', ['view' => 'View', 'import' => 'Import', 'repair' => 'Mark repaired', 'edit' => 'Edit', 'delete' => 'Delete']],
        'barcodes' => ['Print barcodes', ['view' => 'Print']],
        'payments' => ['Payments and reminders', ['view' => 'View', 'manage' => 'Send reminders / mark paid']],
        'reports' => ['Reports', ['view' => 'View']],
        'tasks' => ['Tasks (own list; Manage = give tasks to others and see everybody\'s)', ['view' => 'View', 'create' => 'Create', 'manage' => 'Manage team tasks', 'edit' => 'Complete / reschedule / edit', 'delete' => 'Delete']],
        'masters' => ['Masters (brand, category, unit, store, customer, supplier, item, bank)', ['view' => 'View', 'create' => 'Add / import', 'edit' => 'Edit', 'delete' => 'Delete']],
    ];

    /* The columns of the permission table, in order, with their headings. */
    public const ACTIONS = ['view' => 'View', 'create' => 'Create', 'import' => 'Import', 'repair' => 'Repair', 'manage' => 'Manage', 'edit' => 'Edit', 'delete' => 'Delete'];

    /* What a SubAdmin could do before this page existed: enter data and look at reports, but not edit, delete or touch masters. */
    public const USUAL = [
        'purchase.view', 'purchase.create', 'sale.view', 'sale.create', 'rental.view', 'rental.create', 'return.view', 'return.create',
        'replacement.view', 'replacement.create', 'consumption.view', 'consumption.create', 'stock.view', 'stock.import', 'stock.repair',
        'barcodes.view', 'payments.view', 'payments.manage', 'reports.view', 'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete',
    ];

    public static function presets(): array
    {
        $all = self::keys();
        return [
            'usual' => ['Data entry (add and view, no edit or delete)', self::USUAL],
            'readonly' => ['Read only (view and print)', array_values(array_filter($all, fn ($k) => str_ends_with($k, '.view') && !str_starts_with($k, 'masters.')))],
            'nomasters' => ['Everything except Masters', array_values(array_filter($all, fn ($k) => !str_starts_with($k, 'masters.')))],
            'full' => ['Everything', $all],
            'none' => ['Nothing (switch on one by one)', []],
        ];
    }

    public static function keys(): array
    {
        $out = [];
        foreach (self::MODULES as $m => $def) {
            foreach (array_keys($def[1]) as $a) {
                $out[] = $m . '.' . $a;
            }
        }
        return $out;
    }

    /* Keeps only real keys, and makes sure anybody who can create, edit or delete in a module can also view it. */
    public static function normalize(array $in): array
    {
        $valid = array_flip(self::keys());
        $set = [];
        foreach ($in as $k) {
            if (is_string($k) && isset($valid[$k])) {
                $set[$k] = true;
            }
        }
        foreach (self::MODULES as $m => $def) {
            if (isset($def[1]['view'])) {
                foreach (array_keys($def[1]) as $a) {
                    if ($a !== 'view' && isset($set[$m . '.' . $a])) {
                        $set[$m . '.view'] = true;
                    }
                }
            }
        }
        return array_values(array_filter(self::keys(), fn ($k) => isset($set[$k])));
    }

    /* The permission keys a login has. */
    public static function granted($user = null): array
    {
        static $cache = [];
        $user = $user ?? auth()->user();
        if (!$user) {
            return [];
        }
        $ck = $user->id . '|' . $user->role . '|' . (string) $user->permissions;
        if (isset($cache[$ck])) {
            return $cache[$ck];
        }
        if ($user->role === 'admin') {
            return $cache[$ck] = self::keys();
        }
        if ($user->role !== 'subadmin') {
            return $cache[$ck] = [];
        }
        $raw = $user->permissions;
        if ($raw === null || $raw === '') {
            return $cache[$ck] = self::USUAL;
        }
        $list = json_decode((string) $raw, true);
        return $cache[$ck] = self::normalize(is_array($list) ? $list : []);
    }

    /* Has("rental.create"), or any of several: has("rental.edit|replacement.create"). */
    public static function has(string $keys, $user = null): bool
    {
        $have = array_flip(self::granted($user));
        foreach (explode('|', $keys) as $k) {
            if (trim($k) === 'tasks.manage' && !Features::taskAssign()) {
                continue;   // paid extra, off: nobody (not even the Admin) can give tasks to others
            }
            if (isset($have[trim($k)])) {
                return true;
            }
        }
        return false;
    }

    public static function isAdmin($user = null): bool
    {
        $user = $user ?? auth()->user();
        return $user && $user->role === 'admin';
    }

    /* A short line for the Users list. */
    public static function summary($user): string
    {
        if ($user->role === 'admin') {
            return 'Full access';
        }
        $n = count(self::granted($user));
        $all = count(self::keys());
        if ($user->permissions === null || $user->permissions === '') {
            return 'Usual SubAdmin rights (' . $n . ' of ' . $all . ')';
        }
        return $n . ' of ' . $all . ' permissions';
    }
}
