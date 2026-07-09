<?php

namespace App\Console\Commands;

use App\Services\ApplicationReminderDispatchService;
use Illuminate\Console\Command;

class SendApplicationRemindersCommand extends Command
{
    protected $signature = 'applications:send-reminders';

    protected $description = 'Send due application reminder emails';

    public function handle(ApplicationReminderDispatchService $dispatch): int
    {
        $sentIds = $dispatch->sendDueReminders();

        $count = count($sentIds);
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
