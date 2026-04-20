<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait AssignsUuid
{
    public function initializeAssignsUuid(): void
    {
        // Keep legacy integer PK behavior until full FK migration is completed.
        // This trait only guarantees UUID assignment + UUID route binding.
    }

    protected static function bootAssignsUuid(): void
    {
        static::creating(function ($model): void {
            if (! Schema::hasColumn($model->getTable(), 'uuid')) {
                return;
            }

            if (! empty($model->uuid)) {
                return;
            }

            $attempts = 0;
            do {
                $uuid = (string) Str::uuid();
                $exists = static::query()->where('uuid', $uuid)->exists();
                $attempts++;
            } while ($exists && $attempts < 5);

            if ($exists) {
                throw new \RuntimeException('Failed to generate a unique UUID after 5 attempts.');
            }

            $model->uuid = $uuid;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}