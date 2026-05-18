<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ServesStoredFileInline
{
    protected function inlineFileResponse(string $path, string $originalName, string $mimeType): BinaryFileResponse
    {
        $disposition = 'inline; filename="'.str_replace('"', '\\"', $originalName).'"';

        return response()->file(
            Storage::disk('local')->path($path),
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => $disposition,
            ],
        );
    }
}
