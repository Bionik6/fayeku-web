<?php

namespace App\Traits\Shared;

use Illuminate\Support\Str;

trait HasPublicCode
{
    public static function bootHasPublicCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_code)) {
                $model->public_code = static::generateUniquePublicCode();
            }
        });
    }

    public static function generateUniquePublicCode(): string
    {
        do {
            $code = Str::random(8);
        } while (static::query()->where('public_code', $code)->exists());

        return $code;
    }
}
