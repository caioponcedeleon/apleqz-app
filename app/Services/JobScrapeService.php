<?php

namespace App\Services;

use App\Enums\JobExtractionEngine;
use App\Enums\JobScrapeStatus;
use App\Jobs\MatchNewListingsJob;
use App\Models\JobSource;
use App\Models\JobSourceScrapeRun;
use RuntimeException;
use Throwable;

class JobScrapeService
{
    public function __construct(
        protected JobSourceFetcher $fetcher,
        protected JobListingExtractor $extractor,
        protected JobListingUpserter $upserter,
    ) {}

    public function scrape(JobSource $source): JobSourceScrapeRun
    {
        $startedAt = now();

        $run = $source->scrapeRuns()->create([
            'started_at' => $startedAt,
            'status' => JobScrapeStatus::Failed,
            'listings_found' => 0,
            'listings_new' => 0,
        ]);

        try {
            $config = $source->extraction_config ?? [];
            $engine = $config['engine'] ?? JobExtractionEngine::Http->value;
            $interactions = $config['interactions'] ?? [];

            if ($engine === JobExtractionEngine::Playwright->value || $interactions !== []) {
                throw new RuntimeException(
                    'Playwright scraping is not available yet. Use engine "http" with no interactions for Phase B.',
                );
            }

            $html = $this->fetcher->fetch($source->url);
            $listings = $this->extractor->extract(
                $html,
                $config,
                $source->url,
                $source->company_name,
            );

            $counts = $this->upserter->upsertMany($source, $listings, $startedAt);

            $run->update([
                'finished_at' => now(),
                'status' => JobScrapeStatus::Success,
                'listings_found' => $counts['found'],
                'listings_new' => $counts['new'],
                'meta' => ['engine' => JobExtractionEngine::Http->value],
            ]);

            $source->update([
                'last_scraped_at' => $startedAt,
                'last_scrape_status' => JobScrapeStatus::Success,
            ]);

            if ($counts['new_listing_ids'] !== []) {
                MatchNewListingsJob::dispatch($counts['new_listing_ids']);
            }
        } catch (Throwable $exception) {
            $run->update([
                'finished_at' => now(),
                'status' => JobScrapeStatus::Failed,
                'error_message' => $exception->getMessage(),
                'meta' => ['engine' => JobExtractionEngine::Http->value],
            ]);

            $source->update([
                'last_scraped_at' => $startedAt,
                'last_scrape_status' => JobScrapeStatus::Failed,
            ]);
        }

        return $run->fresh();
    }
}
