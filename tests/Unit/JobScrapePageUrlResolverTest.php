<?php

namespace Tests\Unit;

use App\Support\JobScrapePageUrlResolver;
use Tests\TestCase;

class JobScrapePageUrlResolverTest extends TestCase
{
    public function test_single_page_when_pagination_disabled(): void
    {
        $pages = app(JobScrapePageUrlResolver::class)->pages(
            'https://example.com/jobs',
            ['type' => 'none'],
        );

        $this->assertSame([
            ['url' => 'https://example.com/jobs', 'page' => 1],
        ], $pages);
    }

    public function test_query_param_pages_start_with_base_url_for_page_one(): void
    {
        $resolver = app(JobScrapePageUrlResolver::class);

        $pages = $resolver->pages('https://example.com/jobs', [
            'type' => 'query_param',
            'param' => 'page',
            'max_pages' => 3,
        ]);

        $this->assertSame('https://example.com/jobs', $pages[0]['url']);
        $this->assertSame('https://example.com/jobs?page=2', $pages[1]['url']);
        $this->assertSame('https://example.com/jobs?page=3', $pages[2]['url']);
    }

    public function test_preserves_existing_query_string_when_adding_page_param(): void
    {
        $pages = app(JobScrapePageUrlResolver::class)->pages('https://example.com/jobs?sort=date', [
            'type' => 'query_param',
            'param' => 'page',
            'max_pages' => 2,
        ]);

        $this->assertSame('https://example.com/jobs?sort=date', $pages[0]['url']);
        $this->assertStringContainsString('page=2', $pages[1]['url']);
        $this->assertStringContainsString('sort=date', $pages[1]['url']);
    }
}
