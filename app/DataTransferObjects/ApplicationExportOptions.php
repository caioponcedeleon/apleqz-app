<?php

namespace App\DataTransferObjects;

readonly class ApplicationExportOptions
{
    public const FORMATS = ['txt', 'docx', 'xlsx', 'pdf'];

    public const FIELDS = ['position', 'company', 'applied_at', 'status', 'events'];

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $format,
        public array $fields,
        public bool $agenturFurArbeit,
        public array $filters,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $agentur = (bool) ($validated['agentur_fur_arbeit'] ?? false);
        $fields = $validated['fields'] ?? [];

        if ($agentur) {
            $fields = array_values(array_unique(array_merge(
                ['position', 'company', 'applied_at', 'status'],
                array_intersect($fields, ['events']),
            )));
        }

        return new self(
            format: $validated['format'],
            fields: array_values(array_intersect($fields, self::FIELDS)),
            agenturFurArbeit: $agentur,
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? null,
                'area_id' => $validated['area_id'] ?? null,
                'sort' => $agentur ? 'applied_at' : ($validated['sort'] ?? 'status'),
                'direction' => $agentur ? 'asc' : ($validated['direction'] ?? 'asc'),
            ],
        );
    }
}
