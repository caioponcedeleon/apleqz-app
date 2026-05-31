<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->excel_import_enabled;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
