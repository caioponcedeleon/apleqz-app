<?php

namespace Tests\Unit;

use App\Support\ScrapeUrlGuard;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScrapeUrlGuardTest extends TestCase
{
    public function test_blocks_localhost_urls(): void
    {
        $this->expectException(ValidationException::class);

        app(ScrapeUrlGuard::class)->assertSafe('http://localhost/jobs');
    }

    public function test_blocks_private_ip_urls(): void
    {
        $this->expectException(ValidationException::class);

        app(ScrapeUrlGuard::class)->assertSafe('http://127.0.0.1/jobs');
    }

    public function test_allows_public_https_urls(): void
    {
        app(ScrapeUrlGuard::class)->assertSafe('https://example.com/jobs');

        $this->assertTrue(true);
    }
}
