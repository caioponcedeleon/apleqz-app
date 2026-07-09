<?php

namespace App\Services;

use App\Enums\ApplicationReminderFrequency;
use App\Models\ApplicationReminder;
use Carbon\Carbon;

class ApplicationReminderDispatchService
{
    /**
     * @return list<int>
     */
    public function sendDueReminders(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $sentIds = [];

        $reminders = ApplicationReminder::query()
            ->with(['application', 'user', 'moment'])
            ->where('is_active', true)
            ->where('remind_at', '<=', $now)
            ->whereHas('user', function ($query) {
                $query->where('email_reminders_enabled', true)
                    ->whereNotNull('email_verified_at');
            })
            ->get();

        $sentTodayByApplication = [];

        foreach ($reminders as $reminder) {
            if (! $this->isDue($reminder, $now)) {
                continue;
            }

            $applicationKey = $reminder->user_id.'-'.$reminder->application_id;

            if (isset($sentTodayByApplication[$applicationKey])) {
                continue;
            }

            $reminder->user->notify(new \App\Notifications\ApplicationReminderNotification($reminder));
            $this->markSent($reminder, $now);
            $sentIds[] = $reminder->id;
            $sentTodayByApplication[$applicationKey] = true;
        }

        return $sentIds;
    }

    public function isDue(ApplicationReminder $reminder, Carbon $now): bool
    {
        if ($reminder->frequency === ApplicationReminderFrequency::Once) {
            return $reminder->sent_at === null
                && $reminder->remind_at->lte($now);
        }

        if ($reminder->frequency === ApplicationReminderFrequency::Daily) {
            if ($reminder->remind_at->gt($now)) {
                return false;
            }

            return $reminder->last_sent_at === null
                || $reminder->last_sent_at->toDateString() < $now->toDateString();
        }

        if ($reminder->frequency === ApplicationReminderFrequency::Weekly) {
            if ($reminder->remind_at->gt($now)) {
                return false;
            }

            return $reminder->last_sent_at === null
                || $reminder->last_sent_at->lte($now->copy()->subDays(7));
        }

        return false;
    }

    public function markSent(ApplicationReminder $reminder, Carbon $now): void
    {
        if ($reminder->frequency === ApplicationReminderFrequency::Once) {
            $reminder->update([
                'sent_at' => $now,
                'last_sent_at' => $now,
            ]);

            return;
        }

        $reminder->update(['last_sent_at' => $now]);
    }
}
