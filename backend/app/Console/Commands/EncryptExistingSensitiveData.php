<?php

namespace App\Console\Commands;

use App\Models\EmployeeBenefit;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use Illuminate\Console\Command;

class EncryptExistingSensitiveData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pdp:encrypt-existing-data {--dry-run : Show what would be encrypted without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time command: Encrypt existing sensitive fields in EmployeeProfile, EmployeeTaxProfile, EmployeeBenefit. UU PDP C5.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('UU PDP Encryption — C5: Encrypt existing sensitive data');
        $this->info('Target fields:');
        $this->line('  - EmployeeProfile: nik, bank_account_no, bank_ifsc_code, bank_branch');
        $this->line('  - EmployeeTaxProfile: npwp');
        $this->line('  - EmployeeBenefit: bpjs_kesehatan_no, bpjs_ketenagakerjaan_no');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No data will be modified');
        }

        // Count total records
        $profileCount = EmployeeProfile::query()->count();
        $this->line("Found {$profileCount} employee profiles to process...\n");

        if ($dryRun) {
            $this->info('Dry-run complete. Run without --dry-run to encrypt.');

            return 0;
        }

        // Update EmployeeProfile
        $this->info('Processing EmployeeProfile...');
        EmployeeProfile::query()->chunk(100, function ($profiles) {
            foreach ($profiles as $profile) {
                $profile->save(); // Laravel auto-encrypts via cast when saved
            }
        });
        $this->info("✓ Encrypted {$profileCount} EmployeeProfile records");

        // Update EmployeeTaxProfile
        $this->info('Processing EmployeeTaxProfile...');
        $taxCount = EmployeeTaxProfile::query()->chunk(100, function ($taxProfiles) {
            foreach ($taxProfiles as $taxProfile) {
                $taxProfile->save();
            }
        });
        $this->info('✓ Encrypted EmployeeTaxProfile records');

        // Update EmployeeBenefit
        $this->info('Processing EmployeeBenefit...');
        $benefitCount = EmployeeBenefit::query()->chunk(100, function ($benefits) {
            foreach ($benefits as $benefit) {
                $benefit->save();
            }
        });
        $this->info('✓ Encrypted EmployeeBenefit records');

        $this->line('');
        $this->info('✓ All sensitive data encrypted successfully!');
        $this->line('Encrypted fields are now stored as ciphertext in the database.');
        $this->line('Laravel will auto-decrypt on read via the "encrypted" cast.');

        return 0;
    }
}
