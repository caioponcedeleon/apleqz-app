<?php

namespace Tests\Feature;

use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobSourceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobAlertSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_job_alert_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['name' => 'Acme Careers', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('job-alerts.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Settings')
                ->where('profile.min_fit_score', 70)
                ->where('sources.0.name', 'Acme Careers'));
    }

    public function test_user_can_save_profile_and_subscriptions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['is_active' => true]);
        JobSource::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => 'Laravel developer in Germany',
                'min_fit_score' => 80,
                'job_alerts_enabled' => true,
                'subscribed_source_ids' => [$source->id],
            ])
            ->assertRedirect(route('job-alerts.settings'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_job_profiles', [
            'user_id' => $user->id,
            'profile_text' => 'Laravel developer in Germany',
            'min_fit_score' => 80,
            'job_alerts_enabled' => true,
        ]);

        $this->assertDatabaseHas('user_job_source_subscriptions', [
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);
    }

    public function test_unverified_user_cannot_enable_email_alerts(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => 'Backend developer',
                'min_fit_score' => 70,
                'job_alerts_enabled' => true,
                'subscribed_source_ids' => [],
            ])
            ->assertSessionHasErrors('job_alerts_enabled');

        $this->assertDatabaseMissing('user_job_profiles', [
            'user_id' => $user->id,
            'job_alerts_enabled' => true,
        ]);
    }

    public function test_user_cannot_subscribe_to_inactive_source(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $inactive = JobSource::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => '',
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [$inactive->id],
            ])
            ->assertSessionHasErrors('subscribed_source_ids.0');
    }

    public function test_user_can_view_matches_placeholder(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('job-alerts.matches'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Matches')
                ->where('matches', []));
    }

    public function test_unsubscribing_deactivates_existing_subscription(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['is_active' => true]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => '',
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [],
            ])
            ->assertRedirect(route('job-alerts.settings'));

        $this->assertDatabaseHas('user_job_source_subscriptions', [
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => false,
        ]);
    }

    public function test_profile_text_is_limited_to_200_characters(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => str_repeat('a', 201),
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [],
            ])
            ->assertSessionHasErrors('profile_text');
    }

    public function test_job_alerts_index_redirects_to_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('job-alerts.index'))
            ->assertRedirect(route('job-alerts.settings'));
    }
}
