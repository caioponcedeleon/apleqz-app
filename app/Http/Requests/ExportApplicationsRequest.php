<?php

namespace App\Http\Requests;

use App\DataTransferObjects\ApplicationExportOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExportApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:'.implode(',', ApplicationExportOptions::FORMATS)],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['in:'.implode(',', ApplicationExportOptions::FIELDS)],
            'agentur_fur_arbeit' => ['sometimes', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'area_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'in:position,company,area,applied_at,status'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('agentur_fur_arbeit')) {
                return;
            }

            $fields = $this->input('fields', []);

            if (! is_array($fields) || $fields === []) {
                $validator->errors()->add('fields', __('app.export.fields_required'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'agentur_fur_arbeit' => $this->boolean('agentur_fur_arbeit'),
        ]);
    }
}
