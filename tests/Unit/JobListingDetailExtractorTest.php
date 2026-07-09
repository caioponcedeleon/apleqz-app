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
}
