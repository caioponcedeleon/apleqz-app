<?php

namespace App\Notifications;

use App\Models\JobMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JobMatchesDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, JobMatch>  $matches
     */
    public function __construct(
        public Collection $matches,
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
        $count = $this->matches->count();
        $actionUrl = route('job-alerts.matches');

        return (new MailMessage)
            ->subject(trans_choice('notifications.job_digest.subject', $count, ['count' => $count]))
            ->markdown('mail.job-matches-digest', [
                'greeting' => __('notifications.job_digest.greeting', ['name' => $notifiable->name]),
                'intro' => trans_choice('notifications.job_digest.intro', $count, ['count' => $count]),
                'matches' => $this->formattedMatches(),
                'actionText' => __('notifications.job_digest.action'),
                'actionUrl' => $actionUrl,
                'displayableActionUrl' => $actionUrl,
                'footer' => __('notifications.job_digest.footer'),
                'salutation' => __('notifications.mail.regards')."\n".config('app.name'),
            ]);
    }

    /**
     * @return list<array{title: string, company: string, score: int, reason: string, url: string|null}>
     */
    protected function formattedMatches(): array
    {
        return $this->matches->map(function (JobMatch $match): array {
            $listing = $match->jobListing;

            return [
                'title' => $listing?->title ?? '—',
                'company' => $listing?->company ?? '—',
                'score' => $match->fit_score,
                'reason' => Str::limit($match->reason, 200),
                'url' => is_string($listing?->url) && $listing->url !== '' ? $listing->url : null,
            ];
        })->all();
    }
}
