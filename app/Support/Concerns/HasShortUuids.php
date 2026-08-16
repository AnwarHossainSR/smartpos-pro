<?php

namespace App\Support\Concerns;

use App\Support\ShortUuid;
use Illuminate\Database\Eloquent\Model;

trait HasShortUuids
{
    public static function bootHasShortUuids(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = ShortUuid::generate();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    public function newUniqueId(): string
    {
        return ShortUuid::generate();
    }

    public function uniqueIds(): bool
    {
        return true;
    }
}
