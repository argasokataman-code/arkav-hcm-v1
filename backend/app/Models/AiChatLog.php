<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $company_id
 * @property string      $session_id
 * @property string      $intent
 * @property bool        $allowed
 * @property string|null $deny_reason
 * @property array|null  $source_endpoints
 * @property \Carbon\Carbon $created_at
 */
class AiChatLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_uuid',
        'user_legacy_id',
        'company_id',
        'session_id',
        'intent',
        'allowed',
        'deny_reason',
        'source_endpoints',
    ];

    protected $casts = [
        'source_endpoints' => 'array',
        'allowed'          => 'boolean',
        'created_at'       => 'datetime',
    ];
}
