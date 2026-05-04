<?php
namespace App\Models;

use App\Models\Concerns\AssignsUuid;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class EmployeeProfile extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::creating(function (EmployeeProfile $profile): void {
            if (! $profile->company_id) {
                $profile->company_id = $profile->resolveCompanyIdFromMembership();
            }

            $profile->syncUuidColumnsFromLegacyIds();
        });

        static::updating(function (EmployeeProfile $profile): void {
            if (! $profile->company_id) {
                $profile->company_id = $profile->resolveCompanyIdFromMembership();
            }

            $profile->syncUuidColumnsFromLegacyIds();
        });
    }

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'user_id',
        'user_uuid',
        'nik',
        'hire_date',
        'department_id',
        'designation_id',
        'manager_user_id',
        'manager_user_uuid',
        'employment_status',
        'team',
        'team_id',
        'designation',
        'phone',
        'address',
        'address_detail',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
        'place_of_birth',
        'date_of_birth',
        'gender',
        'marital_status',
        'religion',
        'nationality',
        'base_salary',
        'fixed_allowance',
        'contract_type',
        'contract_start_date',
        'contract_end_date',
        'bio',
        'bank_name',
        'bank_account_no',
        'bank_ifsc_code',
        'bank_branch',
        'emergency_contacts',
        'education_items',
        'experience_items',
        'profile_photo_path',
    ];

    protected $casts = [
        'uuid' => 'string',
        'company_id' => 'integer',
        'company_uuid' => 'string',
        'user_uuid' => 'string',
        'hire_date' => 'date',
        'date_of_birth' => 'date',
        'emergency_contacts' => 'array',
        'education_items' => 'array',
        'experience_items' => 'array',
        'base_salary' => 'decimal:2',
        'fixed_allowance' => 'decimal:2',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'manager_user_id' => 'integer',
        'manager_user_uuid' => 'string',
        'province_id' => 'integer',
        'regency_id' => 'integer',
        'district_id' => 'integer',
        'village_id' => 'integer',
    ];

    private function syncUuidColumnsFromLegacyIds(): void
    {
        if (Schema::hasColumn($this->getTable(), 'user_uuid') && ! $this->user_uuid && $this->user_id) {
            $this->user_uuid = (string) (User::query()->where('id', $this->user_id)->value('uuid') ?? '');
        }

        if (
            Schema::hasColumn($this->getTable(), 'company_uuid')
            && Schema::hasColumn((new Company)->getTable(), 'uuid')
            && ! $this->company_uuid
            && $this->company_id
        ) {
            $this->company_uuid = (string) (Company::query()->where('id', $this->company_id)->value('uuid') ?? '');
        }

        if (Schema::hasColumn($this->getTable(), 'manager_user_uuid') && ! $this->manager_user_uuid && $this->manager_user_id) {
            $this->manager_user_uuid = (string) (User::query()->where('id', $this->manager_user_id)->value('uuid') ?? '');
        }
    }

    private function resolveCompanyIdFromMembership(): ?int
    {
        if (! $this->user_id) {
            return $this->resolveDefaultCompanyId();
        }

        $companyId = CompanyUser::query()
            ->where('user_id', $this->user_id)
            ->where('status', 'active')
            ->orderBy('company_id')
            ->value('company_id');

        if ($companyId) {
            return (int) $companyId;
        }

        return $this->resolveDefaultCompanyId();
    }

    private function resolveDefaultCompanyId(): ?int
    {
        $companyId = Company::query()
            ->where('code', 'default_company')
            ->value('id');

        return $companyId ? (int) $companyId : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function designationRef(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(WilayahProvince::class, 'province_id');
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(WilayahRegency::class, 'regency_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(WilayahDistrict::class, 'district_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(WilayahVillage::class, 'village_id');
    }

    public function employmentHistories(): HasMany
    {
        return $this->hasMany(EmployeeEmploymentHistory::class, 'employee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class, 'employee_id');
    }

    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class, 'employee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class, 'employee_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class, 'employee_id');
    }

    public function taxProfiles(): HasMany
    {
        return $this->hasMany(EmployeeTaxProfile::class, 'employee_id');
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class, 'employee_id');
    }

    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'employee_id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class, 'employee_id');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class, 'employee_id');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class, 'employee_id');
    }

    public function currentEmploymentSnapshot(): ?EmployeeEmploymentHistory
    {
        return $this->employmentHistories()
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function currentAssignmentSnapshot(): ?EmployeeAssignment
    {
        return $this->assignments()
            ->with(['department:id,name', 'designation:id,name,department_id', 'team:id,name', 'manager:id,name,email'])
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('is_primary')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function currentCompensationSnapshot(): ?EmployeeCompensation
    {
        return $this->compensations()
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    public function currentContractSnapshot(): ?EmployeeContract
    {
        return $this->contracts()
            ->where(function ($query): void {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function primaryBankAccountSnapshot(): ?EmployeeBankAccount
    {
        return $this->bankAccounts()->orderByDesc('is_primary')->orderByDesc('id')->first();
    }

    protected function employmentStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentEmploymentSnapshot()?->employment_status ?? $value,
        );
    }

    protected function departmentId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentAssignmentSnapshot()?->department_id ?? $value,
        );
    }

    protected function designationId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentAssignmentSnapshot()?->designation_id ?? $value,
        );
    }

    protected function managerUserId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentAssignmentSnapshot()?->manager_user_id ?? $value,
        );
    }

    protected function team(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentAssignmentSnapshot()?->team?->name
                ?? $this->currentAssignmentSnapshot()?->team_name
                ?? $value
                ?? $this->currentAssignmentSnapshot()?->department?->name,
        );
    }

    protected function designation(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentAssignmentSnapshot()?->designation?->name
                ?? $this->designationRef?->name
                ?? $value,
        );
    }

    protected function baseSalary(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentCompensationSnapshot()?->base_salary ?? $value,
        );
    }

    protected function fixedAllowance(): Attribute
    {
        return Attribute::make(
            get: fn () => 0.0,
        );
    }

    protected function contractType(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentContractSnapshot()?->contract_type ?? $value,
        );
    }

    protected function contractStartDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentContractSnapshot()?->start_date
                ?? ($value ? Carbon::parse($value) : null),
        );
    }

    protected function contractEndDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->currentContractSnapshot()?->end_date
                ?? ($value ? Carbon::parse($value) : null),
        );
    }

    protected function bankName(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->primaryBankAccountSnapshot()?->bank_name ?? $value,
        );
    }

    protected function bankAccountNo(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->primaryBankAccountSnapshot()?->account_number ?? $value,
        );
    }

    protected function bankIfscCode(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->primaryBankAccountSnapshot()?->bank_ifsc_code ?? $value,
        );
    }

    protected function bankBranch(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->primaryBankAccountSnapshot()?->bank_branch ?? $value,
        );
    }
}

