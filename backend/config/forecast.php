<?php

return [
    'timezone' => env('FORECAST_TIMEZONE', 'Asia/Jakarta'),

    'outlet_map' => [
        'Baso' => 'Outlet 1',
        'Minuman' => 'Outlet 2',
        'Pop Mie' => 'Outlet 3',
    ],

    'explanation' => [
        'provider' => env('FORECAST_EXPLANATION_PROVIDER', 'none'),
        'timeout' => (int) env('FORECAST_EXPLANATION_TIMEOUT', 30),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_FORECAST_MODEL', 'gpt-4o-mini'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_FORECAST_MODEL', 'llama3.2'),
        ],
    ],
];
