<?php

return [
    'reminder_notification_lead_days' => (int) env('HERDWISE_REMINDER_LEAD_DAYS', 3),

    'assistant' => [
        'provider' => env('HERDWISE_ASSISTANT_PROVIDER', 'local'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('HERDWISE_OPENAI_MODEL', 'gpt-5.6-luna'),
            'endpoint' => 'https://api.openai.com/v1/responses',
            'connect_timeout_seconds' => 5,
            'timeout_seconds' => 20,
        ],
    ],
];
