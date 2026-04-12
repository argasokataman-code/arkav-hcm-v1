<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('deduct_from_balance')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('days_per_year', 8, 2)->default(0);
            $table->unsignedSmallInteger('min_service_months')->default(0);
            $table->boolean('is_prorated')->default(false);
            $table->boolean('carry_forward')->default(false);
            $table->unsignedSmallInteger('max_carry_days')->nullable();
            $table->unsignedSmallInteger('expire_after_days')->nullable();
            $table->boolean('is_earned_leave')->default(false);
            $table->boolean('allow_negative_balance')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['leave_type_id', 'effective_from', 'effective_to'], 'leave_policies_effective_idx');
        });

        Schema::create('leave_policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('policy_id')->constrained('leave_policies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'policy_id', 'effective_date'], 'leave_policy_assignments_emp_policy_idx');
        });

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('used', 10, 2)->default(0);
            $table->decimal('expired', 10, 2)->default(0);
            $table->decimal('carried_forward', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'employee_leave_balances_unique');
        });

        Schema::create('leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('leave_policies')->nullOnDelete();
            $table->string('transaction_type', 40);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'occurred_on'], 'leave_ledger_emp_type_date_idx');
            $table->index(['reference_type', 'reference_id'], 'leave_ledger_ref_idx');
        });

        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('status', 20)->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['leave_request_id', 'level'], 'leave_approvals_request_level_idx');
        });

        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->date('date');
            $table->string('name', 200);
            $table->boolean('is_national')->default(false);
            $table->boolean('is_joint_leave')->default(false);
            $table->boolean('deduct_from_leave')->default(false);
            $table->string('source', 20)->default('manual');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'date', 'name'], 'holiday_calendars_unique_per_company');
        });

        $now = now();
        $seedLeaveTypes = [
            ['code' => 'annual_leave', 'name' => 'Annual Leave', 'is_paid' => true, 'requires_approval' => true, 'requires_attachment' => false, 'deduct_from_balance' => true],
            ['code' => 'sick_leave', 'name' => 'Sick Leave', 'is_paid' => true, 'requires_approval' => true, 'requires_attachment' => true, 'deduct_from_balance' => false],
            ['code' => 'maternity_leave', 'name' => 'Maternity Leave', 'is_paid' => true, 'requires_approval' => true, 'requires_attachment' => true, 'deduct_from_balance' => false],
            ['code' => 'paternity_leave', 'name' => 'Paternity Leave', 'is_paid' => true, 'requires_approval' => true, 'requires_attachment' => false, 'deduct_from_balance' => false],
            ['code' => 'unpaid_leave', 'name' => 'Unpaid Leave', 'is_paid' => false, 'requires_approval' => true, 'requires_attachment' => false, 'deduct_from_balance' => false],
        ];

        foreach ($seedLeaveTypes as $row) {
            DB::table('leave_types')->insert(array_merge($row, [
                'company_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $annualTypeId = DB::table('leave_types')->where('code', 'annual_leave')->value('id');
        if ($annualTypeId !== null) {
            DB::table('leave_policies')->insert([
                'company_id' => null,
                'leave_type_id' => $annualTypeId,
                'name' => 'Default Annual Leave Policy',
                'days_per_year' => 12,
                'min_service_months' => 12,
                'is_prorated' => true,
                'carry_forward' => false,
                'max_carry_days' => null,
                'expire_after_days' => null,
                'is_earned_leave' => true,
                'allow_negative_balance' => false,
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('holidays')) {
            $rows = DB::table('holidays')
                ->select(['holiday_date', 'title', 'source', 'last_synced_at', 'is_active'])
                ->where('is_active', true)
                ->get();

            foreach ($rows as $row) {
                DB::table('holiday_calendars')->insert([
                    'company_id' => null,
                    'date' => (string) $row->holiday_date,
                    'name' => (string) $row->title,
                    'is_national' => true,
                    'is_joint_leave' => false,
                    'deduct_from_leave' => false,
                    'source' => (string) ($row->source ?: 'manual'),
                    'last_synced_at' => $row->last_synced_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_ledger');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leave_policy_assignments');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
