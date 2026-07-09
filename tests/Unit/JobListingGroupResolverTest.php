<?php

namespace Tests\Unit;

use App\Support\JobListingGroupResolver;
use DOMDocument;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Tests\TestCase;

class JobListingGroupResolverTest extends TestCase
{
    public function test_group_count_uses_minimum_part_matches(): void
    {
        $html = <<<'HTML'
            <div class="a">1</div><div class="a">2</div>
            <div class="b">1</div>
        HTML;

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $converter = new CssSelectorConverter;
        $resolver = app(JobListingGroupResolver::class);

        $partNodeLists = $resolver->partNodeLists($xpath, $converter, [
            ['selector' => 'div.a'],
            ['selector' => 'div.b'],
        ]);

        $this->assertSame([2, 1], $resolver->partMatchCounts($partNodeLists));
        $this->assertSame(1, $resolver->groupCount($partNodeLists));
    }
}
