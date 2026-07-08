<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $waveId = $this->route('application_wave')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('application_waves', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($waveId),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
