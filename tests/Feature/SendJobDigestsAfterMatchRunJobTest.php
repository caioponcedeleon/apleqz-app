<?php

namespace Tests\Feature;

use App\Enums\JobMatchStatus;
use App\Jobs\SendJobDigestsAfterMatchRunJob;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Notifications\JobMatchesDigestNotification;
use App\Services\JobMatchRunTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendJobDigestsAfterMatchRunJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_job_waits_until_match_queue_is_idle(): void
    {
        Config::set('queue.default', 'database');
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Remote developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        $listing = JobListing::factory()->create();
        JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\EvaluateJobMatchJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $job = new SendJobDigestsAfterMatchRunJob;
        $job->handle(
            app(\App\Services\JobDigestDispatchService::class),
            app(JobMatchRunTracker::class),
        );

        Notification::assertNothingSent();
    }

    public function test_digest_job_sends_pending_notifications_when_queue_is_idle(): void
    {
        Config::set('queue.default', 'database');
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Remote developer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        $listing = JobListing::factory()->create();
        JobMatch::factory()->create([
            'user_id' => $user->id,
            'job_listing_id' => $listing->id,
            'status' => JobMatchStatus::PendingNotify,
        ]);

        $job = new SendJobDigestsAfterMatchRunJob;
        $job->handle(
            app(\App\Services\JobDigestDispatchService::class),
            app(JobMatchRunTracker::class),
        );

        Notification::assertSentTo($user, JobMatchesDigestNotification::class);
    }
}
