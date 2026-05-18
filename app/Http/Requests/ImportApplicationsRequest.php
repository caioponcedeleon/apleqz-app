<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
