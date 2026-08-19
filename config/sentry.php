<?php

use Sentry\Event;

return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') !== null
        ? (float) env('SENTRY_TRACES_SAMPLE_RATE')
        : 0.2,

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') !== null
        ? (float) env('SENTRY_PROFILES_SAMPLE_RATE')
        : 0.2,

    'send_default_pii' => false,

    'error_types' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,

    'before_send' => function (Event $event): ?Event {
        if (app()->environment('local', 'testing')) {
            return null;
        }

        $exception = $event->exception;
        if ($exception) {
            $className = get_class($exception);

            // Filter noisy exceptions that shouldn't be tracked
            $noisyPatterns = [
                'ErrorException',
                'MaxExecutionTime',
                'MaximumExecutionTime',
                'Connection refused',
                'Connection timed out',
                'SQLSTATE[HY000]',
            ];

            foreach ($noisyPatterns as $pattern) {
                if (str_contains($className, $pattern) || str_contains($exception->getMessage(), $pattern)) {
                    return null;
                }
            }
        }

        return $event;
    },

];
