<?php

namespace App\Services;

use App\DataTransferObjects\ApplicationExportOptions;
use App\Models\User;
use App\Queries\FilteredApplicationsQuery;
use App\Services\ApplicationExport\ApplicationExportDocumentBuilder;
use App\Services\ApplicationExport\Writers\DocxExportWriter;
use App\Services\ApplicationExport\Writers\PdfExportWriter;
use App\Services\ApplicationExport\Writers\TxtExportWriter;
use App\Services\ApplicationExport\Writers\XlsxExportWriter;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationExportService
{
    public function __construct(
        protected ApplicationExportDocumentBuilder $documentBuilder,
        protected TxtExportWriter $txtWriter,
        protected XlsxExportWriter $xlsxWriter,
        protected DocxExportWriter $docxWriter,
        protected PdfExportWriter $pdfWriter,
    ) {}

    public function download(User $user, ApplicationExportOptions $options): Response|StreamedResponse|BinaryFileResponse
    {
        $applications = (new FilteredApplicationsQuery($user))
            ->build($options->filters)
            ->get();

        $document = $this->documentBuilder->build($user, $applications, $options);
        $basename = $this->basename($options);
        $tempDir = storage_path('app/temp/exports');

        File::ensureDirectoryExists($tempDir);

        return match ($options->format) {
            'txt' => response($this->txtWriter->write($document), 200, $this->headers($basename, 'txt', 'text/plain; charset=UTF-8')),
            'pdf' => response($this->pdfWriter->write($document), 200, $this->headers($basename, 'pdf', 'application/pdf')),
            'xlsx' => $this->fileResponse($tempDir.'/'.$basename.'.xlsx', function (string $path) use ($document) {
                $this->xlsxWriter->write($document, $path);
            }, $basename, 'xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'docx' => $this->fileResponse($tempDir.'/'.$basename.'.docx', function (string $path) use ($document) {
                $this->docxWriter->write($document, $path);
            }, $basename, 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        };
    }

    protected function basename(ApplicationExportOptions $options): string
    {
        $prefix = $options->agenturFurArbeit ? 'bewerbungsuebersicht' : 'applications';

        return $prefix.'-'.now()->format('Y-m-d');
    }

    /**
     * @return array<string, string>
     */
    protected function headers(string $basename, string $extension, string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$basename.'.'.$extension.'"',
        ];
    }

    /**
     * @param  callable(string): void  $writer
     */
    protected function fileResponse(
        string $path,
        callable $writer,
        string $basename,
        string $extension,
        string $contentType,
    ): BinaryFileResponse {
        $writer($path);

        return response()
            ->download($path, $basename.'.'.$extension, $this->headers($basename, $extension, $contentType))
            ->deleteFileAfterSend(true);
    }
}
