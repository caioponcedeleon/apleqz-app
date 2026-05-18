<?php

namespace App\Http\Requests;

use App\Enums\ApplicationMomentType;
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

        $moments = $this->input('moments', []);

        if (is_array($moments)) {
            $this->merge([
                'moments' => array_values(array_map(function (array $moment) {
                    if (($moment['occurred_at'] ?? '') === '') {
                        $moment['occurred_at'] = null;
                    }

                    if (($moment['notes'] ?? '') === '') {
                        $moment['notes'] = null;
                    }

                    return $moment;
                }, $moments)),
            ]);
        }
    }

    public function rules(): array
    {
        $statuses = ApplicationStatus::values();
        $momentTypes = ApplicationMomentType::values();

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
            'status' => ['required', Rule::in($statuses)],
            'channel' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'job_url' => ['nullable', 'url', 'max:2048'],
            'moments' => ['nullable', 'array'],
            'moments.*.id' => ['nullable', 'integer'],
            'moments.*.type' => ['required_with:moments.*.occurred_at', Rule::in($momentTypes)],
            'moments.*.occurred_at' => ['required_with:moments.*.type', 'nullable', 'date'],
            'moments.*.notes' => ['nullable', 'string'],
        ];
    }

    public function applicationAttributes(): array
    {
        return $this->safe()->except('moments');
    }

    public function momentsPayload(): array
    {
        return $this->validated('moments') ?? [];
    }
}
