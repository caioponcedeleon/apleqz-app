<?php

return [
    'driver' => env('JOB_MATCH_AI_DRIVER', 'mistral_cloud'),

    'description_max_chars' => (int) env('JOB_MATCH_DESCRIPTION_MAX_CHARS', 800),

    'detail_fetch_min_score' => (int) env('JOB_MATCH_DETAIL_FETCH_MIN_SCORE', 60),

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'ministral-3b-latest'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'model' => env('OLLAMA_MODEL', 'phi3:mini'),
    ],

    'pricing' => [
        'ministral-3b-latest' => ['input' => 0.10, 'output' => 0.10],
        'ministral-8b-latest' => ['input' => 0.15, 'output' => 0.15],
        'open-mistral-nemo' => ['input' => 0.15, 'output' => 0.15],
    ],

    'estimates' => [
        'seconds_per_evaluation' => (int) env('JOB_MATCH_SECONDS_PER_EVALUATION', 3),
        'default_prompt_tokens' => (int) env('JOB_MATCH_DEFAULT_PROMPT_TOKENS', 600),
        'default_completion_tokens' => (int) env('JOB_MATCH_DEFAULT_COMPLETION_TOKENS', 40),
    ],

    'digest' => [
        'max_per_email' => (int) env('JOB_MATCH_DIGEST_MAX_PER_EMAIL', 10),
    ],

    'regex' => [
        'recent_listings_per_source' => (int) env('JOB_MATCH_REGEX_RECENT_LISTINGS', 5),
    ],
];
