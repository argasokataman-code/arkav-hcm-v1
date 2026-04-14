<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardMetric extends Model
{
    protected $table = 'dashboard_metrics';

    protected $fillable = [
        'company_id',
        'metric_date',
        'metric_key',
        'metric_value',
        'metric_metadata',
        'calculated_at',
        'next_calculation_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'metric_date' => 'date',
        'metric_value' => 'float',
        'metric_metadata' => 'json',
        'calculated_at' => 'datetime',
        'next_calculation_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get metric by key for a specific date
     */
    public static function getMetric(string $key, $date = null)
    {
        $query = self::where('metric_key', $key);

        if ($date) {
            $query->where('metric_date', $date);
        } else {
            $query->where('metric_date', now()->toDateString());
        }

        return $query->first();
    }

    /**
     * Get metric trend (last N periods)
     */
    public static function getTrend(string $key, int $periods = 12)
    {
        return self::where('metric_key', $key)
            ->latest('metric_date')
            ->limit($periods)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Check if metric needs recalculation
     */
    public function needsRecalculation(): bool
    {
        return $this->next_calculation_at && now()->isAfter($this->next_calculation_at);
    }

    /**
     * Mark metric as calculated
     */
    public function markCalculated(): self
    {
        $this->calculated_at = now();
        $this->next_calculation_at = now()->addHour();
        $this->save();

        return $this;
    }
}
