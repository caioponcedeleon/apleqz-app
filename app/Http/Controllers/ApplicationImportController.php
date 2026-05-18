<?php

namespace App\Http\Controllers;

use App\Exceptions\ApplicationImportException;
use App\Http\Requests\ImportApplicationsRequest;
use App\Services\ApplicationImportService;
use Illuminate\Http\RedirectResponse;

class ApplicationImportController extends Controller
{
    public function __invoke(
        ImportApplicationsRequest $request,
        ApplicationImportService $importService,
    ): RedirectResponse {
        try {
            $result = $importService->importFromPath(
                $request->user(),
                $request->file('file')->getRealPath(),
            );
        } catch (ApplicationImportException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('applications.index')
            ->with('success', __('app.flash.import_complete', [
                'imported' => $result->imported,
                'skipped' => $result->skipped,
            ]));
    }
}
