<?php

namespace App\Support;

class HtmlEncodingNormalizer
{
    public function toUtf8(string $html, ?string $contentType = null): string
    {
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        $charset = $this->detectCharset($html, $contentType);
        $converted = $this->convert($html, $charset);

        if (! mb_check_encoding($converted, 'UTF-8')) {
            $converted = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }

        return $converted;
    }

    protected function detectCharset(string $html, ?string $contentType): string
    {
        if (is_string($contentType) && preg_match('/charset\s*=\s*([^\s;]+)/i', $contentType, $matches) === 1) {
            return $this->normalizeCharsetName($matches[1]);
        }

        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>;]+)/i', $html, $matches) === 1) {
            return $this->normalizeCharsetName($matches[1]);
        }

        if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset\s*=\s*([^"\'\s;]+)/i', $html, $matches) === 1) {
            return $this->normalizeCharsetName($matches[1]);
        }

        return 'ISO-8859-1';
    }

    protected function normalizeCharsetName(string $charset): string
    {
        $charset = trim($charset, "\"' ");

        return match (strtolower($charset)) {
            'utf8', 'utf-8' => 'UTF-8',
            'iso-8859-1', 'iso8859-1', 'latin1', 'latin-1' => 'ISO-8859-1',
            'windows-1252', 'cp1252' => 'Windows-1252',
            default => $charset,
        };
    }

    protected function convert(string $html, string $fromCharset): string
    {
        if ($fromCharset === 'UTF-8') {
            return $html;
        }

        $converted = @mb_convert_encoding($html, 'UTF-8', $fromCharset);

        if ($converted === false) {
            return $html;
        }

        return $converted;
    }
}
