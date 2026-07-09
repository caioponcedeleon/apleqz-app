<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationReminderRequest;
use App\Models\Application;
use App\Models\ApplicationReminder;
use Illuminate\Http\RedirectResponse;

class ApplicationReminderController extends Controller
{
    public function store(ApplicationReminderRequest $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->reminders()->create([
            ...$request->reminderAttributes(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('app.flash.reminder_created'));
    }

    public function update(
        ApplicationReminderRequest $request,
        Application $application,
        ApplicationReminder $reminder,
    ): RedirectResponse {
        $this->authorize('update', $application);
        abort_unless($reminder->application_id === $application->id, 404);

        $reminder->update([
            ...$request->reminderAttributes(),
            'sent_at' => null,
            'last_sent_at' => null,
        ]);

        return back()->with('success', __('app.flash.reminder_updated'));
    }

    public function toggleActive(Application $application, ApplicationReminder $reminder): RedirectResponse
    {
        $this->authorize('update', $application);
        abort_unless($reminder->application_id === $application->id, 404);

        $reminder->update(['is_active' => ! $reminder->is_active]);

        return back();
    }

    public function destroy(Application $application, ApplicationReminder $reminder): RedirectResponse
    {
        $this->authorize('update', $application);
        abort_unless($reminder->application_id === $application->id, 404);

        $reminder->delete();

        return back()->with('success', __('app.flash.reminder_deleted'));
    }
}
