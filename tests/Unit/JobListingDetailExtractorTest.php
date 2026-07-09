<?php

namespace Tests\Unit;

use App\Models\JobListing;
use App\Models\JobSource;
use App\Services\JobListingDetailEnrichmentService;
use App\Services\JobListingExtractor;
use App\Services\JobPreviewHtmlSanitizer;
use App\Services\JobSourceFetcher;
use App\Services\PlaywrightPageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobListingDetailExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_detail_reads_document_scoped_fields(): void
    {
        $html = <<<'HTML'
        <html><body>
            <h1>Senior Engineer</h1>
            <div class="job-body">Build APIs with Laravel and PostgreSQL.</div>
        </body></html>
        HTML;

        $fields = [
            'description' => [
                'selector' => 'div.job-body',
                'scope' => 'document',
                'extract' => 'text',
            ],
        ];

        $result = app(JobListingExtractor::class)->extractDetail($html, $fields, 'https://example.com/jobs/1');

        $this->assertSame('Build APIs with Laravel and PostgreSQL.', $result['description']);
    }

    public function test_extract_detail_handles_mixed_heading_and_paragraph_siblings(): void
    {
        $html = <<<'HTML'
        <html><body>
            <div class="card-body textpicBanner">
                <h2>Einsatzort</h2>
                <p>Essen</p>
                <h2>Einsatzbereich</h2>
                <p>Fakultät für Bildungswissenschaften</p>
                <p><span>IT-Systemadministrator:in</span><b>EG 11 TV-L</b></p>
            </div>
        </body></html>
        HTML;

        $extractor = app(JobListingExtractor::class);

        $wrongLocation = $extractor->extractDetail($html, [
            'location' => [
                'selector' => 'div.card-body.textpicBanner > p:nth-child(1)',
                'scope' => 'document',
                'extract' => 'text',
            ],
        ], 'https://example.com/jobs/1');

        $this->assertNull($wrongLocation['location']);

        $correct = $extractor->extractDetail($html, [
            'location' => [
                'selector' => 'div.card-body.textpicBanner > p:nth-of-type(1)',
                'scope' => 'document',
                'extract' => 'text',
            ],
            'department' => [
                'selector' => 'div.card-body.textpicBanner > p:nth-of-type(2)',
                'scope' => 'document',
                'extract' => 'text',
            ],
            'job_title' => [
                'selector' => 'div.card-body.textpicBanner > p:nth-of-type(3) > span',
                'scope' => 'document',
                'extract' => 'text',
            ],
            'salary' => [
                'selector' => 'div.card-body.textpicBanner > p:nth-of-type(3) > b',
                'scope' => 'document',
                'extract' => 'text',
            ],
        ], 'https://example.com/jobs/1');

        $this->assertSame('Essen', $correct['location']);
        $this->assertSame('Fakultät für Bildungswissenschaften', $correct['department']);
        $this->assertSame('IT-Systemadministrator:in', $correct['job_title']);
        $this->assertSame('EG 11 TV-L', $correct['salary']);
    }

    public function test_extract_detail_uses_match_index_for_repeated_selectors(): void
    {
        $html = <<<'HTML'
        <html><body>
            <div class="module"><div class="text">Deadline section</div></div>
            <div class="module"><div class="text">Provider section</div></div>
            <div class="module"><div class="text">Full job description body.</div></div>
        </body></html>
        HTML;

        $fields = [
            'description' => [
                'selector' => 'div.text',
                'scope' => 'document',
                'extract' => 'text',
                'match_index' => 2,
            ],
        ];

        $result = app(JobListingExtractor::class)->extractDetail($html, $fields, 'https://example.com/jobs/1');

        $this->assertSame('Full job description body.', $result['description']);
    }

    public function test_enrichment_service_updates_listing_and_marks_enriched(): void
    {
        $source = JobSource::factory()->create([
            'extraction_config' => array_merge(JobSource::defaultExtractionConfig(), [
                'detail' => [
                    'enabled' => true,
                    'sample_url' => 'https://example.com/jobs/1',
                    'fetch_min_score' => 60,
                    'engine' => 'http',
                    'interactions' => [],
                    'fields' => [
                        'description' => [
                            'selector' => 'div.job-body',
                            'scope' => 'document',
                            'extract' => 'text',
                        ],
                    ],
                ],
            ]),
        ]);

        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://example.com/jobs/1',
            'description' => null,
            'detail_enriched_at' => null,
        ]);

        $html = '<html><body><div class="job-body">Full job description text.</div></body></html>';

        $this->mock(JobSourceFetcher::class, function ($mock) use ($html): void {
            $mock->shouldReceive('fetch')->once()->andReturn($html);
        });

        $this->mock(JobPreviewHtmlSanitizer::class, function ($mock) use ($html): void {
            $mock->shouldReceive('sanitize')->once()->andReturn($html);
        });

        $service = app(JobListingDetailEnrichmentService::class);

        $this->assertTrue($service->enrich($listing->fresh()));

        $listing->refresh();

        $this->assertSame('Full job description text.', $listing->description);
        $this->assertNotNull($listing->detail_enriched_at);
        $this->assertSame(
            hash('sha256', $listing->title.'Full job description text.'),
            $listing->content_hash,
        );
    }

    public function test_enrichment_service_updates_title_from_detail_job_title_field(): void
    {
        $source = JobSource::factory()->create([
            'extraction_config' => array_merge(JobSource::defaultExtractionConfig(), [
                'detail' => [
                    'enabled' => true,
                    'sample_url' => 'https://example.com/jobs/1',
                    'fetch_min_score' => 60,
                    'engine' => 'http',
                    'interactions' => [],
                    'fields' => [
                        'job_title' => [
                            'selector' => 'h1.title',
                            'scope' => 'document',
                            'extract' => 'text',
                        ],
                    ],
                ],
            ]),
        ]);

        $listing = JobListing::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://example.com/jobs/1',
            'title' => 'Short listing title',
            'description' => null,
            'detail_enriched_at' => null,
        ]);

        $html = '<html><body><h1 class="title">IT-Systemadministrator:in in der Hochschulambulanz</h1></body></html>';

        $this->mock(JobSourceFetcher::class, function ($mock) use ($html): void {
            $mock->shouldReceive('fetch')->once()->andReturn($html);
        });

        $this->mock(JobPreviewHtmlSanitizer::class, function ($mock) use ($html): void {
            $mock->shouldReceive('sanitize')->once()->andReturn($html);
        });

        $service = app(JobListingDetailEnrichmentService::class);

        $this->assertTrue($service->enrich($listing->fresh()));

        $listing->refresh();

        $this->assertSame('IT-Systemadministrator:in in der Hochschulambulanz', $listing->title);
        $this->assertSame(
            hash('sha256', 'IT-Systemadministrator:in in der Hochschulambulanz'.($listing->description ?? '')),
            $listing->content_hash,
        );
    }
}
