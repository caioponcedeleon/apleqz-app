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

    public function test_admin_can_view_job_sources_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create(['name' => 'Acme Careers']);

        $this->actingAs($admin)
            ->get(route('job-sources.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/JobSources/Index')
                ->where('jobSources.0.name', 'Acme Careers'));
    }

    public function test_admin_can_open_create_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('job-sources.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/JobSources/Create'));
    }

    public function test_non_admin_cannot_access_job_sources(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('job-sources.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_job_source_and_is_redirected_to_configure(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('job-sources.store'), [
            'name' => 'Uni DuE - Management',
            'url' => 'https://www.uni-due.de/karriere/mtv.php',
            'company_name' => 'Universität Duisburg-Essen',
            'is_active' => false,
        ]);

        $source = JobSource::query()->where('name', 'Uni DuE - Management')->firstOrFail();

        $response->assertRedirect(route('job-sources.configure', $source));

        $this->assertDatabaseHas('job_sources', [
            'name' => 'Uni DuE - Management',
            'is_active' => false,
        ]);
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
