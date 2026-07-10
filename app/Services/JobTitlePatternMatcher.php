<?php

namespace App\Services;

class JobTitlePatternMatcher
{
    public function evaluationCacheKey(string $includeKeywords, string $excludeKeywords, ?string $contentHash): string
    {
        return hash('sha256', 'regex|'.trim($includeKeywords).'|'.trim($excludeKeywords).'|'.($contentHash ?? ''));
    }

    /**
     * @return array{fit_score: int, reason: string}
     */
    public function evaluate(string $title, ?string $includeKeywords, ?string $excludeKeywords): array
    {
        $includes = $this->parseLines($includeKeywords);
        $excludes = $this->parseLines($excludeKeywords);

        foreach ($excludes as $pattern) {
            if ($this->matches($title, $pattern)) {
                return [
                    'fit_score' => 0,
                    'reason' => __('app.job_alerts.regex_excluded_by', ['pattern' => $pattern]),
                ];
            }
        }

        if ($includes !== []) {
            foreach ($includes as $pattern) {
                if ($this->matches($title, $pattern)) {
                    return [
                        'fit_score' => 100,
                        'reason' => __('app.job_alerts.regex_matched_include', ['pattern' => $pattern]),
                    ];
                }
            }

            return [
                'fit_score' => 0,
                'reason' => __('app.job_alerts.regex_no_include_match'),
            ];
        }

        return [
            'fit_score' => 100,
            'reason' => __('app.job_alerts.regex_passed_excludes_only'),
        ];
    }

    public function hasRules(?string $includeKeywords, ?string $excludeKeywords): bool
    {
        return $this->parseLines($includeKeywords) !== [] || $this->parseLines($excludeKeywords) !== [];
    }

    /**
     * @return list<string>
     */
    public function parseLines(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    protected function matches(string $title, string $pattern): bool
    {
        if ($this->isRegexPattern($pattern)) {
            $result = @preg_match($pattern, $title);

            return $result === 1;
        }

        if (str_contains($pattern, '*')) {
            return $this->matchesWildcard($title, $pattern);
        }

        return mb_stripos($title, $pattern) !== false;
    }

    protected function matchesWildcard(string $title, string $pattern): bool
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/'.str_replace('\*', '.*', $quoted).'/iu';

        $result = @preg_match($regex, $title);

        return $result === 1;
    }

    protected function isRegexPattern(string $pattern): bool
    {
        return (bool) preg_match('/^\/.+\/[a-zA-Z]*$/', $pattern);
    }
}
