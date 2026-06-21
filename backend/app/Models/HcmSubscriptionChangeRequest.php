<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_uuid
 * @property string $user_uuid
 * @property ?string $current_subscription_uuid
 * @property ?string $from_package_uuid
 * @property ?string $to_package_uuid
 * @property string $action
 * @property string $status
 * @property ?array $preview
 * @property ?string $notes
 * @property ?Carbon $effective_at
 * @property ?Carbon $decided_at
 * @property ?string $decided_by_user_uuid
 * @property ?Carbon $applied_at
 */
class HcmSubscriptionChangeRequest extends Model
{
    use AssignsUuid;

    public const ACTION_UPGRADE = 'upgrade';

    public const ACTION_DOWNGRADE = 'downgrade';

    public const ACTION_CANCEL = 'cancel';

    public const VALID_ACTIONS = [self::ACTION_UPGRADE, self::ACTION_DOWNGRADE, self::ACTION_CANCEL];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_APPLIED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'hcm_subscription_change_requests';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_uuid',
        'user_uuid',
        'current_subscription_uuid',
        'from_package_uuid',
        'to_package_uuid',
        'action',
        'status',
        'preview',
        'notes',
        'effective_at',
        'decided_at',
        'decided_by_user_uuid',
        'applied_at',
    ];

    protected $casts = [
        'preview' => 'array',
        'effective_at' => 'datetime',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function fromPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'from_package_uuid', 'uuid');
    }

    public function toPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'to_package_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
