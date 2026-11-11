<?php

namespace App\Http\Controllers;

use App\Support\Menu;

/* Screens that are built in a later step. They already sit in the menu, exactly as in the prototype. */
class PageController extends Controller
{
    public function show(string $screen)
    {
        abort_unless(Menu::canOpenPage(auth()->user()->role, $screen), 403, 'You do not have access to this page.');
        return view('page', ['title' => Menu::PAGES[$screen]]);
    }
}
