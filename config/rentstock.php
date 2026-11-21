<?php

return [
    // Business time zone. Used for dates on invoices, history and reminders.
    'timezone' => env('RENTSTOCK_TIMEZONE', 'Asia/Kolkata'),

    // The mobile app (install on the phone, bottom bar, Scan page). Set PWA_ENABLED=false in .env to switch it off.
    'pwa' => (bool) env('PWA_ENABLED', true),
    // Only these usernames get the mobile app and camera scan (comma separated, for example PWA_USERS=demo). Empty = everybody.
    'pwa_users' => array_values(array_filter(array_map(fn ($x) => strtolower(trim($x)), explode(',', (string) env('PWA_USERS', ''))))),

    // The two-colour name shown in the sidebar and on the login screen .
    'brand' => ['Rental', 'Solutions'],

    // SubAdmin logins are created and given their permissions on the Users page (Admin only). Set SUBADMIN_ENABLED=false in .env to switch every SubAdmin login off.
    'subadmin_enabled' => (bool) env('SUBADMIN_ENABLED', true),

    // HSN / SAC code printed on every rent line (renting of IT equipment). Sold items keep the HSN of the item.
    'rent_sac' => env('RENT_SAC', '997315'),

    // Barcode sticker. The first line printed on every label, and the label size in millimetres.
    // Change these here (or in .env) if the client's label roll is a different size.
    'label' => [
        'title' => env('LABEL_TITLE', 'KPI SOLUTIONS'),
        'w' => (float) env('LABEL_W', 50),
        'h' => (float) env('LABEL_H', 25),
        'cols' => (int) env('LABEL_COLS', 2),     // labels side by side on the roll
        'gap' => (float) env('LABEL_GAP', 3),     // space between two labels side by side, mm
    ],

];
