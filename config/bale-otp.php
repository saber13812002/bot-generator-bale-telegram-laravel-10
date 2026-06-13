<?php

return [
    'client_id' => env('BALE_SAFIR_CLIENT_ID'),
    'client_secret' => env('BALE_SAFIR_CLIENT_SECRET'),
    'base_url' => env('BALE_SAFIR_BASE_URL', 'https://safir.bale.ai/api/v2'),
    'otp_length' => (int) env('BALE_OTP_LENGTH', 6),
    'otp_ttl' => (int) env('BOT_OWNER_OTP_TTL', 300),
    'max_attempts' => (int) env('BOT_OWNER_OTP_MAX_ATTEMPTS', 5),
    'rate_limit_per_hour' => (int) env('BALE_OTP_RATE_LIMIT_PER_HOUR', 30),
    'token_cache_key' => 'bale_safir_access_token',
];
