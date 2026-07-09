<?php

namespace App\Http\Requests;

use App\Enums\ApplicationReminderFrequency;
use App\Enums\ApplicationReminderType;
use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof Application
            && $this->user()
            && $application->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        $application = $this->route('application');
        $momentId = $this->input('application_moment_id');

        return [
            'type' => ['required', Rule::in(ApplicationReminderType::values())],
            'frequency' => ['required', Rule::in(ApplicationReminderFrequency::values())],
            'remind_at' => ['required', 'date'],
            'custom_message' => [
                Rule::requiredIf($this->input('type') === ApplicationReminderType::Custom->value),
                'nullable',
                'string',
                'max:1000',
            ],
            'application_moment_id' => [
                'nullable',
                'integer',
                Rule::exists('application_moments', 'id')
                    ->where('application_id', $application?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reminderAttributes(): array
    {
        $data = $this->validated();

        return [
            'type' => $data['type'],
            'frequency' => $data['frequency'],
            'remind_at' => $data['remind_at'],
            'custom_message' => $data['custom_message'] ?? null,
            'application_moment_id' => $data['application_moment_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'channel' => 'mail',
        ];
    }
}
