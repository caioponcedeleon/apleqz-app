<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesStoredFileInline;
use App\Http\Requests\RenameStoredFileRequest;
use App\Http\Requests\StoreUserFileRequest;
use App\Models\UserFile;
use App\Services\StoredFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserFileController extends Controller
{
    use ServesStoredFileInline;

    public function __construct(
        protected StoredFileService $storedFiles
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', UserFile::class);

        $files = auth()->user()
            ->files()
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Files/Index', [
            'files' => $files,
        ]);
    }

    public function store(StoreUserFileRequest $request): RedirectResponse
    {
        $meta = $this->storedFiles->store(
            $request->file('file'),
            "user-files/{$request->user()->id}",
        );

        $request->user()->files()->create($meta);

        return back()->with('success', __('app.flash.file_uploaded'));
    }

    public function update(RenameStoredFileRequest $request, UserFile $file): RedirectResponse
    {
        $this->authorize('update', $file);

        $file->update($request->validated());

        return back()->with('success', __('app.flash.file_label_updated'));
    }

    public function destroy(UserFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        $file->delete();

        return back()->with('success', __('app.flash.file_deleted'));
    }

    public function download(UserFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return Storage::disk('local')->download(
            $file->path,
            $file->downloadFilename(),
            ['Content-Type' => $file->mime_type],
        );
    }

    public function preview(UserFile $file): BinaryFileResponse
    {
        $this->authorize('download', $file);

        return $this->inlineFileResponse($file->path, $file->downloadFilename(), $file->mime_type);
    }
}
