<?php

namespace Tests\Unit;

use App\Support\HtmlEncodingNormalizer;
use Tests\TestCase;

class HtmlEncodingNormalizerTest extends TestCase
{
    public function test_converts_iso_8859_1_html_to_utf8(): void
    {
        $html = mb_convert_encoding(
            '<html><head><meta charset="iso-8859-1"></head><body>Müller & Straße</body></html>',
            'ISO-8859-1',
            'UTF-8',
        );

        $this->assertFalse(mb_check_encoding($html, 'UTF-8'));

        $normalized = app(HtmlEncodingNormalizer::class)->toUtf8(
            $html,
            'text/html; Charset=iso-8859-1',
        );

        $this->assertTrue(mb_check_encoding($normalized, 'UTF-8'));
        $this->assertStringContainsString('Müller & Straße', $normalized);
        json_encode(['html' => $normalized], JSON_THROW_ON_ERROR);
    }

    public function test_leaves_valid_utf8_unchanged(): void
    {
        $html = '<html><body>Hello 世界</body></html>';

        $normalized = app(HtmlEncodingNormalizer::class)->toUtf8($html);

        $this->assertSame($html, $normalized);
    }
}
