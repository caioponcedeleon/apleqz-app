<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

class JobPreviewHtmlSanitizer
{
    /**
     * @var list<string>
     */
    protected array $blockedTags = [
        'script',
        'iframe',
        'object',
        'embed',
        'base',
    ];

    public function sanitize(string $html, string $baseUrl): string
    {
        $document = $this->loadHtml($html);
        $this->removeBlockedTags($document);
        $this->stripEventHandlers($document);
        $this->rewriteUrls($document, $baseUrl);

        $sanitized = $document->saveHTML();

        return is_string($sanitized) ? $sanitized : $html;
    }

    protected function loadHtml(string $html): DOMDocument
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT,
        );
        libxml_clear_errors();

        return $document;
    }

    protected function removeBlockedTags(DOMDocument $document): void
    {
        foreach ($this->blockedTags as $tag) {
            while (true) {
                $nodes = $document->getElementsByTagName($tag);

                if ($nodes->length === 0) {
                    break;
                }

                $node = $nodes->item(0);

                if ($node?->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    protected function stripEventHandlers(DOMDocument $document): void
    {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@*[starts-with(name(), "on")]]');

        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $attributes = [];

            foreach ($node->attributes ?? [] as $attribute) {
                $name = strtolower($attribute->name);

                if (str_starts_with($name, 'on')) {
                    $attributes[] = $attribute->name;
                }
            }

            foreach ($attributes as $name) {
                $node->removeAttribute($name);
            }
        }
    }

    protected function rewriteUrls(DOMDocument $document, string $baseUrl): void
    {
        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@href or @src]');

        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->hasAttribute('href')) {
                $href = trim($node->getAttribute('href'));

                if ($href !== '' && ! str_starts_with(strtolower($href), 'javascript:')) {
                    $node->setAttribute('href', $this->resolveUrl($href, $baseUrl));
                }
            }

            if ($node->hasAttribute('src')) {
                $src = trim($node->getAttribute('src'));

                if ($src !== '' && ! str_starts_with(strtolower($src), 'javascript:')) {
                    $node->setAttribute('src', $this->resolveUrl($src, $baseUrl));
                }
            }
        }
    }

    protected function resolveUrl(string $value, string $baseUrl): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $baseParts = parse_url($baseUrl);

        if (! is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return $value;
        }

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';

        if (str_starts_with($value, '//')) {
            return $scheme.':'.$value;
        }

        if (str_starts_with($value, '/')) {
            return "{$scheme}://{$host}{$port}{$value}";
        }

        $path = $baseParts['path'] ?? '/';
        $directory = str_ends_with($path, '/') ? $path : dirname($path).'/';

        return "{$scheme}://{$host}{$port}{$directory}{$value}";
    }

    public function injectPickerScript(string $html, string $pickerScriptUrl): string
    {
        $script = '<script src="'.htmlspecialchars($pickerScriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" defer></script>';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $script.'</body>', $html, 1) ?? $html.$script;
        }

        return $html.$script;
    }
}
