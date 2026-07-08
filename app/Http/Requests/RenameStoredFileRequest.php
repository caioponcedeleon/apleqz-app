<?php

namespace App\Http\Requests;

use App\Models\ApplicationFile;
use App\Models\UserFile;
use Illuminate\Foundation\Http\FormRequest;

class RenameStoredFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $file = $this->route('file');

        if ($file instanceof ApplicationFile) {
            $application = $this->route('application');

            return $user
                && $user->application_files_enabled
                && $file->user_id === $user->id
                && $application
                && $file->application_id === $application->id;
        }

        if ($file instanceof UserFile) {
            return $user
                && $user->personal_files_enabled
                && $file->user_id === $user->id;
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        $displayName = $this->input('display_name');

        if (is_string($displayName) && trim($displayName) === '') {
            $this->merge(['display_name' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'display_name' => [
                'nullable',
                'string',
                'max:255',
                'not_regex:/[\/\\\\]/',
            ],
        ];
    }
}
