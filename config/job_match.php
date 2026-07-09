<?php

return [
    'driver' => env('JOB_MATCH_AI_DRIVER', 'mistral_cloud'),

    'description_max_chars' => (int) env('JOB_MATCH_DESCRIPTION_MAX_CHARS', 800),

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'ministral-3b-latest'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434/v1'),
        'model' => env('OLLAMA_MODEL', 'phi3:mini'),
    ],
];
