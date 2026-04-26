<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use App\Models\User;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Team extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::creating(function (self $team): void {
            if (! Schema::hasColumn($team->getTable(), 'uuid') || ! empty($team->uuid)) {
                return;
            }

            $team->uuid = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'department_id',
        'team_lead_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_id' => 'integer',
        'team_lead_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class, 'team_id');
    }

    public function memberProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'team_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }
}
