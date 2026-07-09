<?php

namespace Tests\Unit;

use App\Services\JobPreviewHtmlSanitizer;
use Tests\TestCase;

class JobPreviewHtmlSanitizerTest extends TestCase
{
    public function test_strips_scripts_and_event_handlers(): void
    {
        $html = <<<'HTML'
            <html>
            <body>
                <a href="/jobs/1" onclick="alert(1)">Role</a>
                <script>alert("xss")</script>
            </body>
            </html>
        HTML;

        $sanitized = app(JobPreviewHtmlSanitizer::class)->sanitize($html, 'https://example.com/careers');

        $this->assertStringNotContainsString('<script', strtolower($sanitized));
        $this->assertStringNotContainsString('onclick', strtolower($sanitized));
        $this->assertStringContainsString('https://example.com/jobs/1', $sanitized);
    }

    public function test_rewrites_relative_urls(): void
    {
        $html = '<a href="/jobs/designer">Designer</a><img src="logo.png" />';

        $sanitized = app(JobPreviewHtmlSanitizer::class)->sanitize($html, 'https://example.com/careers/list');

        $this->assertStringContainsString('href="https://example.com/jobs/designer"', $sanitized);
        $this->assertStringContainsString('src="https://example.com/careers/logo.png"', $sanitized);
    }

    public function test_injects_picker_script_before_body_end(): void
    {
        $html = '<html><body><p>Jobs</p></body></html>';

        $result = app(JobPreviewHtmlSanitizer::class)->injectPickerScript(
            $html,
            'https://example.com/js/job-source-picker.js',
        );

        $this->assertStringContainsString('job-source-picker.js', $result);
        $this->assertStringContainsString('</body>', $result);
    }
}
