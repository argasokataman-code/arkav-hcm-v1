<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Faq extends Model
{
    protected $table = 'faqs';

    protected $fillable = [
        'uuid',
        'company_id',
        'category',
        'question',
        'answer',
        'created_by',
        'updated_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $faq): void {
            if (empty($faq->uuid)) {
                $faq->uuid = (string) Str::uuid();
            }
        });
    }
}
