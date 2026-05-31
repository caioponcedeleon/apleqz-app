<?php

namespace App\Services\ApplicationExport\Writers;

use App\DataTransferObjects\ApplicationExportDocument;
use PhpOffice\PhpWord\PhpWord;

class DocxExportWriter
{
    public function write(ApplicationExportDocument $document, string $path): void
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addText($document->title, ['bold' => true, 'size' => 14]);
        $section->addTextBreak();

        if (isset($document->meta['applicant'])) {
            $section->addText(__('export.applicant', ['name' => $document->meta['applicant']], 'de'));
            $section->addText(__('export.generated', ['date' => $document->meta['exported_at']], 'de'));
            $section->addTextBreak();
        }

        if ($document->headers !== []) {
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => 'CCCCCC',
                'cellMargin' => 80,
            ]);

            $table->addRow();
            foreach ($document->headers as $header) {
                $table->addCell(2200)->addText($header, ['bold' => true]);
            }

            foreach ($document->rows as $row) {
                $table->addRow();
                foreach ($row as $cell) {
                    $table->addCell(2200)->addText($cell);
                }
            }

            $section->addTextBreak();
        }

        if (isset($document->meta['footnote'])) {
            $section->addText($document->meta['footnote'], ['italic' => true, 'size' => 9]);
        }

        $phpWord->save($path, 'Word2007');
    }
}
