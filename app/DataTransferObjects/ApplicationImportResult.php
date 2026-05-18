<?php

namespace App\DataTransferObjects;

readonly class ApplicationImportResult
{
    public function __construct(
        public int $imported,
        public int $skipped,
    ) {}
}
