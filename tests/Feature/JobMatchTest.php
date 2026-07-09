<?php

namespace Tests\Feature;

use App\Enums\JobMatchStatus;
use App\Jobs\EvaluateJobMatchJob;
use App\Jobs\MatchNewListingsJob;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use App\Services\JobMatchEvaluator;
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

        $user = User::factory()->create();
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

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(app(JobMatchEvaluator::class));

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

        $user = User::factory()->create();
        $listing = JobListing::factory()->create();

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Data scientist',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        (new EvaluateJobMatchJob($user->id, $listing->id))->handle(app(JobMatchEvaluator::class));

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
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            UserJobSourceSubscription::query()->create([
                'user_id' => $user->id,
                'job_source_id' => $source->id,
                'is_active' => true,
            ]);
        }

        (new MatchNewListingsJob([$listing->id]))->handle();

        Queue::assertPushed(EvaluateJobMatchJob::class, 2);
    }

    public function test_user_can_view_matches_page(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        $match = JobMatch::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('job-alerts.matches.dismiss', $match))
            ->assertRedirect(route('job-alerts.matches'));

        $this->assertSame(JobMatchStatus::Dismissed, $match->fresh()->status);
    }

    public function test_user_cannot_dismiss_another_users_match(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $match = JobMatch::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->patch(route('job-alerts.matches.dismiss', $match))
            ->assertForbidden();
    }
}
