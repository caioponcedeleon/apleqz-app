<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobAlertsTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->withCount('applications')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'email',
                    'locale',
                    'is_admin',
                    'application_files_enabled',
                    'personal_files_enabled',
                    'excel_import_enabled',
                    'job_alerts_tier',
                    'created_at',
                ])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'is_admin' => (bool) $user->is_admin,
                    'application_files_enabled' => (bool) $user->application_files_enabled,
                    'personal_files_enabled' => (bool) $user->personal_files_enabled,
                    'excel_import_enabled' => (bool) $user->excel_import_enabled,
                    'job_alerts_tier' => $user->job_alerts_tier ?? JobAlertsTier::None->value,
                    'job_alerts_tier_label' => $user->jobAlertsTier()->label(),
                    'applications_count' => (int) $user->applications_count,
                    'created_at' => $user->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', $this->formOptions());
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        User::query()->create($this->userAttributes($request->validated()));

        return redirect()
            ->route('administration.users.index')
            ->with('success', __('app.administration.users_created'));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            ...$this->formOptions(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d\TH:i'),
                'locale' => $user->locale,
                'is_admin' => (bool) $user->is_admin,
                'application_files_enabled' => (bool) $user->application_files_enabled,
                'personal_files_enabled' => (bool) $user->personal_files_enabled,
                'excel_import_enabled' => (bool) $user->excel_import_enabled,
                'job_alerts_tier' => $user->job_alerts_tier ?? JobAlertsTier::None->value,
            ],
        ]);
    }

    public function update(AdminUserRequest $request, User $user): RedirectResponse
    {
        $user->update($this->userAttributes($request->validated(), $user));

        return redirect()
            ->route('administration.users.index')
            ->with('success', __('app.administration.users_updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->id === $user->id) {
            return back()->with('error', __('app.administration.users_cannot_delete_self'));
        }

        $user->delete();

        return redirect()
            ->route('administration.users.index')
            ->with('success', __('app.administration.users_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'localeOptions' => collect(config('app.available_locales', ['en']))
                ->map(fn (string $locale): array => [
                    'value' => $locale,
                    'label' => config("app.locale_labels.{$locale}", strtoupper($locale)),
                ])
                ->values()
                ->all(),
            'jobAlertsTiers' => collect(JobAlertsTier::cases())
                ->map(fn (JobAlertsTier $tier): array => [
                    'value' => $tier->value,
                    'label' => $tier->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function userAttributes(array $validated, ?User $user = null): array
    {
        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => filled($validated['email_verified_at'] ?? null)
                ? $validated['email_verified_at']
                : null,
            'locale' => $validated['locale'],
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'application_files_enabled' => (bool) ($validated['application_files_enabled'] ?? false),
            'personal_files_enabled' => (bool) ($validated['personal_files_enabled'] ?? false),
            'excel_import_enabled' => (bool) ($validated['excel_import_enabled'] ?? false),
            'job_alerts_tier' => $validated['job_alerts_tier'],
        ];

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        return $attributes;
    }
}
