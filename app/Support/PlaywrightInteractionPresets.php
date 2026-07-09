<?php

namespace App\Support;

class PlaywrightInteractionPresets
{
    /**
     * @param  list<array<string, mixed>>  $interactions
     * @return list<array<string, mixed>>
     */
    public static function resolve(array $interactions, bool $usePlaywright): array
    {
        if (! $usePlaywright || $interactions !== []) {
            return $interactions;
        }

        return [
            [
                'type' => 'wait_for',
                'selector' => '.jobboard-datatable table tbody tr, table.dataTable tbody tr, [data-widget="jobboardDatatable"] tbody tr',
                'timeout_ms' => 20_000,
                'optional' => true,
            ],
            ['type' => 'sleep', 'ms' => 500],
        ];
    }
}
