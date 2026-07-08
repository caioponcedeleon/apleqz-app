<?php

namespace App\Http\Requests;

use App\Services\StoredFileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class StoreApplicationFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->application_files_enabled
            && $this->route('application')->user_id === $user->id;
    }

    public function rules(): array
    {
        $fileRule = File::types(StoredFileService::ALLOWED_EXTENSIONS)
            ->max(StoredFileService::MAX_BYTES);

        return [
            'files' => ['required_without:file', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', $fileRule],
            'file' => ['required_without:files', $fileRule],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        if ($this->hasFile('files')) {
            return array_values($this->file('files'));
        }

        $file = $this->file('file');

        return $file ? [$file] : [];
    }
}
