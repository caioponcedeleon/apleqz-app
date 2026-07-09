<?php

namespace App\Support;

use DOMElement;
use DOMNode;
use DOMXPath;
use Symfony\Component\CssSelector\CssSelectorConverter;

class JobListingGroupResolver
{
    /**
     * @param  list<array{selector?: string}>  $parts
     * @return list<list<DOMNode>>
     */
    public function partNodeLists(DOMXPath $xpath, CssSelectorConverter $converter, array $parts): array
    {
        $lists = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                $lists[] = [];

                continue;
            }

            $selector = $part['selector'] ?? '';

            if (! is_string($selector) || trim($selector) === '') {
                $lists[] = [];

                continue;
            }

            $nodes = $xpath->query($converter->toXPath($selector));
            $lists[] = $nodes === false ? [] : iterator_to_array($nodes);
        }

        return $lists;
    }

    /**
     * @param  list<list<DOMNode>>  $partNodeLists
     * @return list<int>
     */
    public function partMatchCounts(array $partNodeLists): array
    {
        return array_map(count(...), $partNodeLists);
    }

    /**
     * @param  list<list<DOMNode>>  $partNodeLists
     */
    public function groupCount(array $partNodeLists): int
    {
        if ($partNodeLists === []) {
            return 0;
        }

        return min($this->partMatchCounts($partNodeLists));
    }

    /**
     * @param  list<list<DOMNode>>  $partNodeLists
     */
    public function itemContainerAt(array $partNodeLists, int $index): ?DOMElement
    {
        $nodes = [];

        foreach ($partNodeLists as $list) {
            $node = $list[$index] ?? null;

            if (! $node instanceof DOMElement) {
                return null;
            }

            $nodes[] = $node;
        }

        $ancestor = $this->lowestCommonAncestor($nodes);

        return $ancestor instanceof DOMElement ? $ancestor : null;
    }

    /**
     * @param  list<DOMElement>  $nodes
     */
    public function lowestCommonAncestor(array $nodes): ?DOMElement
    {
        if ($nodes === []) {
            return null;
        }

        if (count($nodes) === 1) {
            return $nodes[0];
        }

        $ancestors = [];
        $first = $nodes[0];

        for ($current = $first; $current instanceof DOMElement; $current = $current->parentNode) {
            $ancestors[spl_object_id($current)] = $current;
        }

        foreach (array_slice($nodes, 1) as $node) {
            for ($current = $node; $current instanceof DOMElement; $current = $current->parentNode) {
                if (isset($ancestors[spl_object_id($current)])) {
                    return $ancestors[spl_object_id($current)];
                }
            }
        }

        return $first;
    }
}
