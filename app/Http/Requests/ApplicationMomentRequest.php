<?php

namespace App\Http\Requests;

use App\Enums\ApplicationMomentType;
use App\Models\Application;
use App\Models\ApplicationMoment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationMomentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof Application || ! $this->user()) {
            return false;
        }

        if ($application->user_id !== $this->user()->id) {
            return false;
        }

        $moment = $this->route('moment');

        if ($moment instanceof ApplicationMoment) {
            return $moment->application_id === $application->id && ! $moment->is_system;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ApplicationMomentType::userEditableValues())],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function momentAttributes(): array
    {
        return $this->validated();
    }
}
