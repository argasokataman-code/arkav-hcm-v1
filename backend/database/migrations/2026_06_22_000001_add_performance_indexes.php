<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── subscriptions: billing scheduler scans by (status, ends_at) ──
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasIndex('subscriptions', 'subscriptions_status_ends_at_index')) {
                $table->index(['status', 'ends_at'], 'subscriptions_status_ends_at_index');
            }
        });

        // ── invoices: global payment reminder scans by (is_paid, due_date) ──
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasIndex('invoices', 'invoices_paid_due_index')) {
                $table->index(['is_paid', 'due_date'], 'invoices_paid_due_index');
            }
        });

        // ── invoices: per-company termination/renewal queries by (company_id, is_paid, due_date) ──
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasIndex('invoices', 'invoices_company_paid_due_index')) {
                $table->index(['company_id', 'is_paid', 'due_date'], 'invoices_company_paid_due_index');
            }
        });

        // ── overtime_requests: payroll draft builder per-employee + date range + status ──
        Schema::table('overtime_requests', function (Blueprint $table): void {
            if (! Schema::hasIndex('overtime_requests', 'overtime_requests_company_user_date_status_index')) {
                $table->index(
                    ['company_id', 'user_id', 'work_date', 'status'],
                    'overtime_requests_company_user_date_status_index',
                );
            }
        });

        // ── hcm_payroll_periods: refresh service scans by (status) then orders by company/period ──
        Schema::table('hcm_payroll_periods', function (Blueprint $table): void {
            if (! Schema::hasIndex('hcm_payroll_periods', 'hcm_payroll_periods_status_company_period_idx')) {
                $table->index(
                    ['status', 'company_id', 'period_year', 'period_month'],
                    'hcm_payroll_periods_status_company_period_idx',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasIndex('subscriptions', 'subscriptions_status_ends_at_index')) {
                $table->dropIndex('subscriptions_status_ends_at_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasIndex('invoices', 'invoices_paid_due_index')) {
                $table->dropIndex('invoices_paid_due_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasIndex('invoices', 'invoices_company_paid_due_index')) {
                $table->dropIndex('invoices_company_paid_due_index');
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table): void {
            if (Schema::hasIndex('overtime_requests', 'overtime_requests_company_user_date_status_index')) {
                $table->dropIndex('overtime_requests_company_user_date_status_index');
            }
        });

        Schema::table('hcm_payroll_periods', function (Blueprint $table): void {
            if (Schema::hasIndex('hcm_payroll_periods', 'hcm_payroll_periods_status_company_period_idx')) {
                $table->dropIndex('hcm_payroll_periods_status_company_period_idx');
            }
        });
    }
};
