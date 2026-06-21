<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Policy extends Model
{
    use AssignsUuid, SoftDeletes;

    protected $fillable = [
        'company_id',
        'company_uuid',
        'department_id',
        'department_uuid',
        'name',
        'description',
        'effective_date',
        'attachment_path',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
