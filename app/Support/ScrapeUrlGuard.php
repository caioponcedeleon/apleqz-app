<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ScrapeUrlGuard
{
    /**
     * @throws ValidationException
     */
    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw ValidationException::withMessages([
                'url' => 'The scrape URL must be a valid absolute HTTP(S) URL.',
            ]);
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'url' => 'Only HTTP and HTTPS URLs can be scraped.',
            ]);
        }

        $host = strtolower($parts['host']);

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages([
                'url' => 'Localhost URLs cannot be scraped.',
            ]);
        }

        foreach ($this->resolveIps($host) as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw ValidationException::withMessages([
                    'url' => 'The scrape URL resolves to a blocked network address.',
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === [] || $records === false) {
            throw ValidationException::withMessages([
                'url' => 'The scrape URL host could not be resolved.',
            ]);
        }

        return collect($records)
            ->map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
