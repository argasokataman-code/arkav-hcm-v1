<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use Illuminate\Database\Seeder;

/**
 * Isi rekening bank demo untuk profil yang masih kosong (THR disburse / QA).
 * Hanya mengubah baris tanpa nomor rekening; tidak menimpa yang sudah diisi.
 */
class EmployeeProfileBankBackfillSeeder extends Seeder
{
    public function run(): void
    {
        EmployeeProfile::query()
            ->where(function ($q): void {
                $q->whereNull('bank_account_no')->orWhere('bank_account_no', '');
            })
            ->orderBy('id')
            ->each(function (EmployeeProfile $profile): void {
                $profile->update([
                    'bank_name' => $profile->bank_name ?: 'Bank Central Asia',
                    'bank_account_no' => sprintf('5271%08d', (int) $profile->user_id),
                    'bank_ifsc_code' => $profile->bank_ifsc_code ?: 'BCA001',
                    'bank_branch' => $profile->bank_branch ?: 'Jakarta',
                ]);
            });
    }
}
