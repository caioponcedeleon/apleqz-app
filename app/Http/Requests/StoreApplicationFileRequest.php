<?php

namespace App\Http\Requests;

use App\Services\StoredFileService;
use Illuminate\Foundation\Http\FormRequest;
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
        return [
            'file' => [
                'required',
                File::types(StoredFileService::ALLOWED_EXTENSIONS)
                    ->max(StoredFileService::MAX_BYTES),
            ],
        ];
    }
}
