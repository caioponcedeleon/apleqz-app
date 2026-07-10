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

    public function test_supports_asterisk_wildcards_in_plain_keywords(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate(
            'Wissenschaftliche*r Mitarbeiter*in (m/w/d)',
            'wissenschaftlich*',
            '',
        );

        $this->assertSame(100, $result['fit_score']);
    }

    public function test_wildcard_prefix_does_not_match_unrelated_title(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate(
            'Senior Software Engineer',
            'wissenschaftlich*',
            '',
        );

        $this->assertSame(0, $result['fit_score']);
    }

    public function test_wildcard_works_for_exclude_rules(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate(
            'Praktikant*in im Marketing',
            'developer',
            'praktik*',
        );

        $this->assertSame(0, $result['fit_score']);
    }

    public function test_wildcard_supports_middle_gaps(): void
    {
        $matcher = app(JobTitlePatternMatcher::class);

        $result = $matcher->evaluate(
            'Engenheiro de dados sênior',
            'engenheiro*dados',
            '',
        );

        $this->assertSame(100, $result['fit_score']);
    }
}
