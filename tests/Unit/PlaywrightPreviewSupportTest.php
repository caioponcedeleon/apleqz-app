<?php

namespace Tests\Unit;

use App\Support\DynamicHtmlPlaceholderDetector;
use App\Support\PlaywrightInteractionPresets;
use PHPUnit\Framework\TestCase;

class PlaywrightPreviewSupportTest extends TestCase
{
    public function test_detects_jobboard_datatable_placeholder(): void
    {
        $html = '<div class="jobboard-datatable" data-widget="jobboardDatatable"><div>Bitte warten...</div></div>';

        $this->assertTrue(DynamicHtmlPlaceholderDetector::suggestsPlaywright($html));
    }

    public function test_does_not_suggest_playwright_when_table_rows_are_present(): void
    {
        $html = '<div class="jobboard-datatable"><table><tbody><tr><td>Engineer</td></tr></tbody></table></div>';

        $this->assertFalse(DynamicHtmlPlaceholderDetector::suggestsPlaywright($html));
    }

    public function test_resolves_default_interactions_for_playwright_without_custom_steps(): void
    {
        $resolved = PlaywrightInteractionPresets::resolve([], true);

        $this->assertNotEmpty($resolved);
        $this->assertSame('wait_for', $resolved[0]['type'] ?? null);
    }

    public function test_keeps_custom_interactions_when_provided(): void
    {
        $custom = [['type' => 'click', 'selector' => '#accept']];

        $this->assertSame($custom, PlaywrightInteractionPresets::resolve($custom, true));
    }
}
