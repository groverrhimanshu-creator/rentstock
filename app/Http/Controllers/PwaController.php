<?php

namespace App\Http\Controllers;

/* The install file (manifest) and the offline page of the mobile app (PWA). Both are public: they hold no data. */
class PwaController extends Controller
{
    public function manifest()
    {
        $name = trim(implode(' ', config('rentstock.brand')));
        return response()->json([
            'name' => $name . ' · Rental & Stock',
            'short_name' => config('rentstock.pwa_short', $name),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#06063A',
            'theme_color' => '#06063A',
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Scan barcode', 'url' => '/scan'],
                ['name' => 'Inventory', 'url' => '/stock/inventory'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function offline()
    {
        return view('offline');
    }
}
