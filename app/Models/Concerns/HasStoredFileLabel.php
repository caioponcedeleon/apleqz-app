<?php

namespace App\Models\Concerns;

trait HasStoredFileLabel
{
    public function label(): string
    {
        return $this->display_name ?? $this->original_name;
    }

    public function downloadFilename(): string
    {
        return app(\App\Services\StoredFileService::class)
            ->downloadFilename($this->label(), $this->path);
    }
}
