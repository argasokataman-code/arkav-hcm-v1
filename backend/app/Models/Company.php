<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use AssignsUuid, HasFactory;

    protected $fillable = [
        'id',
        'code',
        'name',
        'legal_name',
        'status',
        'owner_user_id',
        'timezone',
        'currency',
        'country_code',
        'onboarding_consent_accepted',
        'onboarding_consent_at',
        'onboarding_consent_ip',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany('id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class, 'company_uuid', 'uuid');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with(['package.features'])
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($q): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    public function hasFeature(string $featureCode): bool
    {
        $subscription = $this->activeSubscription();
        if (! $subscription || ! $subscription->package) {
            return false;
        }

        return $subscription->package->hasFeature($featureCode);
    }
}
