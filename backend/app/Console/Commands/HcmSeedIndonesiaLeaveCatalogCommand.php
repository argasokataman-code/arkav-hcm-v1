<?php

namespace App\Console\Commands;

use App\Models\HcmLeaveTypeSetting;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HcmSeedIndonesiaLeaveCatalogCommand extends Command
{
    protected $signature = 'hcm:leave-seed-indonesia
        {--company-id= : Optional company id}
        {--sync-legacy : Also sync leave types into hcm_leave_type_settings for existing menu UI}';

    protected $description = 'Seed Indonesian leave catalog and policies (with deducted vs non-deducted balance rules).';

    public function handle(): int
    {
        if (! Schema::hasTable('leave_types') || ! Schema::hasTable('leave_policies')) {
            $this->warn('Foundation leave tables are not available. Run migrations first.');

            return self::SUCCESS;
        }

        $companyId = $this->option('company-id');
        $companyId = ($companyId === null || $companyId === '') ? null : (int) $companyId;
        $syncLegacy = (bool) $this->option('sync-legacy');

        $catalog = [
            [
                'code' => 'annual_leave',
                'name' => 'Cuti Tahunan',
                'is_paid' => true,
                'requires_attachment' => false,
                'deduct_from_balance' => true,
                'days_per_year' => 12,
                'min_service_months' => 12,
                'is_prorated' => true,
                'carry_forward' => true,
                'max_carry_days' => 6,
                'is_earned_leave' => true,
                'legacy' => ['days' => 12, 'carry_forward' => true, 'max_carry_days' => 6, 'earned_leave' => true, 'sort_order' => 1],
            ],
            [
                'code' => 'joint_leave',
                'name' => 'Cuti Bersama',
                'is_paid' => true,
                'requires_attachment' => false,
                'deduct_from_balance' => true,
                'days_per_year' => 0,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 0, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 2],
            ],
            [
                'code' => 'sick_leave',
                'name' => 'Cuti Sakit',
                'is_paid' => true,
                'requires_attachment' => true,
                'deduct_from_balance' => false,
                'days_per_year' => 0,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 0, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 3],
            ],
            [
                'code' => 'maternity_leave',
                'name' => 'Cuti Melahirkan',
                'is_paid' => true,
                'requires_attachment' => true,
                'deduct_from_balance' => false,
                'days_per_year' => 90,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 90, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 4],
            ],
            [
                'code' => 'paternity_leave',
                'name' => 'Cuti Ayah',
                'is_paid' => true,
                'requires_attachment' => false,
                'deduct_from_balance' => false,
                'days_per_year' => 2,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 2, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 5],
            ],
            [
                'code' => 'menstrual_leave',
                'name' => 'Cuti Haid',
                'is_paid' => true,
                'requires_attachment' => false,
                'deduct_from_balance' => false,
                'days_per_year' => 24,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 24, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 6],
            ],
            [
                'code' => 'marriage_leave',
                'name' => 'Cuti Menikah',
                'is_paid' => true,
                'requires_attachment' => true,
                'deduct_from_balance' => false,
                'days_per_year' => 3,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 3, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 7],
            ],
            [
                'code' => 'bereavement_leave',
                'name' => 'Cuti Duka',
                'is_paid' => true,
                'requires_attachment' => true,
                'deduct_from_balance' => false,
                'days_per_year' => 2,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 2, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 8],
            ],
            [
                'code' => 'unpaid_leave',
                'name' => 'Cuti Tidak Dibayar (LOP)',
                'is_paid' => false,
                'requires_attachment' => false,
                'deduct_from_balance' => false,
                'days_per_year' => 0,
                'min_service_months' => 0,
                'is_prorated' => false,
                'carry_forward' => false,
                'max_carry_days' => null,
                'is_earned_leave' => false,
                'legacy' => ['days' => 0, 'carry_forward' => false, 'max_carry_days' => null, 'earned_leave' => false, 'sort_order' => 9],
            ],
        ];

        DB::transaction(function () use ($catalog, $companyId, $syncLegacy): void {
            foreach ($catalog as $row) {
                $type = LeaveType::query()->updateOrCreate(
                    ['code' => $row['code']],
                    [
                        'company_id' => $companyId,
                        'name' => $row['name'],
                        'is_paid' => $row['is_paid'],
                        'requires_approval' => true,
                        'requires_attachment' => $row['requires_attachment'],
                        'deduct_from_balance' => $row['deduct_from_balance'],
                        'is_active' => true,
                    ]
                );

                LeavePolicy::query()->updateOrCreate(
                    [
                        'leave_type_id' => $type->id,
                        'name' => 'Indonesia Default: '.$row['name'],
                    ],
                    [
                        'company_id' => $companyId,
                        'days_per_year' => $row['days_per_year'],
                        'min_service_months' => $row['min_service_months'],
                        'is_prorated' => $row['is_prorated'],
                        'carry_forward' => $row['carry_forward'],
                        'max_carry_days' => $row['max_carry_days'],
                        'expire_after_days' => null,
                        'is_earned_leave' => $row['is_earned_leave'],
                        'allow_negative_balance' => false,
                        'effective_from' => now()->startOfYear()->toDateString(),
                        'effective_to' => null,
                    ]
                );

                if ($syncLegacy && Schema::hasTable('hcm_leave_type_settings')) {
                    $legacy = $row['legacy'];
                    $legacyCode = $this->legacyCode($row['code']);
                    HcmLeaveTypeSetting::query()->updateOrCreate(
                        ['code' => $legacyCode],
                        [
                            'name' => $row['name'],
                            'is_enabled' => true,
                            'days' => $legacy['days'],
                            'carry_forward' => $legacy['carry_forward'],
                            'max_carry_days' => $legacy['max_carry_days'],
                            'earned_leave' => $legacy['earned_leave'],
                            'sort_order' => $legacy['sort_order'],
                        ]
                    );
                }
            }
        });

        $deducted = collect($catalog)->where('deduct_from_balance', true)->pluck('name')->values()->all();
        $notDeducted = collect($catalog)->where('deduct_from_balance', false)->pluck('name')->values()->all();

        $this->info('Seed katalog cuti Indonesia selesai.');
        $this->line('Dipotong saldo: '.implode(', ', $deducted));
        $this->line('Tidak dipotong saldo: '.implode(', ', $notDeducted));

        return self::SUCCESS;
    }

    private function legacyCode(string $foundationCode): string
    {
        return match ($foundationCode) {
            'maternity_leave' => 'maternity',
            'paternity_leave' => 'paternity',
            'unpaid_leave' => 'lop',
            default => $foundationCode,
        };
    }
}
