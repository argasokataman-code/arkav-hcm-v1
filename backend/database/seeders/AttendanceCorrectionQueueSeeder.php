<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed realistic pending correction data for testing the correction queue.
 * Creates attendance records for every employee in the target company for
 * the past 30 days, each with status=needs_review and correction_status=requested.
 *
 * Usage:
 *   php artisan db:seed --class=AttendanceCorrectionQueueSeeder
 *
 * To target a specific company:
 *   SEED_COMPANY_CODE=sembrani-local_9d3f php artisan db:seed --class=AttendanceCorrectionQueueSeeder
 */
class AttendanceCorrectionQueueSeeder extends Seeder
{
    private array $reasons = [
        'Lupa absen keluar, sudah pulang tapi lupa tap out.',
        'Sistem error saat check-out, mohon dikoreksi.',
        'Kepencet tombol check-in terlalu awal.',
        'Internet mati saat mau check-out, jadi tidak terekam.',
        'Masuk lebih awal tapi sistem tidak merekam dengan benar.',
        'Check-out dari cabang lain, lokasi berbeda dari biasanya.',
        'Ada meeting mendadak, pulang terlambat tapi tidak sempat check-out.',
        'Handphone mati, tidak bisa tap-out tepat waktu.',
        'Terlambat masuk karena hujan deras di perjalanan.',
        'Pulang lebih awal karena sakit, sudah izin ke atasan langsung.',
    ];

    public function run(): void
    {
        $companyCode = env('SEED_COMPANY_CODE', 'sembrani-local_9d3f');

        $company = Company::where('code', $companyCode)->first();

        if (! $company) {
            $this->command->error("Company '{$companyCode}' not found.");
            return;
        }

        $tz = $company->timezone ?: 'Asia/Jakarta';
        $today = Carbon::now($tz)->startOfDay();

        $users = User::whereHas('companyMemberships', fn ($q) => $q->where('company_id', $company->id))
            ->get(['id', 'uuid', 'name']);

        if ($users->isEmpty()) {
            $this->command->error("No users found in company '{$companyCode}'.");
            return;
        }

        $this->command->info("Seeding correction queue for company: {$company->name} (id={$company->id})");
        $this->command->info("Users: " . $users->pluck('name')->join(', '));

        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            for ($daysBack = 1; $daysBack <= 30; $daysBack++) {
                $workDay = $today->copy()->subDays($daysBack);

                // Skip Sundays (keep Saturdays for realism)
                if ($workDay->isSunday()) {
                    continue;
                }

                $workDate = $workDay->toDateString();

                // Check-in at 09:00 local, check-out at 12:10 local → 190 min net → needs_review
                $checkIn  = Carbon::createFromFormat('Y-m-d H:i:s', $workDate . ' 09:00:00', $tz)->utc();
                $checkOut = Carbon::createFromFormat('Y-m-d H:i:s', $workDate . ' 12:10:00', $tz)->utc();

                // Random correction request time: same day afternoon (13:00–18:00 local)
                $requestHour = rand(13, 17);
                $requestMin  = rand(0, 59);
                $requestedAt = Carbon::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:00', $workDate, $requestHour, $requestMin), $tz)->utc();

                $reason = $this->reasons[($daysBack - 1) % count($this->reasons)];

                $existing = AttendanceRecord::withTrashed()
                    ->where('company_id', $company->id)
                    ->where('user_id', $user->id)
                    ->where('work_date', $workDate)
                    ->first();

                if ($existing) {
                    // Update to pending correction state (don't overwrite if already dismissed/approved)
                    if (in_array((string) ($existing->correction_status ?? 'none'), ['approved', 'dismissed'], true)) {
                        $skipped++;
                        continue;
                    }
                    $existing->restore(); // in case soft-deleted
                    $existing->update([
                        'status'                  => 'needs_review',
                        'correction_status'       => 'requested',
                        'correction_reason'       => $reason,
                        'correction_requested_at' => $requestedAt,
                        'corrected_by_user_id'    => null,
                        'corrected_at'            => null,
                        'check_in_at'             => $checkIn,
                        'check_out_at'            => $checkOut,
                        'break_minutes'           => 0,
                        'late_minutes'            => 0,
                    ]);
                    $created++;
                } else {
                    AttendanceRecord::create([
                        'uuid'                    => (string) Str::uuid(),
                        'user_id'                 => $user->id,
                        'user_uuid'               => (string) ($user->uuid ?? Str::uuid()),
                        'company_id'              => $company->id,
                        'company_uuid'            => (string) ($company->uuid ?? ''),
                        'work_date'               => $workDate,
                        'status'                  => 'needs_review',
                        'check_in_at'             => $checkIn,
                        'check_out_at'            => $checkOut,
                        'break_minutes'           => 0,
                        'late_minutes'            => 0,
                        'correction_status'       => 'requested',
                        'correction_reason'       => $reason,
                        'correction_requested_at' => $requestedAt,
                        'corrected_by_user_id'    => null,
                        'corrected_at'            => null,
                    ]);
                    $created++;
                }
            }
        }

        $this->command->info("Done. Created/updated: {$created}, skipped (already reviewed): {$skipped}");
    }
}
