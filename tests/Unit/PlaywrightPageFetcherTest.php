<?php

namespace Tests\Unit;

use App\Services\PlaywrightPageFetcher;
use RuntimeException;
use Tests\TestCase;

class PlaywrightPageFetcherTest extends TestCase
{
    public function test_fetch_returns_html_from_node_script(): void
    {
        config([
            'job_scraping.playwright.script_path' => base_path('tests/fixtures/scripts/mock-scrape-page.mjs'),
            'job_scraping.playwright.node_binary' => 'node',
        ]);

        $html = app(PlaywrightPageFetcher::class)->fetch('https://example.com/jobs');

        $this->assertStringContainsString('article class="job-card"', $html);
    }

    public function test_fetch_throws_when_script_is_missing(): void
    {
        config([
            'job_scraping.playwright.script_path' => base_path('tests/fixtures/scripts/does-not-exist.mjs'),
            'job_scraping.playwright.node_binary' => 'node',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Playwright scrape script was not found.');

        app(PlaywrightPageFetcher::class)->fetch('https://example.com/jobs');
    }
}
