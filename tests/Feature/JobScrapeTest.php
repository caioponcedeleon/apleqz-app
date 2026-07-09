<?php

namespace Tests\Feature;

use App\Enums\JobExtractionEngine;
use App\Enums\JobScrapeStatus;
use App\Jobs\ScrapeJobSourceJob;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Services\JobScrapeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobScrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrape_service_persists_listings_from_http_source(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        Http::fake([
            'https://example.com/jobs' => Http::response($html, 200),
        ]);

        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'company_name' => 'Acme GmbH',
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                        'location' => ['selector' => '.location', 'scope' => 'item', 'extract' => 'text', 'optional' => true],
                    ],
                ],
            ],
        ]);

        $run = app(JobScrapeService::class)->scrape($source);

        $this->assertSame(JobScrapeStatus::Success, $run->status);
        $this->assertSame(2, $run->listings_found);
        $this->assertSame(2, $run->listings_new);
        $this->assertDatabaseCount('job_listings', 2);
        $this->assertSame(JobScrapeStatus::Success, $source->fresh()->last_scrape_status);
    }

    public function test_second_scrape_does_not_create_duplicate_listings(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        Http::fake([
            'https://example.com/jobs' => Http::response($html, 200),
        ]);

        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    ],
                ],
            ],
        ]);

        $scraper = app(JobScrapeService::class);
        $scraper->scrape($source);
        $secondRun = $scraper->scrape($source);

        $this->assertSame(2, $secondRun->listings_found);
        $this->assertSame(0, $secondRun->listings_new);
        $this->assertDatabaseCount('job_listings', 2);
    }

    public function test_playwright_sources_fail_until_phase_d(): void
    {
        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                ...JobSource::defaultExtractionConfig(),
                'engine' => JobExtractionEngine::Playwright->value,
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [],
                ],
            ],
        ]);

        $run = app(JobScrapeService::class)->scrape($source);

        $this->assertSame(JobScrapeStatus::Failed, $run->status);
        $this->assertStringContainsString('Playwright', $run->error_message ?? '');
        $this->assertDatabaseCount('job_listings', 0);
    }

    public function test_scrape_sources_command_dispatches_jobs_for_active_sources(): void
    {
        Queue::fake();

        $active = JobSource::factory()->create(['is_active' => true]);
        JobSource::factory()->inactive()->create();

        $this->artisan('jobs:scrape-sources')->assertSuccessful();

        Queue::assertPushed(ScrapeJobSourceJob::class, fn (ScrapeJobSourceJob $job) => $job->jobSource->is($active));
        Queue::assertPushed(ScrapeJobSourceJob::class, 1);
    }

    public function test_scrape_source_command_runs_single_source(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        Http::fake([
            'https://example.com/jobs' => Http::response($html, 200),
        ]);

        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    ],
                ],
            ],
        ]);

        $this->artisan('jobs:scrape-source', ['jobSource' => $source->id])
            ->assertSuccessful();

        $this->assertSame(2, JobListing::query()->where('job_source_id', $source->id)->count());
    }
}
