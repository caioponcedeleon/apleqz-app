<?php

namespace Tests\Unit;

use App\Services\JobTitlePatternMatcher;
use Tests\TestCase;

class JobTitlePatternMatcherTest extends TestCase
{
    public function test_matches_include_keyword_case_insensitively(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate('Senior Policy Analyst', "analyst\ndeveloper", 'intern');

        $this->assertSame(100, $result['fit_score']);
    }

    public function test_rejects_when_exclude_keyword_matches(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate('Software Intern', 'developer', 'intern');

        $this->assertSame(0, $result['fit_score']);
    }

    public function test_supports_regex_patterns(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate('Data Engineer (m/f/d)', '/engineer/i', '');

        $this->assertSame(100, $result['fit_score']);
    }
}
