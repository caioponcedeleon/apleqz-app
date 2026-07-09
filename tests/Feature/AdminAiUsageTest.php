<?php

namespace Tests\Feature;

use App\Jobs\EvaluateJobMatchJob;
use App\Models\AiUsageRecord;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use App\Services\AiUsageRecorder;
use App\Support\AiUsageContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminAiUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_administration_hub(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('administration.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Index'));
    }

    public function test_admin_can_view_ai_usage_page(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.model' => 'ministral-3b-latest',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Jane Doe']);

        AiUsageRecord::query()->create([
            'driver' => 'mistral_cloud',
            'model' => 'ministral-3b-latest',
            'purpose' => 'job_match',
            'prompt_tokens' => 120,
            'completion_tokens' => 30,
            'total_tokens' => 150,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('administration.ai-usage'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AiUsage')
                ->where('summary.prompt_tokens', 120)
                ->where('summary.completion_tokens', 30)
                ->where('recent.0.user.name', 'Jane Doe')
                ->where('pricingAvailable', true));
    }

    public function test_non_admin_cannot_access_administration_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('administration.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('administration.ai-usage'))
            ->assertForbidden();
    }

    public function test_usage_recorder_stores_tokens_with_context(): void
    {
        $user = User::factory()->create();

        AiUsageContext::run([
            'user_id' => $user->id,
            'purpose' => 'job_match',
        ], function (): void {
            app(AiUsageRecorder::class)->record('mistral_cloud', 'ministral-3b-latest', [
                'prompt_tokens' => 200,
                'completion_tokens' => 50,
                'total_tokens' => 250,
            ]);
        });

        $this->assertDatabaseHas('ai_usage_records', [
            'driver' => 'mistral_cloud',
            'model' => 'ministral-3b-latest',
            'purpose' => 'job_match',
            'prompt_tokens' => 200,
            'completion_tokens' => 50,
            'total_tokens' => 250,
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_preview_and_dispatch_match_backfill(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.model' => 'ministral-3b-latest',
        ]);

        Queue::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create(['job_source_id' => $source->id]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'PHP developer in Germany',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('administration.ai-usage.match-preview'))
            ->assertOk()
            ->assertJson([
                'evaluations' => 1,
                'listings' => 1,
                'users' => 1,
            ]);

        $this->actingAs($admin)
            ->postJson(route('administration.ai-usage.run-matches'))
            ->assertOk()
            ->assertJsonStructure(['run_id', 'total']);

        Queue::assertPushed(EvaluateJobMatchJob::class, 1);
    }

    public function test_admin_can_poll_match_run_status(): void
    {
        config([
            'job_match.driver' => 'mistral_cloud',
            'job_match.mistral.model' => 'ministral-3b-latest',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create(['job_source_id' => $source->id]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'PHP developer in Germany',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        $runId = app(\App\Services\JobMatchRunTracker::class)->start(1);

        $this->actingAs($admin)
            ->getJson(route('administration.ai-usage.match-status', ['run' => $runId]))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'total' => 1,
            ]);
    }

    public function test_admin_match_backfill_json_reports_nothing_to_queue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->getJson(route('administration.ai-usage.match-preview'))
            ->assertOk()
            ->assertJson(['evaluations' => 0]);

        $this->actingAs($admin)
            ->postJson(route('administration.ai-usage.run-matches'))
            ->assertStatus(422);
    }

    public function test_admin_match_backfill_redirect_reports_nothing_to_queue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('administration.ai-usage.run-matches'))
            ->assertRedirect(route('administration.ai-usage'))
            ->assertSessionHas('warning');
    }
}
