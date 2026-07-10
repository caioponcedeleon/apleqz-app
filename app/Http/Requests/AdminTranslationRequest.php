<?php

namespace App\Http\Requests;

use App\Models\TranslationLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TranslationLine|null $translationLine */
        $translationLine = $this->route('translationLine');
        $isEdit = $translationLine !== null;

        $localeRules = collect(config('app.available_locales', ['en']))
            ->mapWithKeys(fn (string $locale): array => [
                "values.{$locale}" => ['nullable', 'string'],
            ])
            ->all();

        return array_merge([
            'group' => [
                $isEdit ? 'prohibited' : 'required',
                'string',
                'max:100',
            ],
            'key' => [
                $isEdit ? 'prohibited' : 'required',
                'string',
                'max:255',
            ],
            'values' => ['required', 'array'],
        ], $localeRules);
    }

    public function withValidator($validator): void
    {
        if ($this->route('translationLine')) {
            return;
        }

        $validator->after(function ($validator): void {
            $group = $this->input('group');
            $key = $this->input('key');

            if (! is_string($group) || ! is_string($key)) {
                return;
            }

            if (TranslationLine::query()->where('group', $group)->where('key', $key)->exists()) {
                $validator->errors()->add('key', __('app.administration.translations_key_exists'));
            }
        });
    }
}
