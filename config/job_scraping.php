<?php

return [
    'http_timeout' => (int) env('JOB_SCRAPE_HTTP_TIMEOUT', 15),
    'max_bytes' => (int) env('JOB_SCRAPE_MAX_BYTES', 2_097_152),
    'user_agent' => env('JOB_SCRAPE_USER_AGENT', 'ApleqzJobBot/1.0 (+https://apleqz.camk.net)'),
    'playwright' => [
        'node_binary' => env('JOB_SCRAPE_NODE_BINARY', 'node'),
        'script_path' => env('JOB_SCRAPE_PLAYWRIGHT_SCRIPT', base_path('scripts/scrape-page.mjs')),
        'timeout_ms' => (int) env('JOB_SCRAPE_PLAYWRIGHT_TIMEOUT', 60_000),
        // PHP-FPM often runs with a stripped PATH/HOME; set these in production.
        'home' => env('JOB_SCRAPE_HOME'),
        'browsers_path' => env('PLAYWRIGHT_BROWSERS_PATH'),
        'path' => env('JOB_SCRAPE_PATH', '/usr/local/bin:/usr/bin:/bin'),
        'node_options' => env('JOB_SCRAPE_NODE_OPTIONS', '--max-old-space-size=768'),
    ],
];
