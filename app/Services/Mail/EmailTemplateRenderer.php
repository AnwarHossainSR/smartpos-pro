<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Support\Mail\EmailTemplateCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Renders one catalogued email, preferring the tenant's override.
 *
 * Templates are markdown with `{variable}` placeholders, never Blade: a tenant
 * admin editing their own receipt email must not be able to reach into the
 * container or the calling scope, and `Blade::render()` on stored text would let
 * them. Raw HTML in the markdown is escaped for the same reason — the editor
 * writes copy, not markup.
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, string|int|float|null>  $variables
     * @return array{subject: string, html: string, markdown: string}
     */
    public function render(string $key, array $variables, ?string $tenantId = null, ?EmailTemplate $override = null): array
    {
        $default = EmailTemplateCatalog::find($key);

        if (! $default) {
            throw new \InvalidArgumentException("Unknown email template [{$key}].");
        }

        // Mail is rendered on the queue, where no tenant is bound: the caller passes
        // the sale's or the owner's tenant so the override lookup can't fail closed.
        // A caller previewing an unsaved draft hands the override in directly.
        $override ??= $tenantId !== null
            ? app(TenantContext::class)->runAs($tenantId, fn () => $this->overrideFor($key))
            : $this->overrideFor($key);

        $subject = strip_tags($this->interpolate($override->subject ?? $default['subject'], $variables));

        // Angle brackets go before anything else touches the text, so the same string
        // is what the preview renders and what the mailer sends: markdown syntax is
        // untouched, embedded markup is inert either way.
        $markdown = str_replace(
            ['<', '>'],
            ['&lt;', '&gt;'],
            $this->interpolate($override->body ?? $default['body'], $variables),
        );

        return [
            'subject' => $subject,
            'markdown' => $markdown,
            'html' => (string) Str::markdown($markdown),
        ];
    }

    public function overrideFor(string $key): ?EmailTemplate
    {
        return EmailTemplate::where('key', $key)->first();
    }

    /** @param  array<string, string|int|float|null>  $variables */
    private function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $m) => array_key_exists($m[1], $variables) ? (string) $variables[$m[1]] : $m[0],
            $template,
        ) ?? $template;
    }
}
