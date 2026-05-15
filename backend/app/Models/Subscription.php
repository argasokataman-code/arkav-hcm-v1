<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscription extends Model
{
    use HasFactory, AssignsUuid;

    protected $fillable = [
        'company_id',
        'package_uuid',
        'plan_code',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'auto_renew',
        'billing_cycle',
        'amount',
        'terminated_at',
        'termination_reason',
        'suspended_at',
        'suspension_reason',
        'grace_started_at',
        'grace_ends_at',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'terminated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'grace_started_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'auto_renew' => 'boolean',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_uuid', 'uuid');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany('id');
    }

    /**
     * Get active subscription for a company
     */
    public static function activeForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial', 'grace_period'])
            ->where(function ($q): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Check if subscription is in trial period
     */
    public function isInTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at?->isFuture();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * Calculate price based on billing cycle
     */
    public function getPrice(): float
    {
        if ($this->billing_cycle === 'yearly') {
            return (float)$this->package->yearly_price;
        }
        return (float)$this->package->monthly_price;
    }

    /**
     * Get subscription duration in days
     */
    public function getDurationDays(): int
    {
        if ($this->ends_at === null) return 0;
        return $this->starts_at->diffInDays($this->ends_at);
    }
}
