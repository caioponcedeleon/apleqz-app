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

    /**
     * Send every active reminder immediately, ignoring schedule and sent state.
     * Intended for local/testing only.
     *
     * @return list<int>
     */
    public function sendAllRemindersForTesting(bool $markSent = true, ?Carbon $now = null, ?callable $onSending = null): array
    {
        $now = $now ?? now();
        $sentIds = [];

        $reminders = ApplicationReminder::query()
            ->with(['application', 'user', 'moment'])
            ->where('is_active', true)
            ->whereHas('user', function ($query) {
                $query->where('email_reminders_enabled', true)
                    ->whereNotNull('email_verified_at');
            })
            ->get();

        foreach ($reminders as $reminder) {
            $onSending?->call($this, $reminder);

            $reminder->user->notify(new \App\Notifications\ApplicationReminderNotification($reminder));

            if ($markSent) {
                $this->markSent($reminder, $now);
            }

            $sentIds[] = $reminder->id;
        }

        return $sentIds;
    }

    public function isDue(ApplicationReminder $reminder, Carbon $now): bool
    {
        if ($reminder->frequency === ApplicationReminderFrequency::Once) {
            return $reminder->sent_at === null
                && $reminder->remind_at->lte($now);
        }

        if ($reminder->remind_at->gt($now)) {
            return false;
        }

        $slot = $now->copy()->setTimeFromTimeString($reminder->remind_at->format('H:i:s'));

        if ($now->lt($slot)) {
            return false;
        }

        if ($reminder->frequency === ApplicationReminderFrequency::Daily) {
            return $reminder->last_sent_at === null
                || $reminder->last_sent_at->lt($slot);
        }

        if ($reminder->frequency === ApplicationReminderFrequency::Weekly) {
            if ($now->dayOfWeek !== $reminder->remind_at->dayOfWeek) {
                return false;
            }

            return $reminder->last_sent_at === null
                || $reminder->last_sent_at->lt($slot);
        }

        if ($reminder->frequency === ApplicationReminderFrequency::Monthly) {
            $scheduledDay = min($reminder->remind_at->day, $now->daysInMonth);

            if ($now->day !== $scheduledDay) {
                return false;
            }

            return $reminder->last_sent_at === null
                || $reminder->last_sent_at->lt($slot);
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
