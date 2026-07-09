<?php

namespace App\Http\Requests;

use App\Enums\ApplicationReminderFrequency;
use App\Enums\ApplicationReminderType;
use App\Models\Application;
use App\Support\ReminderSchedule;
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
        $frequency = $this->input('frequency');

        return [
            'type' => ['required', Rule::in(ApplicationReminderType::values())],
            'frequency' => ['required', Rule::in(ApplicationReminderFrequency::values())],
            'remind_at' => [
                Rule::requiredIf($frequency === ApplicationReminderFrequency::Once->value),
                'nullable',
                'date',
            ],
            'remind_weekday' => [
                Rule::requiredIf($frequency === ApplicationReminderFrequency::Weekly->value),
                'nullable',
                'integer',
                Rule::in(ReminderSchedule::weekdayOptions()),
            ],
            'remind_day_of_month' => [
                Rule::requiredIf($frequency === ApplicationReminderFrequency::Monthly->value),
                'nullable',
                'integer',
                Rule::in(ReminderSchedule::dayOfMonthOptions()),
            ],
            'remind_time' => ['required', 'date_format:H:i', Rule::in(ReminderSchedule::timeSlotOptions())],
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
            'remind_at' => ReminderSchedule::combineFromRequest($data),
            'custom_message' => $data['custom_message'] ?? null,
            'application_moment_id' => $data['application_moment_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'channel' => 'mail',
        ];
    }
}
