<?php

namespace App\Support;

class JobScrapePageUrlResolver
{
    /**
     * @param  array<string, mixed>  $pagination
     * @return list<array{url: string, page: int}>
     */
    public function pages(string $baseUrl, array $pagination): array
    {
        $type = is_string($pagination['type'] ?? null) ? $pagination['type'] : 'none';

        if ($type === 'none') {
            return [['url' => $baseUrl, 'page' => 1]];
        }

        if ($type !== 'query_param') {
            return [['url' => $baseUrl, 'page' => 1]];
        }

        $param = is_string($pagination['param'] ?? null) && $pagination['param'] !== ''
            ? $pagination['param']
            : 'page';
        $start = max(1, (int) ($pagination['start'] ?? 1));
        $maxPages = max(1, min(50, (int) ($pagination['max_pages'] ?? 10)));

        $pages = [];

        for ($offset = 0; $offset < $maxPages; $offset++) {
            $pageNum = $start + $offset;
            $pages[] = [
                'url' => $this->urlForPage($baseUrl, $param, $pageNum, $start),
                'page' => $pageNum,
            ];
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $pagination
     */
    public function shouldStopAfterEmptyPage(array $pagination, int $pagesAlreadyFetched): bool
    {
        if (($pagination['type'] ?? 'none') === 'none') {
            return true;
        }

        if (($pagination['stop_when_empty'] ?? true) === false) {
            return false;
        }

        return $pagesAlreadyFetched >= 1;
    }

    protected function urlForPage(string $baseUrl, string $param, int $pageNum, int $start): string
    {
        if ($pageNum === 1 && $start === 1) {
            return $baseUrl;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts)) {
            return $baseUrl;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query[$param] = (string) $pageNum;

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
        $path = $parts['path'] ?? '';
        $builtQuery = http_build_query($query);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$auth.$host.$port.$path.'?'.$builtQuery.$fragment;
    }
}
