<?php

namespace App\Services;

use App\Enums\JobExtractionEngine;
use App\Enums\JobScrapeStatus;
use App\Jobs\MatchNewListingsJob;
use App\Models\JobSource;
use App\Models\JobSourceScrapeRun;
use App\Support\RobotsTxtGuard;
use Throwable;

class JobScrapeService
{
    public function __construct(
        protected JobSourceFetcher $fetcher,
        protected PlaywrightPageFetcher $playwrightFetcher,
        protected JobListingExtractor $extractor,
        protected JobListingUpserter $upserter,
        protected RobotsTxtGuard $robotsGuard,
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

        $config = $source->extraction_config ?? [];
        $engine = $config['engine'] ?? JobExtractionEngine::Http->value;
        $interactions = is_array($config['interactions'] ?? null) ? $config['interactions'] : [];
        $usePlaywright = $engine === JobExtractionEngine::Playwright->value || $interactions !== [];
        $resolvedEngine = $usePlaywright
            ? JobExtractionEngine::Playwright
            : JobExtractionEngine::Http;

        try {
            if ($config['respect_robots'] ?? false) {
                $this->robotsGuard->assertAllowed($source->url);
            }

            $html = $usePlaywright
                ? $this->playwrightFetcher->fetch($source->url, $interactions)
                : $this->fetcher->fetch($source->url);
            $listings = $this->extractor->extract(
                $html,
                $config,
                $source->url,
                $source->company_name,
            );

            $counts = $this->upserter->upsertMany($source, $listings, $startedAt);
            $status = $counts['found'] === 0
                ? JobScrapeStatus::Partial
                : JobScrapeStatus::Success;

            $meta = ['engine' => $resolvedEngine->value];

            if ($counts['found'] === 0) {
                $meta['warning'] = 'zero_listings';
            }

            $run->update([
                'finished_at' => now(),
                'status' => $status,
                'listings_found' => $counts['found'],
                'listings_new' => $counts['new'],
                'meta' => $meta,
            ]);

            $source->update([
                'last_scraped_at' => $startedAt,
                'last_scrape_status' => $status,
            ]);

            if ($counts['new_listing_ids'] !== []) {
                MatchNewListingsJob::dispatch($counts['new_listing_ids']);
            }
        } catch (Throwable $exception) {
            $run->update([
                'finished_at' => now(),
                'status' => JobScrapeStatus::Failed,
                'error_message' => $exception->getMessage(),
                'meta' => ['engine' => $resolvedEngine->value],
            ]);

            $source->update([
                'last_scraped_at' => $startedAt,
                'last_scrape_status' => JobScrapeStatus::Failed,
            ]);
        }

        return $run->fresh();
    }
}
