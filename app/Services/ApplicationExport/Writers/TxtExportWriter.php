<?php

namespace App\Services\ApplicationExport\Writers;

use App\DataTransferObjects\ApplicationExportDocument;

class TxtExportWriter
{
    public function write(ApplicationExportDocument $document): string
    {
        $lines = [$document->title, str_repeat('=', mb_strlen($document->title))];

        if (isset($document->meta['applicant'])) {
            $lines[] = __('export.applicant', ['name' => $document->meta['applicant']], 'de');
            $lines[] = __('export.generated', ['date' => $document->meta['exported_at']], 'de');
            $lines[] = '';
        }

        if ($document->headers !== []) {
            $lines[] = implode("\t", $document->headers);
        }

        foreach ($document->rows as $row) {
            $lines[] = implode("\t", array_map(
                fn (string $cell) => str_replace(["\r", "\n", "\t"], ' ', $cell),
                $row,
            ));
        }

        if (isset($document->meta['footnote'])) {
            $lines[] = '';
            $lines[] = $document->meta['footnote'];
        }

        return implode("\n", $lines)."\n";
    }
}
