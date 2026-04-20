<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomDomain extends Model
{
    use SoftDeletes, AssignsUuid;

    protected $fillable = [
        'company_id',
        'domain',
        'status',
        'verification_token',
        'verified_at',
        'verification_failed_at',
        'verification_method',
        'verification_record',
        'verification_response',
        'verification_attempts',
        'last_verification_attempt_at',
        'active_from',
        'active_until',
        'notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'verification_failed_at' => 'datetime',
        'last_verification_attempt_at' => 'datetime',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->verification_token) {
                $model->verification_token = self::generateVerificationToken();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(DomainVerificationLog::class, 'domain_id');
    }

    /**
     * Check if domain is currently verified and active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'verified' || !$this->verified_at) {
            return false;
        }

        $now = now();

        if ($this->active_from && $now->isBefore($this->active_from)) {
            return false;
        }

        if ($this->active_until && $now->isAfter($this->active_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if domain is currently pending verification
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if domain verification failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Generate verification token
     */
    public static function generateVerificationToken(): string
    {
        return 'verify_' . bin2hex(random_bytes(16));
    }

    /**
     * Generate DNS verification record
     */
    public static function generateDnsRecord(string $token): string
    {
        return "v=arcav " . $token;
    }

    /**
     * Get verification record for display
     */
    public function getVerificationRecord(): string
    {
        if ($this->verification_method === 'dns') {
            return self::generateDnsRecord($this->verification_token);
        }

        return $this->verification_record ?? '';
    }
}
