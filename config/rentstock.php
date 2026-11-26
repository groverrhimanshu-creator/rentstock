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
    // First barcode number of the series, for example 900001. Empty = start at the lowest barcode in stock (600001 when there is none).
    'barcode_start' => env('BARCODE_START') ? (int) env('BARCODE_START') : null,

    // Paid extras, OFF unless switched on in .env:
    //   ACTIVITY_LOG_ENABLED=true   the Admin's Activity Log page (who did what). The log is still recorded while it is off, so switching it on shows the history.
    //   TASK_ASSIGN_ENABLED=true    give tasks to other logins and see everybody's tasks (without it everybody has only his own list).
    'activity_log' => (bool) env('ACTIVITY_LOG_ENABLED', false),
    'task_assign' => (bool) env('TASK_ASSIGN_ENABLED', false),

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
        'gap' => (float) env('LABEL_GAP', 0),     // space between two labels side by side, mm (0 = labels touch, as on a 2-up roll)
    ],

];
