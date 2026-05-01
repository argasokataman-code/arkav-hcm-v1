<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Note extends Model
{
    protected $table = 'notes';

    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'title',
        'content',
        'tag',
        'priority',
        'is_important',
        'is_trashed',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'is_trashed' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $note) {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid();
            }
        });
    }
}
