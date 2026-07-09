<?php

namespace App\Console\Commands;

use App\Services\ApplicationReminderDispatchService;
use Illuminate\Console\Command;

class ForceSendApplicationRemindersCommand extends Command
{
    protected $signature = 'applications:force-send-reminders
                            {--without-marking : Send emails without updating sent_at / last_sent_at}';

    protected $description = 'Send all active reminder emails immediately (testing only; ignores schedule)';

    public function handle(ApplicationReminderDispatchService $dispatch): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This command is only available in the local and testing environments.');

            return self::FAILURE;
        }

        $markSent = ! $this->option('without-marking');
        $sentIds = $dispatch->sendAllRemindersForTesting($markSent);

        $count = count($sentIds);
        $this->warn('Force-sent reminders ignore date, time, and frequency rules.');
        $this->info("Sent {$count} reminder(s).");

        if (! $markSent) {
            $this->comment('Reminders were not marked as sent (--without-marking).');
        }

        return self::SUCCESS;
    }
}
