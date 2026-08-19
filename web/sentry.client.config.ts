import * as Sentry from "@sentry/nextjs";

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,

  environment: process.env.NEXT_PUBLIC_SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,

  tracesSampleRate: 0.2,

  profilesSampleRate: 0.2,

  beforeSend(event) {
    // Filter noisy client-side errors
    if (event.exception) {
      const exceptions = event.exception.values;
      const filtered = exceptions?.filter((ex: any) => {
        const type = ex.type || '';
        const message = ex.value || '';

        // Filter noisy React/JS errors
        const noisyPatterns = [
          'React',
          'Component',
          'fiber',
          'scheduleWork',
          'render',
          'useEffect',
        ];

        return !noisyPatterns.some((p: string) => type.includes(p) || message.includes(p));
      });

      if (filtered.length < exceptions.length) {
        event.exception.values = filtered;
      }
    }

    return event;
  },

  release: process.env.SENTRY_RELEASE || process.env.npm_package_version || 'smartpos-pro',

  enabled:
    process.env.NODE_ENV === "production" && !!process.env.NEXT_PUBLIC_SENTRY_DSN,
});
