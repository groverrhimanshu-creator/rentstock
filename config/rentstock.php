<?php

return [
    // Business time zone. Used for dates on invoices, history and reminders.
    'timezone' => env('RENTSTOCK_TIMEZONE', 'Asia/Kolkata'),

    // The two-colour name shown in the sidebar and on the login screen .
    'brand' => ['Rental', 'Solutions'],

    // The SubAdmin role is switched off for now. Set SUBADMIN_ENABLED=true in .env (and run php artisan config:clear) to bring it back.
    'subadmin_enabled' => (bool) env('SUBADMIN_ENABLED', false),

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
