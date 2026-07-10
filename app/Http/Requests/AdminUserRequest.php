<?php

namespace App\Http\Requests;

use App\Enums\JobAlertsTier;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'email_verified_at' => ['nullable', 'date'],
            'locale' => ['required', 'string', Rule::in(config('app.available_locales', ['en']))],
            'is_admin' => ['sometimes', 'boolean'],
            'application_files_enabled' => ['sometimes', 'boolean'],
            'personal_files_enabled' => ['sometimes', 'boolean'],
            'excel_import_enabled' => ['sometimes', 'boolean'],
            'job_alerts_tier' => ['required', 'string', Rule::in(JobAlertsTier::values())],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                Password::defaults(),
            ],
        ];
    }
}
