<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\JobMatchStatus;
use App\Jobs\EnrichJobListingDetailJob;
use App\Jobs\EvaluateJobMatchJob;
use App\Jobs\MatchNewListingsJob;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use App\Services\JobListingDetailEnrichmentService;
use App\Services\JobMatchApplicationOverlapChecker;
use App\Services\JobMatchEvaluator;
use App\Services\JobTitlePatternMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeMistralResponse(int $score, string $reason): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'fit_score' => $score,
                                'reason' => $reason,
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_evaluator_parses_mistral_json_response(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
        ]);

        $this->fakeMistralResponse(88, 'Strong Laravel overlap.');

        $listing = JobListing::factory()->create([
            'title' => 'Backend Developer',
            'company' => 'Acme',
            'description' => 'Laravel and PostgreSQL role.',
        ]);

        $result = app(JobMatchEvaluator::class)->evaluate(
            'Laravel developer in Germany',
            $listing,
        );

        $this->assertSame(88, $result['fit_score']);
        $this->assertSame('Strong Laravel overlap.', $result['reason']);
    }

    public function test_evaluate_job_match_job_creates_match_above_threshold(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
        ]);

        $this->fakeMistralResponse(82, 'Good PHP fit.');

        $user = User::factory()->withJobAlertsAi()->create();
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create(['job_source_id' => $source->id]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'PHP developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 82,
            'status' => JobMatchStatus::PendingNotify->value,
        ]);
    }

    public function test_evaluate_job_match_job_skips_below_threshold(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
        ]);

        $this->fakeMistralResponse(45, 'Weak overlap.');

        $user = User::factory()->withJobAlertsAi()->create();
        $listing = JobListing::factory()->create();

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Data scientist',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);
    }

    public function test_evaluate_job_defers_match_when_detail_enrichment_pending(): void
    {
        Queue::fake();

        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
            'job_match.detail_fetch_min_score' => 60,
        ]);

        $this->fakeMistralResponse(82, 'Promising title match.');

        $user = User::factory()->withJobAlertsAi()->create();
        $source = JobSource::factory()->create([
            'is_active' => true,
            'extraction_config' => array_merge(JobSource::defaultExtractionConfig(), [
                'detail' => array_merge(JobSource::defaultDetailConfig(), [
                    'enabled' => true,
                    'fields' => [
                        'description' => [
                            'selector' => 'div.body',
                            'scope' => 'document',
                            'extract' => 'text',
                        ],
                    ],
                ]),
            ]),
        ]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'description' => null,
            'detail_enriched_at' => null,
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'PHP developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        Queue::assertPushed(EnrichJobListingDetailJob::class, function (EnrichJobListingDetailJob $job) use ($listing): bool {
            return $job->listingId === $listing->id;
        });

        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);
    }

    public function test_match_new_listings_job_dispatches_per_subscriber(): void
    {
        Queue::fake();

        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create(['job_source_id' => $source->id]);
        $users = User::factory()->withJobAlertsAi()->count(2)->create();

        foreach ($users as $user) {
            UserJobSourceSubscription::query()->create([
                'user_id' => $user->id,
                'job_source_id' => $source->id,
                'is_active' => true,
            ]);
        }

        (new MatchNewListingsJob([$listing->id]))->handle(app(JobMatchApplicationOverlapChecker::class));

        Queue::assertPushed(EvaluateJobMatchJob::class, 2);
    }

    public function test_match_new_listings_job_only_evaluates_passed_listing_ids(): void
    {
        Queue::fake();

        $source = JobSource::factory()->create(['is_active' => true]);
        $newListing = JobListing::factory()->create(['job_source_id' => $source->id]);
        $existingListing = JobListing::factory()->create(['job_source_id' => $source->id]);
        $user = User::factory()->withJobAlertsAi()->create();

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        (new MatchNewListingsJob([$newListing->id]))->handle(app(JobMatchApplicationOverlapChecker::class));

        Queue::assertPushed(EvaluateJobMatchJob::class, function (EvaluateJobMatchJob $job) use ($user, $newListing, $existingListing): bool {
            return $job->userId === $user->id
                && $job->listingId === $newListing->id
                && $job->listingId !== $existingListing->id;
        });
        Queue::assertPushed(EvaluateJobMatchJob::class, 1);
    }

    public function test_evaluate_job_match_job_skips_when_user_already_has_application_for_listing_url(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
        ]);

        Http::fake();

        $user = User::factory()->withJobAlertsAi()->create();
        $listing = JobListing::factory()->create([
            'title' => 'Backend Developer',
            'url' => 'https://example.com/jobs/backend',
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'position' => 'Different title',
            'job_url' => 'https://example.com/jobs/backend/',
            'applied_at' => now()->subDay(),
            'status' => ApplicationStatus::Waiting,
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'PHP developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        Http::assertNothingSent();
        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);
    }

    public function test_evaluate_job_match_job_skips_when_user_already_has_application_for_position_title(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.api_key' => 'test-key',
        ]);

        Http::fake();

        $user = User::factory()->withJobAlertsAi()->create();
        $listing = JobListing::factory()->create([
            'title' => 'Policy Analyst',
            'url' => 'https://example.com/jobs/999',
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'position' => '  policy   analyst ',
            'job_url' => null,
            'applied_at' => now()->subDay(),
            'status' => ApplicationStatus::Waiting,
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Public policy',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        Http::assertNothingSent();
        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);
    }

    public function test_match_new_listings_job_skips_users_with_overlapping_applications(): void
    {
        Queue::fake();

        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Research Assistant',
            'url' => 'https://example.com/jobs/research',
        ]);
        $user = User::factory()->withJobAlertsAi()->create();

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        Application::factory()->create([
            'user_id' => $user->id,
            'position' => 'Research Assistant',
            'job_url' => null,
            'applied_at' => now()->subDay(),
            'status' => ApplicationStatus::Waiting,
        ]);

        (new MatchNewListingsJob([$listing->id]))->handle(app(JobMatchApplicationOverlapChecker::class));

        Queue::assertNotPushed(EvaluateJobMatchJob::class);
    }

    public function test_regex_tier_creates_match_when_title_matches_include_keyword(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create();
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Senior Policy Analyst',
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'include_keywords' => "analyst\ndeveloper",
            'exclude_keywords' => 'intern',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(
            app(JobMatchEvaluator::class),
            app(JobTitlePatternMatcher::class),
            app(JobListingDetailEnrichmentService::class),
            app(JobMatchApplicationOverlapChecker::class),
        );

        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 100,
            'status' => JobMatchStatus::PendingNotify->value,
        ]);
    }

    public function test_user_can_view_matches_page(): void
    {
        $user = User::factory()->withJobAlertsAi()->create();
        $match = JobMatch::factory()->create([
            'user_id' => $user->id,
            'fit_score' => 91,
            'reason' => 'Excellent fit.',
        ]);

        $this->actingAs($user)
            ->get(route('job-alerts.matches'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Matches')
                ->where('matches.0.id', $match->id)
                ->where('matches.0.fit_score', 91));
    }

    public function test_user_can_dismiss_match(): void
    {
        $user = User::factory()->withJobAlertsAi()->create();
        $match = JobMatch::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('job-alerts.matches.dismiss', $match))
            ->assertRedirect(route('job-alerts.matches'));

        $this->assertSame(JobMatchStatus::Dismissed, $match->fresh()->status);
    }

    public function test_user_cannot_dismiss_another_users_match(): void
    {
        $owner = User::factory()->withJobAlertsAi()->create();
        $other = User::factory()->withJobAlertsAi()->create();
        $match = JobMatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->patch(route('job-alerts.matches.dismiss', $match))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_run_matches(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->post(route('job-alerts.matches.run'))
            ->assertForbidden();
    }

    public function test_admin_can_run_regex_matches_for_subscribed_listings(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        $source = JobSource::factory()->create(['is_active' => true]);
        $matchingListing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Research Assistant (f/m/d)',
        ]);
        JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Marketing Intern',
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'include_keywords' => 'assistant',
            'exclude_keywords' => 'intern',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('job-alerts.matches.run'))
            ->assertRedirect(route('job-alerts.matches'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $matchingListing->id,
            'fit_score' => 100,
        ]);

        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => JobListing::query()
                ->where('title', 'Marketing Intern')
                ->value('id'),
        ]);
    }

    public function test_admin_run_matches_shows_warning_when_nothing_to_evaluate(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'include_keywords' => '',
            'exclude_keywords' => '',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        $this->actingAs($user)
            ->post(route('job-alerts.matches.run'))
            ->assertRedirect(route('job-alerts.matches'))
            ->assertSessionHas('warning');
    }

    public function test_matches_page_shows_run_button_for_admin_only(): void
    {
        $admin = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        $user = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('job-alerts.matches'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Matches')
                ->where('canRunMatches', true));

        $this->actingAs($user)
            ->get(route('job-alerts.matches'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Matches')
                ->where('canRunMatches', false));
    }

    public function test_admin_force_rematch_removes_stale_ai_match_excluded_by_regex_rule(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create([
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Post Doc Position (f/m/d, No. 320-26)',
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'include_keywords' => 'mitarbeiter',
            'exclude_keywords' => "post doc\nintern*",
            'min_fit_score' => 85,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 85,
            'reason' => 'Strong match for research position in Ruhr area.',
            'evaluation_cache_key' => 'stale-ai-cache-key',
        ]);

        $this->actingAs($user)
            ->post(route('job-alerts.matches.run'))
            ->assertRedirect(route('job-alerts.matches'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);
    }

    public function test_user_can_preview_own_job_match_listing(): void
    {
        Http::fake([
            'https://example.com/jobs/1' => Http::response(
                '<html><body><h1>Research Assistant</h1><script>alert(1)</script></body></html>',
                200,
            ),
        ]);

        $user = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Research Assistant',
            'url' => 'https://example.com/jobs/1',
        ]);
        $match = JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('job-alerts.matches.preview', $match))
            ->assertOk()
            ->assertJsonPath('title', 'Research Assistant')
            ->assertJsonPath('url', 'https://example.com/jobs/1');

        $html = (string) $response->json('html');

        $this->assertStringContainsString('Research Assistant', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('job-source-picker.js', $html);
    }

    public function test_user_cannot_preview_another_users_job_match(): void
    {
        $owner = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);
        $other = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);
        $match = JobMatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson(route('job-alerts.matches.preview', $match))
            ->assertForbidden();
    }
}
