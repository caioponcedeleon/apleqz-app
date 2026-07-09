<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobAlertSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_text' => ['nullable', 'string', 'max:5000'],
            'min_fit_score' => ['required', 'integer', 'min:0', 'max:100'],
            'job_alerts_enabled' => ['sometimes', 'boolean'],
            'subscribed_source_ids' => ['nullable', 'array'],
            'subscribed_source_ids.*' => [
                'uuid',
                Rule::exists('job_sources', 'id')->where('is_active', true),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('job_alerts_enabled')) {
                return;
            }

            if ($this->user()?->hasVerifiedEmail()) {
                return;
            }

            $validator->errors()->add(
                'job_alerts_enabled',
                __('app.job_alerts.email_verification_required'),
            );
        });
    }
}
