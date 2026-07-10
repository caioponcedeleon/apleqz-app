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

    public function test_create_always_stores_source_as_inactive_even_when_active_is_submitted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('job-sources.store'), [
            'name' => 'Active attempt',
            'url' => 'https://example.com/jobs',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('job_sources', [
            'name' => 'Active attempt',
            'is_active' => false,
        ]);
    }

    public function test_cannot_activate_source_on_edit_before_extraction_is_configured(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'is_active' => false,
            'extraction_config' => JobSource::defaultExtractionConfig(),
        ]);

        $this->actingAs($admin)
            ->put(route('job-sources.update', $source), [
                'name' => $source->name,
                'url' => $source->url,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('is_active');
    }

    public function test_admin_can_toggle_source_active_from_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'is_active' => false,
            'extraction_config' => JobSource::factory()->make()->extraction_config,
        ]);

        $this->actingAs($admin)
            ->from(route('job-sources.index'))
            ->patch(route('job-sources.toggle-active', $source), [
                'is_active' => true,
            ])
            ->assertRedirect(route('job-sources.index'));

        $this->assertTrue($source->fresh()->is_active);
    }

    public function test_toggle_active_rejects_unconfigured_source(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'is_active' => false,
            'extraction_config' => JobSource::defaultExtractionConfig(),
        ]);

        $this->actingAs($admin)
            ->from(route('job-sources.index'))
            ->patch(route('job-sources.toggle-active', $source), [
                'is_active' => true,
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($source->fresh()->is_active);
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

    public function test_admin_can_scrape_job_source_from_ui(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/jobs' => \Illuminate\Support\Facades\Http::response($html, 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                'version' => 1,
                'engine' => 'http',
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('job-sources.scrape', $source))
            ->assertRedirect(route('job-sources.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, JobListing::query()->where('job_source_id', $source->id)->count());
    }

    public function test_admin_can_scrape_job_source_as_json(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        \Illuminate\Support\Facades\Http::fake([
            'https://example.com/jobs' => \Illuminate\Support\Facades\Http::response($html, 200),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'url' => 'https://example.com/jobs',
            'extraction_config' => [
                'version' => 1,
                'engine' => 'http',
                'interactions' => [],
                'listing' => [
                    'item_selector' => 'article.job-card',
                    'fields' => [
                        'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                        'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->postJson(route('job-sources.scrape', $source))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('listings_found', 2)
            ->assertJsonPath('listings_new', 2);
    }

    public function test_admin_can_export_job_sources_as_json_download(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'name' => 'Export Me',
            'url' => 'https://example.com/careers/export-me',
            'company_name' => 'Acme GmbH',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('job-sources.export'));

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $payload = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['schema_version']);
        $this->assertCount(1, $payload['sources']);
        $this->assertSame('Export Me', $payload['sources'][0]['name']);
        $this->assertSame('https://example.com/careers/export-me', $payload['sources'][0]['url']);
        $this->assertSame('Acme GmbH', $payload['sources'][0]['company_name']);
        $this->assertIsArray($payload['sources'][0]['extraction_config']);
    }

    public function test_non_admin_cannot_export_job_sources(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('job-sources.export'))
            ->assertForbidden();
    }

    public function test_admin_can_import_job_sources_from_export_file(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = [
            'schema_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'sources' => [
                [
                    'name' => 'Imported Source',
                    'url' => 'https://example.com/careers/imported',
                    'company_name' => 'Imported Co',
                    'is_active' => true,
                    'extraction_config' => JobSource::factory()->make()->extraction_config,
                    'config_version' => 2,
                ],
            ],
        ];

        $path = tempnam(sys_get_temp_dir(), 'job-sources-import-');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('job-sources.import'), [
                'file' => new \Illuminate\Http\UploadedFile(
                    $path,
                    'job-sources.json',
                    'application/json',
                    null,
                    true,
                ),
            ])
            ->assertRedirect(route('job-sources.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('job_sources', [
            'name' => 'Imported Source',
            'url' => 'https://example.com/careers/imported',
            'company_name' => 'Imported Co',
            'is_active' => true,
            'config_version' => 2,
        ]);
    }

    public function test_import_updates_existing_source_when_url_matches(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create([
            'name' => 'Old Name',
            'url' => 'https://example.com/careers/shared',
            'company_name' => 'Old Co',
            'is_active' => false,
            'config_version' => 1,
        ]);

        $payload = [
            'schema_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'sources' => [
                [
                    'name' => 'New Name',
                    'url' => 'https://example.com/careers/shared',
                    'company_name' => 'New Co',
                    'is_active' => true,
                    'extraction_config' => $source->extraction_config,
                    'config_version' => 4,
                ],
            ],
        ];

        $path = tempnam(sys_get_temp_dir(), 'job-sources-import-');
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('job-sources.import'), [
                'file' => new \Illuminate\Http\UploadedFile(
                    $path,
                    'job-sources.json',
                    'application/json',
                    null,
                    true,
                ),
            ])
            ->assertRedirect(route('job-sources.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('job_sources', [
            'id' => $source->id,
            'name' => 'New Name',
            'company_name' => 'New Co',
            'is_active' => true,
            'config_version' => 4,
            'last_scraped_at' => null,
        ]);
    }

    public function test_import_rejects_invalid_json_file(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $path = tempnam(sys_get_temp_dir(), 'job-sources-import-');
        file_put_contents($path, '{not-json');

        $this->actingAs($admin)
            ->post(route('job-sources.import'), [
                'file' => new \Illuminate\Http\UploadedFile(
                    $path,
                    'job-sources.json',
                    'application/json',
                    null,
                    true,
                ),
            ])
            ->assertSessionHasErrors('file');
    }
}
