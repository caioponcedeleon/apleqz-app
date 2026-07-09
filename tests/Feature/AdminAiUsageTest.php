<?php

namespace Tests\Feature;

use App\Models\AiUsageRecord;
use App\Models\User;
use App\Services\AiUsageRecorder;
use App\Support\AiUsageContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
