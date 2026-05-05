<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enlarge VARCHAR columns to TEXT to accommodate Laravel encrypted cast ciphertext.
     * Encrypted data is longer than plaintext due to encryption overhead.
     *
     * Fields encrypted:
     *  - EmployeeProfile: nik, bank_account_no, bank_ifsc_code, bank_branch
     *  - EmployeeTaxProfile: npwp
     *  - EmployeeBenefit: bpjs_kesehatan_no, bpjs_ketenagakerjaan_no
     *  - EmployeeBankAccount: account_number, bank_ifsc_code, bank_branch (if encrypted via EmployeeProfile.bank_* fields)
     */
    public function up(): void
    {
        // EmployeeProfile — enlarge sensitive fields
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->text('nik')->nullable()->change();
            $table->text('bank_account_no')->nullable()->change();
            $table->text('bank_ifsc_code')->nullable()->change();
            $table->text('bank_branch')->nullable()->change();
        });

        // EmployeeTaxProfile — enlarge NPWP
        Schema::table('employee_tax_profiles', function (Blueprint $table) {
            $table->text('npwp')->nullable()->change();
        });

        // EmployeeBenefit — enlarge BPJS numbers
        Schema::table('employee_benefits', function (Blueprint $table) {
            $table->text('bpjs_kesehatan_no')->nullable()->change();
            $table->text('bpjs_ketenagakerjaan_no')->nullable()->change();
        });

        // EmployeeBankAccount — enlarge sensitive fields (if table exists)
        if (Schema::hasTable('employee_bank_accounts')) {
            Schema::table('employee_bank_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('employee_bank_accounts', 'account_number')) {
                    $table->text('account_number')->nullable()->change();
                }
                if (Schema::hasColumn('employee_bank_accounts', 'bank_ifsc_code')) {
                    $table->text('bank_ifsc_code')->nullable()->change();
                }
                if (Schema::hasColumn('employee_bank_accounts', 'bank_branch')) {
                    $table->text('bank_branch')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Revert to VARCHAR (original size, may lose data if encrypted)
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('nik', 255)->nullable()->change();
            $table->string('bank_account_no', 255)->nullable()->change();
            $table->string('bank_ifsc_code', 255)->nullable()->change();
            $table->string('bank_branch', 255)->nullable()->change();
        });

        Schema::table('employee_tax_profiles', function (Blueprint $table) {
            $table->string('npwp', 255)->nullable()->change();
        });

        Schema::table('employee_benefits', function (Blueprint $table) {
            $table->string('bpjs_kesehatan_no', 255)->nullable()->change();
            $table->string('bpjs_ketenagakerjaan_no', 255)->nullable()->change();
        });

        // Revert employee_bank_accounts if it exists
        if (Schema::hasTable('employee_bank_accounts')) {
            Schema::table('employee_bank_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('employee_bank_accounts', 'account_number')) {
                    $table->string('account_number', 255)->nullable()->change();
                }
                if (Schema::hasColumn('employee_bank_accounts', 'bank_ifsc_code')) {
                    $table->string('bank_ifsc_code', 255)->nullable()->change();
                }
                if (Schema::hasColumn('employee_bank_accounts', 'bank_branch')) {
                    $table->string('bank_branch', 255)->nullable()->change();
                }
            });
        }
    }
};
