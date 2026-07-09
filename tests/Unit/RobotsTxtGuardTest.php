<?php

namespace Tests\Unit;

use App\Support\RobotsTxtGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RobotsTxtGuardTest extends TestCase
{
    public function test_allows_path_when_robots_has_no_disallow_rules(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("User-agent: *\nAllow: /", 200),
        ]);

        $this->assertTrue(app(RobotsTxtGuard::class)->isPathAllowed('https://example.com/jobs/1'));
    }

    public function test_blocks_disallowed_prefix(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /private/", 200),
        ]);

        $guard = app(RobotsTxtGuard::class);

        $this->assertFalse($guard->isPathAllowed('https://example.com/private/listing'));
        $this->assertTrue($guard->isPathAllowed('https://example.com/jobs/listing'));
    }

    public function test_allows_when_robots_txt_is_missing(): void
    {
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 404),
        ]);

        $this->assertTrue(app(RobotsTxtGuard::class)->isPathAllowed('https://example.com/jobs/1'));
    }
}
