<?php

namespace App\Console\Commands;

use App\Support\Dues;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'E-mail the payment reminders that are due today (invoices unpaid past their due date).';

    public function handle(): int
    {
        [$sent, $failed] = Dues::sendDue();
        $this->info($sent . ' reminders sent.');
        foreach ($failed as $f) {
            $this->warn($f);
        }
        return self::SUCCESS;
    }
}
