<?php

namespace Tests\Feature;

use App\Enums\JobExtractionEngine;
use App\Enums\JobMatchStatus;
use App\Enums\JobScrapeStatus;
use App\Models\ApplicationWave;
use App\Models\Area;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\JobSource;
use App\Models\JobSourceConfigRevision;
use App\Models\User;
use App\Services\JobScrapeService;
use App\Services\JobSourceConfigRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobAlertsHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrape_marks_partial_when_zero_listings_extracted(): void
    {
        Queue::fake();

        Http::fake([
            'https://example.com/jobs' => Http::response('<html><body><p>No jobs here</p></body></html>', 200),
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
                        'job_title' => ['selector' => 'h2', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    ],
                ],
            ],
        ]);

        $run = app(JobScrapeService::class)->scrape($source);

        $this->assertSame(JobScrapeStatus::Partial, $run->status);
        $this->assertSame(0, $run->listings_found);
        $this->assertSame('zero_listings', $run->meta['warning'] ?? null);
        $this->assertSame(JobScrapeStatus::Partial, $source->fresh()->last_scrape_status);
    }

    public function test_scrape_respects_robots_txt_when_enabled(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /jobs", 200),
        ]);

        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'respect_robots' => true,
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article',
                    'fields' => [],
                ],
            ],
        ]);

        $run = app(JobScrapeService::class)->scrape($source);

        $this->assertSame(JobScrapeStatus::Failed, $run->status);
        $this->assertStringContainsString('robots.txt', $run->error_message ?? '');
    }

    public function test_config_revision_is_stored_before_update(): void
    {
        $source = JobSource::factory()->create([
            'config_version' => 2,
            'extraction_config' => [
                'version' => 1,
                'engine' => JobExtractionEngine::Http->value,
                'listing' => ['item_selector' => '.old', 'fields' => []],
            ],
        ]);

        $newConfig = [
            'version' => 1,
            'engine' => JobExtractionEngine::Http->value,
            'listing' => ['item_selector' => '.new', 'fields' => []],
        ];

        app(JobSourceConfigRevisionService::class)->snapshotBeforeUpdate($source, $newConfig);

        $this->assertDatabaseHas('job_source_config_revisions', [
            'job_source_id' => $source->id,
            'config_version' => 2,
        ]);

        $revision = JobSourceConfigRevision::query()->first();
        $this->assertSame('.old', $revision->extraction_config['listing']['item_selector'] ?? null);
    }

    public function test_user_can_start_application_from_job_match(): void
    {
        $user = User::factory()->withJobAlertsAi()->create();
        ApplicationWave::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $area = Area::factory()->create(['user_id' => $user->id]);
        $listing = JobListing::factory()->create([
            'title' => 'Policy Analyst',
            'company' => 'City Council',
            'url' => 'https://example.com/jobs/policy-analyst',
            'location' => 'Essen',
        ]);
        $match = JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);

        $this->actingAs($user)
            ->get(route('job-alerts.matches.apply', $match))
            ->assertRedirect(route('applications.create', [
                'position' => 'Policy Analyst',
                'company' => 'City Council',
                'location' => 'Essen',
                'job_url' => 'https://example.com/jobs/policy-analyst',
                'job_match_id' => $match->id,
            ]));

        $this->actingAs($user)
            ->get(route('applications.create', [
                'position' => 'Policy Analyst',
                'company' => 'City Council',
                'job_url' => 'https://example.com/jobs/policy-analyst',
                'job_match_id' => $match->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Applications/Form')
                ->where('prefill.position', 'Policy Analyst')
                ->where('prefill.company', 'City Council')
                ->where('prefill.job_match_id', (string) $match->id));

        $wave = $user->applicationWaves()->first();

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'area_id' => $area->id,
                'application_wave_id' => $wave->id,
                'position' => 'Policy Analyst',
                'company' => 'City Council',
                'location' => 'Essen',
                'status' => 'a_candidatar',
                'job_url' => 'https://example.com/jobs/policy-analyst',
                'job_match_id' => $match->id,
            ])
            ->assertRedirect();

        $this->assertSame(JobMatchStatus::Applied, $match->fresh()->status);
    }
}
