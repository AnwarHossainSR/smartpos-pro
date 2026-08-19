<?php

return [

    // Project API key (phc_...). Leave blank to disable telemetry entirely.
    'key' => env('POSTHOG_KEY'),

    'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),

    // Local and test runs should never phone home.
    'enabled' => env('POSTHOG_ENABLED', env('APP_ENV') === 'production'),

];
