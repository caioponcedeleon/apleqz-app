<?php

namespace App\Services\ApplicationExport\Writers;

use App\DataTransferObjects\ApplicationExportDocument;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class XlsxExportWriter
{
    public function write(ApplicationExportDocument $document, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName(__('app.export.sheet_name'));

        $writer->addRow(Row::fromValues([$document->title]));

        if (isset($document->meta['applicant'])) {
            $writer->addRow(Row::fromValues([
                __('export.applicant', ['name' => $document->meta['applicant']], 'de'),
            ]));
            $writer->addRow(Row::fromValues([
                __('export.generated', ['date' => $document->meta['exported_at']], 'de'),
            ]));
            $writer->addRow(Row::fromValues(['']));
        }

        if ($document->headers !== []) {
            $writer->addRow(Row::fromValues($document->headers));
        }

        foreach ($document->rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        if (isset($document->meta['footnote'])) {
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues([$document->meta['footnote']]));
        }

        $writer->close();
    }
}
