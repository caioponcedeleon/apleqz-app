<?php

namespace App\Services;

use App\Enums\JobExtractionEngine;
use App\Enums\JobScrapeStatus;
use App\Jobs\MatchNewListingsJob;
use App\Models\JobSource;
use App\Models\JobSourceScrapeRun;
use App\Support\JobScrapePageUrlResolver;
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

            $pagination = is_array($config['pagination'] ?? null) ? $config['pagination'] : ['type' => 'none'];
            $pageResolver = app(JobScrapePageUrlResolver::class);
            $allListings = [];
            $pagesScraped = 0;
            $pageSummaries = [];

            foreach ($pageResolver->pages($source->url, $pagination) as $page) {
                $pageUrl = $page['url'];

                $html = $usePlaywright
                    ? $this->playwrightFetcher->fetch($pageUrl, $interactions)
                    : $this->fetcher->fetch($pageUrl);

                $pageListings = $this->extractor->extract(
                    $html,
                    $config,
                    $pageUrl,
                    $source->company_name,
                );

                $pagesScraped++;
                $pageSummaries[] = [
                    'page' => $page['page'],
                    'found' => count($pageListings),
                ];

                if ($pageListings === [] && $pageResolver->shouldStopAfterEmptyPage($pagination, $pagesScraped)) {
                    break;
                }

                array_push($allListings, ...$pageListings);

                if (($pagination['type'] ?? 'none') === 'none') {
                    break;
                }
            }

            $listings = $allListings;

            $counts = $this->upserter->upsertMany($source, $listings, $startedAt);
            $status = $counts['found'] === 0
                ? JobScrapeStatus::Partial
                : JobScrapeStatus::Success;

            $meta = ['engine' => $resolvedEngine->value];

            if ($pagesScraped > 1 || ($pagination['type'] ?? 'none') !== 'none') {
                $meta['pagination'] = [
                    'type' => $pagination['type'] ?? 'none',
                    'pages_scraped' => $pagesScraped,
                    'pages' => $pageSummaries,
                ];
            }

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
