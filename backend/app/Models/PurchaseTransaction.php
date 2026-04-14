<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'transaction_code',
        'company_id',
        'subscription_id',
        'package_addon_id',
        'transaction_type',
        'description',
        'amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->transaction_code)) {
                $model->transaction_code = self::generateCode();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function packageAddon(): BelongsTo
    {
        return $this->belongsTo(PackageAddon::class, 'package_addon_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if transaction is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' && $this->paid_at !== null;
    }

    /**
     * Check if transaction is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' && $this->due_date?->isPast();
    }

    /**
     * Generate transaction code
     */
    public static function generateCode(): string
    {
        $year = date('Y');
        $randomId = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        return "TXN-{$year}-{$randomId}";
    }
}
