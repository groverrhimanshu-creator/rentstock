<?php

namespace App\Support;

/*
 * Master definitions (brands, categories, units, stores, customers, suppliers, items, banks).
 * Each master is one array: the table, the fields, the list columns and the search columns.
 * Field types: text, number, select (fixed options), lookup (rows of another table), state, yesno.
 * One generic controller and two views serve all of them.
 */
class Masters
{
    public static function states(): array
    {
        return [
            'Jammu and Kashmir' => '01', 'Himachal Pradesh' => '02', 'Punjab' => '03', 'Chandigarh' => '04',
            'Uttarakhand' => '05', 'Haryana' => '06', 'Delhi' => '07', 'Rajasthan' => '08', 'Uttar Pradesh' => '09',
            'Bihar' => '10', 'Sikkim' => '11', 'Arunachal Pradesh' => '12', 'Nagaland' => '13', 'Manipur' => '14',
            'Mizoram' => '15', 'Tripura' => '16', 'Meghalaya' => '17', 'Assam' => '18', 'West Bengal' => '19',
            'Jharkhand' => '20', 'Odisha' => '21', 'Chhattisgarh' => '22', 'Madhya Pradesh' => '23', 'Gujarat' => '24',
            'Dadra and Nagar Haveli and Daman and Diu' => '26', 'Maharashtra' => '27', 'Karnataka' => '29', 'Goa' => '30',
            'Lakshadweep' => '31', 'Kerala' => '32', 'Tamil Nadu' => '33', 'Puducherry' => '34',
            'Andaman and Nicobar Islands' => '35', 'Telangana' => '36', 'Andhra Pradesh' => '37', 'Ladakh' => '38',
        ];
    }

    public static function validGstin(string $g): bool
    {
        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g);
    }

    /* Returns an error text for a value that fails a named check, or null when it is fine. Empty values pass. */
    public static function check(string $kind, string $v): ?string
    {
        if ($v === '') {
            return null;
        }
        switch ($kind) {
            case 'gstin':
                return self::validGstin($v) ? null : 'is not a valid GSTIN (15 characters, like 03AAAAA0000A1Z5)';
            case 'phone':
                return preg_match('/^[0-9+\-\s()]{6,30}$/', $v) ? null : 'can contain only digits, spaces, + and -';
            case 'email':
                return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'is not a valid email address';
            case 'digits':
                return preg_match('/^[0-9]+$/', $v) ? null : 'must be digits only';
            case 'ifsc':
                return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $v) ? null : 'is not a valid IFSC (like HDFC0001234)';
            case 'pan':
                return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $v) ? null : 'is not a valid PAN (like ABCDE1234F)';
        }
        return null;
    }

    private static function text(string $name, string $label, int $max, array $extra = []): array
    {
        return ['name' => $name, 'label' => $label, 'type' => 'text', 'max' => $max] + $extra;
    }

    private static function contactFields(string $nameLabel, string $defaultState): array
    {
        return [
            self::text('name', $nameLabel, 150, ['req' => true, 'unique' => true]),
            self::text('address', 'Address', 255),
            self::text('city', 'City', 80),
            ['name' => 'state', 'label' => 'State', 'type' => 'state', 'default' => $defaultState],
            self::text('gstin', 'GSTIN', 15, ['upper' => true, 'check' => 'gstin']),
            self::text('contact', 'Contact person', 100),
            self::text('phone', 'Phone', 30, ['check' => 'phone']),
            self::text('email', 'Email', 150, ['check' => 'email']),
        ];
    }

    /*
     * Screens, fields and list columns follow the final prototype exactly.
     * A column is [field, label, kind]. kind: num (right aligned), money, kind (Barcoded / By quantity).
     * 'count' adds a calculated column that needs the stock tables (shows 0 until they exist).
     */
    public static function defs(): array
    {
        static $d = null;
        if ($d !== null) {
            return $d;
        }
        $d = [
            'brand' => [
                'title' => 'Brand', 'table' => 'brands', 'order' => 'name',
                'fields' => [
                    self::text('name', 'Brand name', 100, ['req' => true, 'unique' => true]),
                    self::text('popular_name', 'Popular name', 100),
                    self::text('description', 'Description', 255),
                ],
                'cols' => [['name', 'Brand name'], ['popular_name', 'Popular name'], ['description', 'Description'], ['items', 'Associated items', 'assoc']],
                'search' => ['name', 'popular_name', 'description'],
                'count' => ['items', '(select count(*) from items x where x.brand_id = brands.id)', true],
            ],
            'category' => [
                'title' => 'Category', 'table' => 'categories', 'order' => 'name',
                'fields' => [
                    self::text('name', 'Category name', 100, ['req' => true, 'unique' => true]),
                    self::text('description', 'Description', 255),
                ],
                'cols' => [['name', 'Category'], ['description', 'Description'], ['items', 'Associated items', 'assoc']],
                'search' => ['name', 'description'],
                'count' => ['items', '(select count(*) from items x where x.category_id = categories.id)', true],
            ],
            'unit' => [
                'title' => 'Unit', 'table' => 'units', 'order' => 'name',
                'fields' => [
                    self::text('name', 'Unit name', 50, ['req' => true, 'unique' => true]),
                    self::text('symbol', 'Symbol', 20),
                    self::text('description', 'Description', 255),
                ],
                'cols' => [['name', 'Unit'], ['symbol', 'Symbol']],
                'search' => ['name', 'symbol', 'description'],
            ],
            'store' => [
                'title' => 'Store', 'table' => 'stores', 'order' => 'name',
                'fields' => [self::text('name', 'Store name', 100, ['req' => true, 'unique' => true])],
                'cols' => [['name', 'Store name'], ['held', 'Items held', 'num']],
                'search' => ['name'],
                'count' => ['held', "(select count(*) from devices d where d.store_id = stores.id and d.status = 'in_stock')"],
            ],
            'customer' => [
                'title' => 'Customer', 'table' => 'customers', 'order' => 'name',
                'fields' => self::contactFields('Customer name', 'Punjab'),
                'cols' => [['name', 'Customer'], ['city', 'City'], ['gstin', 'GSTIN'], ['contact_phone', 'Contact'], ['on_rent', 'On rent', 'num']],
                'search' => ['name', 'address', 'city', 'state', 'gstin', 'contact', 'phone', 'email'],
                'extra' => ["concat(contact, ' ', phone) as contact_phone"],
                'count' => ['on_rent', "(select count(*) from devices d where d.customer_id = customers.id and d.status = 'rented')"],
            ],
            'supplier' => [
                'title' => 'Supplier', 'table' => 'suppliers', 'order' => 'name',
                'fields' => self::contactFields('Supplier name', 'Delhi'),
                'cols' => [['name', 'Supplier'], ['city', 'City'], ['gstin', 'GSTIN'], ['contact_phone', 'Contact']],
                'search' => ['name', 'address', 'city', 'state', 'gstin', 'contact', 'phone', 'email'],
                'extra' => ["concat(contact, ' ', phone) as contact_phone"],
            ],
            'item' => [
                'title' => 'Item', 'table' => 'items', 'alias' => 'i',
                'joins' => [['brands as b', 'b.id', 'i.brand_id'], ['categories as c', 'c.id', 'i.category_id']],
                'select' => ['i.*', 'b.name as brand', 'c.name as category'],
                'order' => 'i.name',
                'fields' => [
                    self::text('name', 'Item name', 150, ['req' => true, 'unique' => true]),
                    ['name' => 'brand_id', 'label' => 'Brand', 'type' => 'lookup', 'from' => 'brands'],
                    ['name' => 'category_id', 'label' => 'Category', 'type' => 'lookup', 'from' => 'categories'],
                    self::text('hsn', 'HSN code', 10, ['check' => 'digits', 'default' => '8471']),
                    ['name' => 'gst_percent', 'label' => 'GST %', 'type' => 'number', 'min' => 0, 'max' => 28, 'default' => '18'],
                    ['name' => 'rent_per_month', 'label' => 'Rent per month', 'type' => 'number', 'min' => 0, 'default' => '0'],
                    ['name' => 'sale_price', 'label' => 'Sale price', 'type' => 'number', 'min' => 0, 'default' => '0'],
                    ['name' => 'tracked', 'label' => 'Kind', 'type' => 'yesno', 'noblank' => true,
                        'yes' => 'Barcoded (one barcode per piece)', 'no' => 'By quantity (no barcode)', 'default' => '1'],
                ],
                'cols' => [['name', 'Item'], ['brand', 'Brand'], ['category', 'Category'], ['hsn', 'HSN'],
                    ['rent_per_month', 'Rent / month', 'money'], ['sale_price', 'Sale price', 'money'], ['tracked', 'Kind', 'kind']],
                'search' => ['i.name', 'b.name', 'c.name', 'i.hsn'],
            ],
            'bank' => [
                'title' => 'Bank', 'table' => 'banks', 'order' => 'bank_name',
                'fields' => [
                    self::text('account_no', 'Account number', 30, ['req' => true, 'unique' => true, 'check' => 'digits']),
                    self::text('bank_name', 'Bank name', 100),
                    self::text('branch', 'Branch', 100),
                    self::text('ifsc', 'IFSC', 11, ['upper' => true, 'check' => 'ifsc']),
                    ['name' => 'account_type', 'label' => 'Account type', 'type' => 'select', 'options' => ['Current', 'Savings'], 'default' => 'Current'],
                ],
                'cols' => [['account_no', 'Account'], ['bank_name', 'Bank'], ['branch', 'Branch'], ['ifsc', 'IFSC']],
                'search' => ['account_no', 'bank_name', 'branch', 'ifsc'],
            ],
        ];
        return $d;
    }

    public static function def(string $key): array
    {
        $d = self::defs();
        if (!isset($d[$key])) {
            abort(404);
        }
        return $d[$key] + ['alias' => null, 'joins' => [], 'select' => ['*'], 'extra' => [], 'count' => null];
    }

    /* The column shown in a dropdown for a lookup table. */
    public static function lookupColumn(string $table): string
    {
        return $table === 'banks' ? 'bank_name' : 'name';
    }
}
