<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('applied_at') === '') {
            $this->merge(['applied_at' => null]);
        }
    }

    public function rules(): array
    {
        $statuses = ApplicationStatus::values();

        return [
            'area_id' => ['required', 'uuid', Rule::exists('areas', 'id')->where('user_id', $this->user()->id)],
            'position' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'applied_at' => [
                Rule::requiredIf(
                    fn () => ApplicationStatus::tryFrom($this->input('status', ''))?->requiresAppliedDate() ?? true
                ),
                'nullable',
                'date',
            ],
            'rejected_at' => [
                'nullable',
                'date',
                Rule::when($this->filled('applied_at'), 'after_or_equal:applied_at'),
            ],
            'status' => ['required', Rule::in($statuses)],
            'interview_date' => ['nullable', 'date'],
            'channel' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'job_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $status = ApplicationStatus::tryFrom($this->input('status', ''));

            if ($status?->requiresRejectionDate() && ! $this->filled('rejected_at')) {
                $validator->errors()->add('rejected_at', __('validation.required'));
            }
        });
    }
}
