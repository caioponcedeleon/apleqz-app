<?php

namespace Tests\Feature;

use App\Enums\JobAlertsTier;
use App\Enums\JobMatchStatus;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobAlertSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_job_alerts_tier_cannot_access_settings(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('job-alerts.settings'))
            ->assertForbidden();
    }

    public function test_user_can_view_job_alert_settings(): void
    {
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['name' => 'Acme Careers', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('job-alerts.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Settings')
                ->where('tier', JobAlertsTier::Ai->value)
                ->where('isAiTier', true)
                ->where('profile.min_fit_score', 70)
                ->where('sources.0.name', 'Acme Careers'));
    }

    public function test_user_can_save_ai_profile_and_subscriptions(): void
    {
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);
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

    public function test_regex_tier_user_can_autosave_keyword_rules_without_flash(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'include_keywords' => "developer\n/data engineer/i",
                'exclude_keywords' => 'intern',
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [],
                'autosave' => true,
            ])
            ->assertRedirect(route('job-alerts.settings'))
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('user_job_profiles', [
            'user_id' => $user->id,
            'include_keywords' => "developer\n/data engineer/i",
            'exclude_keywords' => 'intern',
        ]);
    }

    public function test_regex_tier_user_can_save_keyword_rules(): void
    {
        $user = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->patch(route('job-alerts.settings.update'), [
                'include_keywords' => "developer\nanalyst",
                'exclude_keywords' => 'intern',
                'min_fit_score' => 70,
                'job_alerts_enabled' => true,
                'subscribed_source_ids' => [],
            ])
            ->assertRedirect(route('job-alerts.settings'));

        $this->assertDatabaseHas('user_job_profiles', [
            'user_id' => $user->id,
            'include_keywords' => "developer\nanalyst",
            'exclude_keywords' => 'intern',
        ]);
    }

    public function test_unverified_user_cannot_enable_email_alerts(): void
    {
        $user = User::factory()->withJobAlertsAi()->unverified()->create();

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
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);
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
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('job-alerts.matches'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('JobAlerts/Matches')
                ->where('matches', []));
    }

    public function test_unsubscribing_deactivates_existing_subscription(): void
    {
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);
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

    public function test_profile_text_is_limited_to_1000_characters(): void
    {
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => str_repeat('a', 1001),
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [],
            ])
            ->assertSessionHasErrors('profile_text');

        $this->actingAs($user)
            ->patch(route('job-alerts.settings.update'), [
                'profile_text' => str_repeat('a', 1000),
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [],
            ])
            ->assertRedirect(route('job-alerts.settings'))
            ->assertSessionHasNoErrors();
    }

    public function test_job_alerts_index_redirects_to_settings(): void
    {
        $user = User::factory()->withJobAlertsAi()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('job-alerts.index'))
            ->assertRedirect(route('job-alerts.settings'));
    }

    public function test_regex_keyword_autosave_rematches_recent_listings(): void
    {
        config(['job_match.regex.recent_listings_per_source' => 5]);

        $user = User::factory()->withJobAlertsRegex()->create(['email_verified_at' => now()]);
        $source = JobSource::factory()->create(['is_active' => true]);
        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'title' => 'Wissenschaftliche*r Mitarbeiter*in',
            'first_seen_at' => now()->subDay(),
        ]);

        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'include_keywords' => 'developer',
            'exclude_keywords' => '',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('job-alerts.settings'))
            ->patch(route('job-alerts.settings.update'), [
                'include_keywords' => 'wissenschaftlich*',
                'exclude_keywords' => 'intern',
                'min_fit_score' => 70,
                'job_alerts_enabled' => false,
                'subscribed_source_ids' => [$source->id],
                'autosave' => true,
            ])
            ->assertRedirect(route('job-alerts.settings'));

        $this->assertDatabaseHas('job_matches', [
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'fit_score' => 100,
            'status' => JobMatchStatus::PendingNotify->value,
        ]);
    }
}
