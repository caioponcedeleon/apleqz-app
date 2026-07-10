<?php

namespace Tests\Feature;

use App\Jobs\RunJobAlertsPipelineJob;
use App\Jobs\SendJobDigestsAfterMatchRunJob;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\User;
use App\Models\UserJobProfile;
use App\Models\UserJobSourceSubscription;
use App\Services\JobMatchRunTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunJobAlertsPipelineJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_waits_until_scrape_queue_is_idle(): void
    {
        Config::set('queue.default', 'database');
        Queue::fake();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ScrapeJobSourceJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $job = new RunJobAlertsPipelineJob;
        $job->handle(app(JobMatchRunTracker::class));

        Queue::assertNotPushed(SendJobDigestsAfterMatchRunJob::class);
    }

    public function test_pipeline_dispatches_digest_job_without_backfilling_existing_listings(): void
    {
        Config::set('queue.default', 'database');
        Queue::fake();

        $user = User::factory()->withJobAlertsAi()->create();
        UserJobProfile::query()->create([
            'user_id' => $user->id,
            'profile_text' => 'Software engineer',
            'min_fit_score' => 70,
            'job_alerts_enabled' => true,
        ]);

        $source = JobSource::factory()->create(['is_active' => true]);
        UserJobSourceSubscription::query()->create([
            'user_id' => $user->id,
            'job_source_id' => $source->id,
        ]);
        JobListing::factory()->create(['job_source_id' => $source->id]);

        $job = new RunJobAlertsPipelineJob;
        $job->handle(app(JobMatchRunTracker::class));

        Queue::assertNotPushed(\App\Jobs\EvaluateJobMatchJob::class);
        Queue::assertPushed(SendJobDigestsAfterMatchRunJob::class);
    }
}
