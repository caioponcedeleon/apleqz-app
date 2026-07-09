<?php

namespace App\Notifications;

use App\Models\ApplicationReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ApplicationReminder $reminder
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? config('app.locale');
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $application = $this->reminder->application;
            $typeKey = $this->reminder->type->value;

            $subject = __('notifications.reminder.subject', [
                'position' => $application->position,
                'company' => $application->company,
            ]);

            $reason = match ($this->reminder->type) {
                \App\Enums\ApplicationReminderType::Custom => $this->reminder->custom_message
                    ?: __('notifications.reminder.reasons.custom'),
                default => __('notifications.reminder.reasons.'.$typeKey),
            };

            $message = (new MailMessage)
                ->subject($subject)
                ->greeting(__('notifications.reminder.greeting', ['name' => $notifiable->name]))
                ->line(__('notifications.reminder.intro', [
                    'position' => $application->position,
                    'company' => $application->company,
                ]))
                ->line(__('notifications.reminder.reason_label', ['reason' => $reason]));

            if ($this->reminder->custom_message && $this->reminder->type !== \App\Enums\ApplicationReminderType::Custom) {
                $message->line($this->reminder->custom_message);
            }

            return $message
                ->action(
                    __('notifications.reminder.action'),
                    route('applications.edit', $application),
                )
                ->line(__('notifications.reminder.footer'));
        } finally {
            app()->setLocale($previous);
        }
    }
}
