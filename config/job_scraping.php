<?php

return [
    'http_timeout' => (int) env('JOB_SCRAPE_HTTP_TIMEOUT', 15),
    'max_bytes' => (int) env('JOB_SCRAPE_MAX_BYTES', 2_097_152),
    'user_agent' => env('JOB_SCRAPE_USER_AGENT', 'ApleqzJobBot/1.0 (+https://apleqz.camk.net)'),
];
