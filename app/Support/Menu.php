<?php

namespace App\Support;

/*
 * The sidebar, exactly as in the final prototype.
 * Admin sees everything. A SubAdmin sees only what the Admin switched on for that login (Users & Permissions). Customer sees three screens.
 * Every screen is built now. The placeholder page (route "page") is only a fallback.
 */
class Menu
{
    /* Screens that exist as placeholders until their step is built: slug => title. */
    public const PAGES = [
        'rental' => 'Rental', 'return' => 'Return', 'replacement' => 'Replacement',
        'consumption' => 'Upgrade Assets', 'barcodes' => 'Print Barcodes', 'payments' => 'Payments & Reminders',
        'reports' => 'Reports', 'portal' => 'My Rentals', 'myinv' => 'Invoices & Challans', 'myret' => 'Return History',
    ];

    private const CUSTOMER_PAGES = ['portal', 'myinv', 'myret'];

    public static function for(string $role): array
    {
        $routes = [
            'rental' => 'rental.index', 'return' => 'return.index', 'replacement' => 'replacement.index', 'consumption' => 'consumption.index',
            'barcodes' => 'barcodes.index', 'payments' => 'payments.index', 'reports' => 'reports.index',
            'portal' => 'portal', 'myinv' => 'myinv', 'myret' => 'myret',
        ];
        $page = fn (string $slug, string $icon = 'box') => ['id' => $slug, 'label' => self::PAGES[$slug], 'icon' => $icon, 'url' => route($routes[$slug])];

        if ($role === 'customer') {
            return [$page('portal', 'rent'), $page('myinv', 'doc'), $page('myret', 'back')];
        }

        $masters = [];
        if (Perm::has('masters.view')) {
            foreach (Masters::defs() as $key => $m) {
                $masters[] = ['id' => 'master:' . $key, 'label' => $m['title'], 'url' => route('masters.index', $key)];
            }
        }
        if (Perm::isAdmin()) {
            $masters[] = ['id' => 'logins', 'label' => 'Customer Logins', 'url' => route('logins.index')];
        }

        $stock = [];
        if (Perm::has('stock.view')) {
            $stock[] = ['id' => 'check', 'label' => 'Stock check', 'url' => route('stock.check')];
            $stock[] = ['id' => 'inventory', 'label' => 'Inventory', 'url' => route('stock.inventory')];
            $stock[] = ['id' => 'entries', 'label' => 'Stock Entries', 'url' => route('stock.entries')];
        }
        if (Perm::has('stock.import')) {
            $stock[] = ['id' => 'import', 'label' => 'Import Stock', 'url' => route('stock.import')];
        }

        // Each entry shows only when this login may open it.
        $menu = [['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'url' => route('dashboard')]];
        if ($masters) {
            $menu[] = ['id' => 'masters', 'label' => 'Masters', 'icon' => 'layers', 'children' => $masters];
        }
        if ($stock) {
            $menu[] = ['id' => 'stock', 'label' => 'Stock', 'icon' => 'box', 'children' => $stock];
        }
        if (Perm::has('purchase.view')) {
            $menu[] = ['id' => 'purchase', 'label' => 'Purchase', 'icon' => 'cart', 'url' => route('purchase.index')];
        }
        if (Perm::has('sale.view')) {
            $menu[] = ['id' => 'sale', 'label' => 'Sale', 'icon' => 'tag', 'url' => route('sale.index')];
        }
        foreach ([['rental', 'rent'], ['return', 'back'], ['replacement', 'swap'], ['consumption', 'chip'], ['barcodes', 'bars'], ['payments', 'rupee'], ['reports', 'chart']] as [$slug, $icon]) {
            if (Perm::has($slug . '.view')) {
                $menu[] = $page($slug, $icon);
            }
        }
        if (Perm::isAdmin()) {
            $menu[] = ['id' => 'users', 'label' => 'Users & Permissions', 'icon' => 'layers', 'url' => route('users.index')];
            $menu[] = ['id' => 'profile', 'label' => 'Company Profile', 'icon' => 'building', 'url' => route('company.edit')];
        }

        if (Features::mobile() && Perm::has('stock.view')) {
            array_splice($menu, 3, 0, [['id' => 'scan', 'label' => 'Scan barcode', 'icon' => 'bars', 'url' => route('scan')]]);
        }

        return $menu;
    }

    public static function canOpenPage(string $role, string $slug): bool
    {
        if (!isset(self::PAGES[$slug])) {
            return false;
        }
        return $role === 'customer' ? in_array($slug, self::CUSTOMER_PAGES, true) : !in_array($slug, self::CUSTOMER_PAGES, true);
    }

    /* Which menu entry is highlighted for the current route. */
    public static function current(): string
    {
        $r = request()->route();
        $name = $r ? (string) $r->getName() : '';
        if ($name === 'dashboard') {
            return 'dashboard';
        }
        if (str_starts_with($name, 'masters.')) {
            return 'master:' . $r->parameter('key');
        }
        if (str_starts_with($name, 'users.')) {
            return 'users';
        }
        if (str_starts_with($name, 'logins.')) {
            return 'logins';
        }
        if (str_starts_with($name, 'company.')) {
            return 'profile';
        }
        if ($name === 'scan') {
            return 'scan';
        }
        if (str_starts_with($name, 'purchase.')) {
            return 'purchase';
        }
        if (str_starts_with($name, 'sale.')) {
            return 'sale';
        }
        if ($name === 'stock.check') {
            return 'check';
        }
        if (str_starts_with($name, 'stock.device') || $name === 'stock.inventory') {
            return 'inventory';
        }
        if (str_starts_with($name, 'stock.entr')) {
            return 'entries';
        }
        if (str_starts_with($name, 'stock.import')) {
            return 'import';
        }
        foreach (['rental.' => 'rental', 'return.' => 'return', 'replacement.' => 'replacement', 'consumption.' => 'consumption', 'barcodes.' => 'barcodes', 'payments.' => 'payments', 'reports.' => 'reports', 'my.' => 'myinv'] as $prefix => $id) {
            if (str_starts_with($name, $prefix)) {
                return $id;
            }
        }
        if (in_array($name, ['portal', 'myinv', 'myret'], true)) {
            return $name;
        }
        if ($name === 'page') {
            return (string) $r->parameter('screen');
        }
        return '';
    }
}
