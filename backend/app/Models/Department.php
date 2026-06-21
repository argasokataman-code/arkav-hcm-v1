<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use AssignsUuid, SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }
}
