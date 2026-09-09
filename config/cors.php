<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Restricted to the configured frontend origin(s) rather than the
    | framework default of '*' — an open CORS policy combined with a
    | bearer-token API is a credential-leakage risk from any origin that
    | can trick a logged-in user's browser into making a request.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('FRONTEND_URL', 'http://localhost:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After', 'Idempotent-Replayed'],

    // Let the browser cache the preflight for a day. At 0 every cross-origin
    // call from the SPA pays for an extra OPTIONS round trip first, which on a
    // POS terminal on shop wifi is a visible tax on every single request.
    'max_age' => 86400,

    'supports_credentials' => false,

];
