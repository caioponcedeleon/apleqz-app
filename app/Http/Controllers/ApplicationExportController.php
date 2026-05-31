<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\ApplicationExportOptions;
use App\Http\Requests\ExportApplicationsRequest;
use App\Services\ApplicationExportService;
use Symfony\Component\HttpFoundation\Response;

class ApplicationExportController extends Controller
{
    public function __invoke(
        ExportApplicationsRequest $request,
        ApplicationExportService $exportService,
    ): Response {
        return $exportService->download(
            $request->user(),
            ApplicationExportOptions::fromValidated($request->validated()),
        );
    }
}
