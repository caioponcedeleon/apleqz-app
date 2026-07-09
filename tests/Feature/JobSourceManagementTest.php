<?php

namespace Tests\Feature;

use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\JobSourceScrapeRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_job_sources_in_filament(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create(['name' => 'Acme Careers']);

        $response = $this->actingAs($admin)->get('/admin/job-sources');

        $response->assertOk();
        $response->assertSee('Acme Careers');
    }

    public function test_non_admin_cannot_access_job_sources_in_filament(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/job-sources');

        $response->assertForbidden();
    }

    public function test_job_source_can_have_scrape_runs_and_listings(): void
    {
        $source = JobSource::factory()->create();

        JobSourceScrapeRun::factory()->create([
            'job_source_id' => $source->id,
            'listings_found' => 3,
            'listings_new' => 1,
        ]);

        JobListing::factory()->count(2)->create([
            'job_source_id' => $source->id,
        ]);

        $this->assertCount(1, $source->fresh()->scrapeRuns);
        $this->assertCount(2, $source->fresh()->listings);
    }

    public function test_listings_are_unique_per_source_and_external_id(): void
    {
        $source = JobSource::factory()->create();

        JobListing::factory()->create([
            'job_source_id' => $source->id,
            'external_id' => 'senior-engineer',
        ]);

        $this->expectException(QueryException::class);

        JobListing::factory()->create([
            'job_source_id' => $source->id,
            'external_id' => 'senior-engineer',
        ]);
    }
}
