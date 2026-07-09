<?php

namespace App\Services;

use App\Models\Application;
use App\Models\JobListing;

class JobMatchApplicationOverlapChecker
{
    /**
     * @var array<int, array{positions: list<string>, urls: list<string>}>
     */
    protected array $userFingerprints = [];

    public function overlapsExistingApplication(int $userId, JobListing $listing): bool
    {
        $fingerprints = $this->fingerprintsForUser($userId);
        $title = self::normalizePosition($listing->title);
        $url = self::normalizeUrl($listing->url);

        if ($title !== '' && in_array($title, $fingerprints['positions'], true)) {
            return true;
        }

        if ($url !== '' && in_array($url, $fingerprints['urls'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{positions: list<string>, urls: list<string>}
     */
    protected function fingerprintsForUser(int $userId): array
    {
        if (! isset($this->userFingerprints[$userId])) {
            $applications = Application::query()
                ->where('user_id', $userId)
                ->get(['position', 'job_url']);

            $this->userFingerprints[$userId] = [
                'positions' => $applications
                    ->map(fn (Application $application): string => self::normalizePosition($application->position))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->unique()
                    ->values()
                    ->all(),
                'urls' => $applications
                    ->map(fn (Application $application): string => self::normalizeUrl($application->job_url))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        return $this->userFingerprints[$userId];
    }

    public static function normalizePosition(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower($normalized);
    }

    public static function normalizeUrl(?string $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        return mb_strtolower(rtrim($normalized, '/'));
    }
}
