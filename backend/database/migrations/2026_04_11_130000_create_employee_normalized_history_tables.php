<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_employment_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('employment_status', 50)->default('active');
            $table->string('employee_type', 50)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'start_date']);
        });

        Schema::create('employee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('team_name', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'start_date']);
        });

        Schema::create('employee_compensations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('salary_type', 50)->default('monthly');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('fixed_allowance', 15, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('employee_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('contract_type', 50)->default('permanent');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'start_date']);
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('account_holder_name', 150)->nullable();
            $table->string('bank_ifsc_code', 100)->nullable();
            $table->string('bank_branch', 150)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->index(['employee_id', 'is_primary']);
        });

        Schema::create('employee_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('npwp', 100)->nullable();
            $table->string('tax_status', 50)->nullable();
            $table->string('ptkp_status', 50)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('employee_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('bpjs_kesehatan_no', 100)->nullable();
            $table->string('bpjs_ketenagakerjaan_no', 100)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('employee_emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_educations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('institution', 150)->nullable();
            $table->string('degree', 100)->nullable();
            $table->string('field_of_study', 150)->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_experiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('company', 150)->nullable();
            $table->string('position', 150)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->backfillFromLegacyProfiles();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_experiences');
        Schema::dropIfExists('employee_educations');
        Schema::dropIfExists('employee_emergency_contacts');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('employee_tax_profiles');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_compensations');
        Schema::dropIfExists('employee_assignments');
        Schema::dropIfExists('employee_employment_history');
    }

    private function backfillFromLegacyProfiles(): void
    {
        $profiles = DB::table('employee_profiles')->orderBy('id')->get();
        $now = now();

        foreach ($profiles as $profile) {
            $effectiveDate = $profile->hire_date
                ?: $profile->contract_start_date
                ?: optional($now)->toDateString();

            DB::table('employee_employment_history')->insert([
                'employee_id' => $profile->id,
                'employment_status' => $profile->employment_status ?: 'active',
                'employee_type' => null,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'notes' => 'Backfilled from employee_profiles',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($profile->department_id !== null || $profile->designation_id !== null || $profile->manager_user_id !== null || $profile->team !== null) {
                DB::table('employee_assignments')->insert([
                    'employee_id' => $profile->id,
                    'department_id' => $profile->department_id,
                    'designation_id' => $profile->designation_id,
                    'manager_user_id' => $profile->manager_user_id,
                    'is_primary' => true,
                    'start_date' => $effectiveDate,
                    'end_date' => null,
                    'team_name' => $profile->team,
                    'notes' => 'Backfilled from employee_profiles',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ((float) ($profile->base_salary ?? 0) > 0 || (float) ($profile->fixed_allowance ?? 0) > 0) {
                DB::table('employee_compensations')->insert([
                    'employee_id' => $profile->id,
                    'salary_type' => 'monthly',
                    'base_salary' => round((float) ($profile->base_salary ?? 0), 2),
                    'fixed_allowance' => round((float) ($profile->fixed_allowance ?? 0), 2),
                    'currency' => 'IDR',
                    'effective_date' => $effectiveDate,
                    'end_date' => null,
                    'notes' => 'Backfilled from employee_profiles',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($profile->contract_type !== null || $profile->contract_start_date !== null || $profile->contract_end_date !== null) {
                DB::table('employee_contracts')->insert([
                    'employee_id' => $profile->id,
                    'contract_type' => $profile->contract_type ?: 'permanent',
                    'start_date' => $profile->contract_start_date ?: $effectiveDate,
                    'end_date' => $profile->contract_end_date,
                    'status' => $profile->contract_end_date ? 'scheduled_end' : 'active',
                    'notes' => 'Backfilled from employee_profiles',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($profile->bank_name !== null || $profile->bank_account_no !== null || $profile->bank_ifsc_code !== null || $profile->bank_branch !== null) {
                DB::table('employee_bank_accounts')->insert([
                    'employee_id' => $profile->id,
                    'bank_name' => $profile->bank_name,
                    'account_number' => $profile->bank_account_no,
                    'account_holder_name' => null,
                    'bank_ifsc_code' => $profile->bank_ifsc_code,
                    'bank_branch' => $profile->bank_branch,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ((array) json_decode((string) ($profile->emergency_contacts ?: '[]'), true) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_emergency_contacts')->insert([
                    'employee_id' => $profile->id,
                    'name' => (string) ($item['name'] ?? 'Contact '.($index + 1)),
                    'relationship' => $item['relationship'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'email' => $item['email'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ((array) json_decode((string) ($profile->education_items ?: '[]'), true) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_educations')->insert([
                    'employee_id' => $profile->id,
                    'institution' => $item['institution'] ?? null,
                    'degree' => $item['degree'] ?? null,
                    'field_of_study' => $item['fieldOfStudy'] ?? ($item['field_of_study'] ?? null),
                    'start_year' => isset($item['startYear']) ? (int) $item['startYear'] : null,
                    'end_year' => isset($item['endYear']) ? (int) $item['endYear'] : null,
                    'notes' => $item['notes'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ((array) json_decode((string) ($profile->experience_items ?: '[]'), true) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_experiences')->insert([
                    'employee_id' => $profile->id,
                    'company' => $item['company'] ?? null,
                    'position' => $item['position'] ?? null,
                    'start_date' => $item['startDate'] ?? ($item['start_date'] ?? null),
                    'end_date' => $item['endDate'] ?? ($item['end_date'] ?? null),
                    'description' => $item['description'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
