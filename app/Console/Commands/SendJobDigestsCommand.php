<?php

namespace App\Console\Commands;

use App\Services\JobDigestDispatchService;
use Illuminate\Console\Command;

class SendJobDigestsCommand extends Command
{
    protected $signature = 'jobs:send-digests';

    protected $description = 'Send job alert digest emails for pending matches';

    public function handle(JobDigestDispatchService $dispatch): int
    {
        $sentUserIds = $dispatch->sendPendingDigests();

        $count = count($sentUserIds);
        $this->info("Sent {$count} digest(s).");

        return self::SUCCESS;
    }
}
