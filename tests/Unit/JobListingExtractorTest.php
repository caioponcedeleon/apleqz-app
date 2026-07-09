<?php

namespace Tests\Unit;

use App\Services\JobListingExtractor;
use Tests\TestCase;

class JobListingExtractorTest extends TestCase
{
    public function test_extracts_listings_from_fixture_html(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/job-sources/basic-listing.html'));
        $config = [
            'listing' => [
                'item_selector' => 'article.job-card',
                'fields' => [
                    'job_title' => [
                        'selector' => 'h2 a',
                        'scope' => 'item',
                        'extract' => 'text',
                    ],
                    'url' => [
                        'selector' => 'h2 a',
                        'scope' => 'item',
                        'extract' => 'attribute',
                        'attribute' => 'href',
                        'absolute' => true,
                    ],
                    'location' => [
                        'selector' => '.location',
                        'scope' => 'item',
                        'extract' => 'text',
                        'optional' => true,
                    ],
                    'description' => [
                        'selector' => '.description',
                        'scope' => 'item',
                        'extract' => 'text',
                        'optional' => true,
                    ],
                ],
            ],
        ];

        $listings = app(JobListingExtractor::class)->extract(
            $html,
            $config,
            'https://careers.example.com/jobs',
            'Acme GmbH',
        );

        $this->assertCount(2, $listings);
        $this->assertSame('Senior Engineer', $listings[0]['title']);
        $this->assertSame('https://careers.example.com/jobs/senior-engineer', $listings[0]['url']);
        $this->assertSame('Berlin', $listings[0]['location']);
        $this->assertSame('Product Designer', $listings[1]['title']);
        $this->assertSame('https://careers.example.com/jobs/designer', $listings[1]['url']);
    }

    public function test_skips_items_missing_required_fields(): void
    {
        $html = <<<'HTML'
            <article class="job-card"><h2><a href="/jobs/ok">Valid Role</a></h2></article>
            <article class="job-card"><h2><span>Missing link</span></h2></article>
        HTML;

        $config = [
            'listing' => [
                'item_selector' => 'article.job-card',
                'fields' => [
                    'job_title' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'text'],
                    'url' => ['selector' => 'h2 a', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                ],
            ],
        ];

        $listings = app(JobListingExtractor::class)->extract(
            $html,
            $config,
            'https://careers.example.com/jobs',
        );

        $this->assertCount(1, $listings);
        $this->assertSame('Valid Role', $listings[0]['title']);
    }
}
