<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobSourceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }
}
