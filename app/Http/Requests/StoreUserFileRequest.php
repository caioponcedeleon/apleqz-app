<?php

namespace App\Http\Requests;

use App\Services\StoredFileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreUserFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->personal_files_enabled;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(StoredFileService::ALLOWED_EXTENSIONS)
                    ->max(StoredFileService::MAX_BYTES),
            ],
        ];
    }
}
