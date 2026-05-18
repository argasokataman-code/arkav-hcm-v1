<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenPayrollSettingsAuditLog();
        $this->hardenSubscriptionEvents();
    }

    public function down(): void
    {
        $this->dropForeignIfExists('payroll_settings_audit_log', 'payroll_settings_audit_log_company_id_fk');
        $this->dropForeignIfExists('payroll_settings_audit_log', 'payroll_settings_audit_log_user_id_fk');

        $this->dropForeignIfExists('subscription_events', 'subscription_events_company_id_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_company_uuid_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_subscription_id_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_subscription_uuid_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_invoice_id_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_invoice_uuid_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_payment_id_fk');
        $this->dropForeignIfExists('subscription_events', 'subscription_events_payment_uuid_fk');
    }

    private function hardenPayrollSettingsAuditLog(): void
    {
        if (! Schema::hasTable('payroll_settings_audit_log')) {
            return;
        }

        if (Schema::hasTable('companies') && Schema::hasColumn('payroll_settings_audit_log', 'company_id')) {
            DB::table('payroll_settings_audit_log')
                ->whereNotIn('company_id', DB::table('companies')->select('id'))
                ->delete();

            $this->safeForeign(
                'payroll_settings_audit_log',
                'company_id',
                'companies',
                'id',
                'payroll_settings_audit_log_company_id_fk',
                'cascade'
            );
        }

        if (Schema::hasTable('users') && Schema::hasColumn('payroll_settings_audit_log', 'user_id')) {
            DB::table('payroll_settings_audit_log')
                ->whereNotNull('user_id')
                ->whereNotIn('user_id', DB::table('users')->select('id'))
                ->update(['user_id' => null]);

            $this->safeForeign(
                'payroll_settings_audit_log',
                'user_id',
                'users',
                'id',
                'payroll_settings_audit_log_user_id_fk',
                'null'
            );
        }
    }

    private function hardenSubscriptionEvents(): void
    {
        if (! Schema::hasTable('subscription_events')) {
            return;
        }

        $this->nullMissingReference('subscription_events', 'company_id', 'companies', 'id');
        $this->nullMissingReference('subscription_events', 'company_uuid', 'companies', 'uuid');
        $this->nullMissingReference('subscription_events', 'subscription_id', 'subscriptions', 'id');
        $this->nullMissingReference('subscription_events', 'subscription_uuid', 'subscriptions', 'uuid');
        $this->nullMissingReference('subscription_events', 'invoice_id', 'invoices', 'id');
        $this->nullMissingReference('subscription_events', 'invoice_uuid', 'invoices', 'uuid');
        $this->nullMissingReference('subscription_events', 'payment_id', 'payments', 'id');
        $this->nullMissingReference('subscription_events', 'payment_uuid', 'payments', 'uuid');

        $this->safeForeign('subscription_events', 'company_id', 'companies', 'id', 'subscription_events_company_id_fk', 'null');
        $this->safeForeign('subscription_events', 'company_uuid', 'companies', 'uuid', 'subscription_events_company_uuid_fk', 'null');
        $this->safeForeign('subscription_events', 'subscription_id', 'subscriptions', 'id', 'subscription_events_subscription_id_fk', 'null');
        $this->safeForeign('subscription_events', 'subscription_uuid', 'subscriptions', 'uuid', 'subscription_events_subscription_uuid_fk', 'null');
        $this->safeForeign('subscription_events', 'invoice_id', 'invoices', 'id', 'subscription_events_invoice_id_fk', 'null');
        $this->safeForeign('subscription_events', 'invoice_uuid', 'invoices', 'uuid', 'subscription_events_invoice_uuid_fk', 'null');
        $this->safeForeign('subscription_events', 'payment_id', 'payments', 'id', 'subscription_events_payment_id_fk', 'null');
        $this->safeForeign('subscription_events', 'payment_uuid', 'payments', 'uuid', 'subscription_events_payment_uuid_fk', 'null');
    }

    private function nullMissingReference(string $table, string $column, string $parentTable, string $parentColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $column) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, DB::table($parentTable)->select($parentColumn))
            ->update([$column => null]);
    }

    private function safeForeign(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $name,
        string $onDelete
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $column) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                    return;
                }

                if ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                }
            });
        } catch (\Throwable $_e) {
            // Constraint may already exist or the current connection may not support altering it safely.
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($constraint): void {
                $blueprint->dropForeign($constraint);
            });
        } catch (\Throwable $_e) {
            // Ignore if the constraint was never created or has already been dropped.
        }
    }
};