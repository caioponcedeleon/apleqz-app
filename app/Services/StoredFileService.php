<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoredFileService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['pdf', 'docx'];

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function store(UploadedFile $file, string $directory): array
    {
        $this->assertAllowed($file);

        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            $directory,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        return [
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }

    public function delete(string $path): void
    {
        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function assertAllowed(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('app.files.too_large'));
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException(__('app.files.invalid_type'));
        }

        $mime = $file->getMimeType();

        if ($mime && ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException(__('app.files.invalid_type'));
        }
    }

    public function downloadFilename(string $label, string $storedPath): string
    {
        $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $labelExtension = strtolower(pathinfo($label, PATHINFO_EXTENSION));

        if ($labelExtension === '' || ! in_array($labelExtension, self::ALLOWED_EXTENSIONS, true)) {
            return rtrim($label, '.').'.'.$extension;
        }

        return $label;
    }
}
