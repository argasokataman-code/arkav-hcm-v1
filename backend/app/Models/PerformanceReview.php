<?php
namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PerformanceReview extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'cycle_id',
        'user_id',
        'manager_user_id',
        'template_id',
        'status',
        'self_note',
        'manager_note',
        'final_note',
        'self_total_score',
        'manager_total_score',
        'final_total_score',
    ];
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    protected function casts(): array
    {
        return [
            'self_total_score' => 'decimal:2',
            'manager_total_score' => 'decimal:2',
            'final_total_score' => 'decimal:2',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicatorTemplate::class, 'template_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PerformanceReviewScore::class, 'review_id');
    }
}

