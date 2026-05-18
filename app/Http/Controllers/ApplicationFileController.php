<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesStoredFileInline;
use App\Http\Requests\StoreApplicationFileRequest;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Services\StoredFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationFileController extends Controller
{
    use ServesStoredFileInline;

    public function __construct(
        protected StoredFileService $storedFiles
    ) {}

    public function store(StoreApplicationFileRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $meta = $this->storedFiles->store(
            $request->file('file'),
            "application-files/{$request->user()->id}/{$application->id}",
        );

        $application->files()->create([
            ...$meta,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('app.flash.file_uploaded'));
    }

    public function destroy(Application $application, ApplicationFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        abort_unless($file->application_id === $application->id, 404);

        $file->delete();

        return back()->with('success', __('app.flash.file_deleted'));
    }

    public function download(Application $application, ApplicationFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        abort_unless($file->application_id === $application->id, 404);

        return Storage::disk('local')->download(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type],
        );
    }

    public function preview(Application $application, ApplicationFile $file): BinaryFileResponse
    {
        $this->authorize('download', $file);

        abort_unless($file->application_id === $application->id, 404);

        return $this->inlineFileResponse($file->path, $file->original_name, $file->mime_type);
    }
}
