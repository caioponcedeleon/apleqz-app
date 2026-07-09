<?php

namespace Tests\Feature;

use App\Models\JobSource;
use App\Models\User;
use App\Services\JobSourcePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JobSourcePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_service_blocks_localhost_urls(): void
    {
        $this->expectException(ValidationException::class);

        app(JobSourcePreviewService::class)->prepare('http://localhost/jobs');
    }

    public function test_preview_service_fetches_and_sanitizes_html(): void
    {
        Http::fake([
            'https://example.com/jobs' => Http::response(
                '<html><body><article class="job-card"><a href="/jobs/1" onclick="x()">Engineer</a></article><script>bad()</script></body></html>',
                200,
            ),
        ]);

        $html = app(JobSourcePreviewService::class)->prepare('https://example.com/jobs');

        $this->assertStringContainsString('job-card', $html);
        $this->assertStringContainsString('https://example.com/jobs/1', $html);
        $this->assertStringNotContainsString('onclick', strtolower($html));
        $this->assertStringContainsString('job-source-picker.js', $html);
    }

    public function test_admin_can_access_configure_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $source = JobSource::factory()->create();

        $this->actingAs($admin)
            ->get(route('job-sources.configure', $source))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/JobSources/Configure')
                ->has('jobSource')
                ->has('fieldOptions'));
    }

    public function test_non_admin_cannot_access_configure_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $source = JobSource::factory()->create();

        $this->actingAs($user)
            ->get(route('job-sources.configure', $source))
            ->assertForbidden();
    }

    public function test_preview_endpoint_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->postJson(route('job-sources.preview'), [
                'url' => 'https://example.com/jobs',
            ])
            ->assertForbidden();
    }

    public function test_preview_endpoint_blocks_private_ips(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('job-sources.preview'), [
                'url' => 'http://127.0.0.1/jobs',
            ])
            ->assertUnprocessable();
    }

    public function test_test_extraction_endpoint_returns_listings_from_html(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));

        $this->actingAs($admin)
            ->postJson(route('job-sources.test-extraction'), [
                'html' => $html,
                'base_url' => 'https://example.com/jobs',
                'company_name' => 'Acme GmbH',
                'item_selector' => 'article.job-card',
                'fields' => [
                    'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                    'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('listings.0.title', 'Senior Engineer');
    }
}
