<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Dates in the app follow the business's own time, set in config/rentstock.php.
        date_default_timezone_set(config('rentstock.timezone'));
    }
}
