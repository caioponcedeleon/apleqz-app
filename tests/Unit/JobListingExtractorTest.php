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

    public function test_extracts_grouped_listings_from_table_columns(): void
    {
        $html = <<<'HTML'
            <table class="results">
                <tbody>
                    <tr>
                        <td><a class="iconless" href="/jobs/one">Engineer One</a></td>
                        <td>Dept A</td>
                        <td>01/08/2026</td>
                    </tr>
                    <tr>
                        <td><a class="iconless" href="/jobs/two">Engineer Two</a></td>
                        <td>Dept B</td>
                        <td>02/08/2026</td>
                    </tr>
                </tbody>
            </table>
        HTML;

        $config = [
            'listing' => [
                'item_mode' => 'group',
                'item_selector' => '',
                'item_group' => [
                    'parts' => [
                        ['selector' => 'table.results tbody tr > td:nth-child(1)'],
                        ['selector' => 'table.results tbody tr > td:nth-child(2)'],
                        ['selector' => 'table.results tbody tr > td:nth-child(3)'],
                    ],
                ],
                'fields' => [
                    'job_title' => ['selector' => 'a.iconless', 'scope' => 'item', 'extract' => 'text'],
                    'url' => ['selector' => 'a.iconless', 'scope' => 'item', 'extract' => 'attribute', 'attribute' => 'href', 'absolute' => true],
                    'department' => ['selector' => 'td:nth-child(2)', 'scope' => 'item', 'extract' => 'text', 'optional' => true],
                    'application_deadline' => ['selector' => 'td:nth-child(3)', 'scope' => 'item', 'extract' => 'text', 'optional' => true],
                ],
            ],
        ];

        $listings = app(JobListingExtractor::class)->extract(
            $html,
            $config,
            'https://example.com/jobs',
        );

        $this->assertCount(2, $listings);
        $this->assertSame('Engineer One', $listings[0]['title']);
        $this->assertSame('https://example.com/jobs/one', $listings[0]['url']);
        $this->assertSame('Dept B', $listings[1]['raw_fields']['department'] ?? null);
    }
}
