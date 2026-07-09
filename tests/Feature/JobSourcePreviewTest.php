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

        app(JobSourcePreviewService::class)->prepare('http://localhost/jobs')['html'];
    }

    public function test_preview_service_fetches_and_sanitizes_html(): void
    {
        Http::fake([
            'https://example.com/jobs' => Http::response(
                '<html><body><article class="job-card"><a href="/jobs/1" onclick="x()">Engineer</a></article><script>bad()</script></body></html>',
                200,
            ),
        ]);

        $result = app(JobSourcePreviewService::class)->prepare('https://example.com/jobs');
        $html = $result['html'];

        $this->assertSame('http', $result['rendered_with']);
        $this->assertFalse($result['suggest_playwright']);
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
                ->has('fieldOptions')
                ->has('detailFieldOptions')
                ->where('detailFieldOptions.job_title', __('app.job_sources.detail_fields.job_title'))
                ->where(
                    'detailFieldOptions',
                    fn ($options): bool => ! $options->has('url'),
                ));
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

    public function test_preview_endpoint_returns_iso_8859_1_html_as_utf8_json(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = mb_convert_encoding(
            '<html><body><article class="job-card">München</article></body></html>',
            'ISO-8859-1',
            'UTF-8',
        );

        Http::fake([
            'https://example.com/jobs' => Http::response(
                $html,
                200,
                ['Content-Type' => 'text/html; Charset=iso-8859-1'],
            ),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('job-sources.preview'), [
                'url' => 'https://example.com/jobs',
            ])
            ->assertOk();

        $html = $response->json('html');

        $this->assertIsString($html);
        $this->assertStringContainsString('job-card', $html);
        $this->assertStringContainsString('München', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
            ->assertJsonPath('listings.0.fields.job_title', 'Senior Engineer')
            ->assertJsonPath('listings.0.fields.url', 'https://example.com/jobs/senior-engineer');
    }

    public function test_test_extraction_endpoint_returns_detail_fields_without_list_item_selector(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $html = <<<'HTML'
        <html><body>
            <h1>Studentische Hilfskraft</h1>
            <p class="deadline">Bewerbungsfrist: 31.07.2026</p>
            <div class="description">Assist with research projects.</div>
        </body></html>
        HTML;

        $this->actingAs($admin)
            ->postJson(route('job-sources.test-extraction'), [
                'html' => $html,
                'base_url' => 'https://example.com/jobs/1',
                'extraction_type' => 'detail',
                'fields' => [
                    'application_deadline' => [
                        'selector' => 'p.deadline',
                        'scope' => 'document',
                        'extract' => 'text',
                    ],
                    'description' => [
                        'selector' => 'div.description',
                        'scope' => 'document',
                        'extract' => 'text',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('listings.0.fields.application_deadline', 'Bewerbungsfrist: 31.07.2026')
            ->assertJsonPath('listings.0.fields.description', 'Assist with research projects.');
    }

    public function test_preview_endpoint_suggests_playwright_for_dynamic_jobboard_placeholder(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Http::fake([
            'https://example.com/jobs' => Http::response(
                '<html><body><div class="jobboard-datatable" data-widget="jobboardDatatable"><div>Bitte warten...</div></div></body></html>',
                200,
            ),
        ]);

        $this->actingAs($admin)
            ->postJson(route('job-sources.preview'), [
                'url' => 'https://example.com/jobs',
                'engine' => 'http',
            ])
            ->assertOk()
            ->assertJsonPath('rendered_with', 'http')
            ->assertJsonPath('suggest_playwright', true);
    }

    public function test_preview_endpoint_uses_playwright_bridge_when_requested(): void
    {
        config([
            'job_scraping.playwright.script_path' => base_path('tests/fixtures/scripts/mock-scrape-page.mjs'),
            'job_scraping.playwright.node_binary' => 'node',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('job-sources.preview'), [
                'url' => 'https://example.com/jobs',
                'engine' => 'playwright',
            ])
            ->assertOk()
            ->assertJsonPath('rendered_with', 'playwright')
            ->assertJsonPath('suggest_playwright', false)
            ->assertJson(fn ($json) => $json
                ->whereType('html', 'string')
                ->whereType('cached_html', 'string')
                ->etc());
    }

    public function test_test_extraction_endpoint_runs_playwright_full_flow(): void
    {
        config([
            'job_scraping.playwright.script_path' => base_path('tests/fixtures/scripts/mock-scrape-page.mjs'),
            'job_scraping.playwright.node_binary' => 'node',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson(route('job-sources.test-extraction'), [
                'url' => 'https://example.com/jobs',
                'base_url' => 'https://example.com/jobs',
                'engine' => 'playwright',
                'interactions' => [
                    ['type' => 'click', 'selector' => '#accept', 'optional' => true],
                ],
                'item_selector' => 'article.job-card',
                'fields' => [
                    'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                    'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('item_match_count', 2);
    }
}
