<?php

namespace App\DataTransferObjects;

readonly class ApplicationExportDocument
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array<string, string>  $meta
     */
    public function __construct(
        public string $title,
        public array $headers,
        public array $rows,
        public array $meta = [],
    ) {}
}
