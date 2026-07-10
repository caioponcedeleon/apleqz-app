<?php

namespace App\Http\Requests;

use App\Enums\JobAlertsTier;
use App\Models\UserJobProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobAlertSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasJobAlerts() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tier = $this->user()?->jobAlertsTier() ?? JobAlertsTier::None;

        $rules = [
            'min_fit_score' => ['required', 'integer', 'min:0', 'max:100'],
            'job_alerts_enabled' => ['sometimes', 'boolean'],
            'subscribed_source_ids' => ['nullable', 'array'],
            'subscribed_source_ids.*' => [
                'uuid',
                Rule::exists('job_sources', 'id')->where('is_active', true),
            ],
        ];

        if ($tier === JobAlertsTier::Ai) {
            $rules['profile_text'] = ['nullable', 'string', 'max:'.UserJobProfile::PROFILE_TEXT_MAX_LENGTH];
        }

        if ($tier === JobAlertsTier::Regex) {
            $rules['include_keywords'] = ['nullable', 'string', 'max:5000'];
            $rules['exclude_keywords'] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
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
