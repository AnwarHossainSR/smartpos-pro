<?php

namespace App\Support\Concerns;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;

/**
 * Applied to money- and access-sensitive models to record every
 * created/updated/deleted change to the audit trail automatically.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            app(AuditLogService::class)->log(
                static::auditModelName().'.created',
                $model,
                null,
                $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function ($model): void {
            $changes = collect($model->getChanges())->except(['updated_at'])->all();

            if (empty($changes)) {
                return;
            }

            $old = collect($model->getOriginal())->only(array_keys($changes))->all();

            app(AuditLogService::class)->log(
                static::auditModelName().'.updated',
                $model,
                $model->auditableAttributes($old),
                $model->auditableAttributes($changes),
            );
        });

        static::deleted(function ($model): void {
            app(AuditLogService::class)->log(
                static::auditModelName().'.deleted',
                $model,
                $model->auditableAttributes($model->getAttributes()),
                null,
            );
        });
    }

    protected static function auditModelName(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = property_exists($this, 'auditExcept')
            ? $this->auditExcept
            : ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

        return collect($attributes)->except($excluded)->all();
    }
}
