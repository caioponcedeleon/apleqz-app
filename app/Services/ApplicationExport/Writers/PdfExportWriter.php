<?php

namespace App\Services\ApplicationExport\Writers;

use App\DataTransferObjects\ApplicationExportDocument;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfExportWriter
{
    public function write(ApplicationExportDocument $document): string
    {
        $html = view('exports.applications-pdf', [
            'document' => $document,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
