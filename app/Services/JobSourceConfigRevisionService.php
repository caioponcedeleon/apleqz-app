<?php

namespace App\Services;

use App\Models\JobSource;
use App\Models\JobSourceConfigRevision;

class JobSourceConfigRevisionService
{
    public function snapshotBeforeUpdate(JobSource $source, array $newConfig): void
    {
        $currentConfig = $source->extraction_config ?? JobSource::defaultExtractionConfig();

        if ($currentConfig === $newConfig) {
            return;
        }

        JobSourceConfigRevision::query()->updateOrCreate(
            [
                'job_source_id' => $source->id,
                'config_version' => $source->config_version ?? 1,
            ],
            [
                'extraction_config' => $currentConfig,
                'created_at' => now(),
            ],
        );
    }
}
