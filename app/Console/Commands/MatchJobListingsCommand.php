<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateJobMatchJob;
use App\Jobs\MatchNewListingsJob;
use App\Models\JobListing;
use Illuminate\Console\Command;

class MatchJobListingsCommand extends Command
{
    protected $signature = 'jobs:match-listings
        {listing?* : Job listing UUIDs to match}
        {--user= : Limit evaluation to a single user ID}';

    protected $description = 'Dispatch AI match evaluation for job listings (testing / backfill)';

    public function handle(): int
    {
        /** @var list<string> $listingIds */
        $listingIds = $this->argument('listing');

        if ($listingIds === []) {
            $listingIds = JobListing::query()
                ->orderByDesc('first_seen_at')
                ->limit(20)
                ->pluck('id')
                ->all();

            if ($listingIds === []) {
                $this->warn('No listings found. Scrape a source first or pass listing UUIDs.');

                return self::FAILURE;
            }

            $this->info('No listing IDs passed — using the '.count($listingIds).' most recent listing(s).');
        }

        $userId = $this->option('user');

        if (is_string($userId) && $userId !== '') {
            foreach ($listingIds as $listingId) {
                EvaluateJobMatchJob::dispatch((int) $userId, $listingId);
                $this->line("Dispatched evaluation for user {$userId} × listing {$listingId}");
            }

            $this->info('Done.');

            return self::SUCCESS;
        }

        MatchNewListingsJob::dispatch($listingIds);
        $this->info('Dispatched match jobs for '.count($listingIds).' listing(s).');

        return self::SUCCESS;
    }
}
