<?php

use Illuminate\Support\Facades\Schedule;

// Payment reminders: every day at 9 in the morning, send the reminders that are due today.
// To run this, the server needs ONE job that calls "php artisan schedule:run" every minute (see INSTALL.txt).
Schedule::command('reminders:send')->dailyAt('09:00');
