<?php

return [
    'enabled' => filter_var(env('SMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'provider' => env('SMS_PROVIDER', 'textlk'),
    'api_key' => env('SMS_API_KEY'),
    'sender_id' => env('SMS_SENDER_ID'),
    'endpoint' => env('SMS_ENDPOINT', 'https://app.text.lk/api/v3/sms/send'),
    'view_endpoint' => env('SMS_VIEW_ENDPOINT', 'https://app.text.lk/api/v3/sms'),
    'timeout' => (int) env('SMS_TIMEOUT', 20),
    'max_attempts' => (int) env('SMS_MAX_ATTEMPTS', 5),
    'retry_after_minutes' => (int) env('SMS_RETRY_AFTER_MINUTES', 15),
];
