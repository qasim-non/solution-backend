<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | Access tokens are short-lived and sent on every API request. Refresh
    | tokens are long-lived, single-use, and only accepted by /auth/refresh.
    |
    | These lifetimes are applied per token through Sanctum's `expires_at`
    | column, so keep `sanctum.expiration` empty: that setting is a global cap
    | and would also expire refresh tokens.
    |
    */

    'access_ttl_minutes' => (int) env('AUTH_ACCESS_TTL_MINUTES', 30),

    'refresh_ttl_days' => (int) env('AUTH_REFRESH_TTL_DAYS', 30),

];
