<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AuthToken extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'user_uuid') && ! $record->user_uuid && $record->user_id) {
                $record->user_uuid = (string) (User::query()->where('id', $record->user_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'user_uuid',
        'token_hash',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'user_uuid' => 'string',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
