<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns();
        $this->backfillRowUuids();
        $this->backfillForeignUuids();
        $this->addIndexesAndConstraints();
    }

    public function down(): void
    {
        // Forward-only hardening migration.
    }

    private function addColumns(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn('subscriptions', 'company_uuid')) {
                    if (Schema::hasColumn('subscriptions', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('subscriptions', 'package_uuid')) {
                    if (Schema::hasColumn('subscriptions', 'package_id')) {
                        $table->uuid('package_uuid')->nullable()->after('package_id');
                    } else {
                        $table->uuid('package_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('purchase_transactions')) {
            Schema::table('purchase_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_transactions', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn('purchase_transactions', 'company_uuid')) {
                    if (Schema::hasColumn('purchase_transactions', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('purchase_transactions', 'subscription_uuid')) {
                    if (Schema::hasColumn('purchase_transactions', 'subscription_id')) {
                        $table->uuid('subscription_uuid')->nullable()->after('subscription_id');
                    } else {
                        $table->uuid('subscription_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('invoices', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn('invoices', 'company_uuid')) {
                    if (Schema::hasColumn('invoices', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('invoices', 'purchase_transaction_uuid')) {
                    if (Schema::hasColumn('invoices', 'purchase_transaction_id')) {
                        $table->uuid('purchase_transaction_uuid')->nullable()->after('purchase_transaction_id');
                    } else {
                        $table->uuid('purchase_transaction_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('invoices', 'subscription_uuid')) {
                    if (Schema::hasColumn('invoices', 'subscription_id')) {
                        $table->uuid('subscription_uuid')->nullable()->after('subscription_id');
                    } else {
                        $table->uuid('subscription_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('payments', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn('payments', 'company_uuid')) {
                    if (Schema::hasColumn('payments', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('payments', 'subscription_uuid')) {
                    if (Schema::hasColumn('payments', 'subscription_id')) {
                        $table->uuid('subscription_uuid')->nullable()->after('subscription_id');
                    } else {
                        $table->uuid('subscription_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('payments', 'purchase_transaction_uuid')) {
                    if (Schema::hasColumn('payments', 'purchase_transaction_id')) {
                        $table->uuid('purchase_transaction_uuid')->nullable()->after('purchase_transaction_id');
                    } else {
                        $table->uuid('purchase_transaction_uuid')->nullable();
                    }
                }

                if (! Schema::hasColumn('payments', 'invoice_uuid')) {
                    if (Schema::hasColumn('payments', 'invoice_id')) {
                        $table->uuid('invoice_uuid')->nullable()->after('invoice_id');
                    } else {
                        $table->uuid('invoice_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('invoice_email_logs')) {
            Schema::table('invoice_email_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('invoice_email_logs', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn('invoice_email_logs', 'invoice_uuid')) {
                    if (Schema::hasColumn('invoice_email_logs', 'invoice_id')) {
                        $table->uuid('invoice_uuid')->nullable()->after('invoice_id');
                    } else {
                        $table->uuid('invoice_uuid')->nullable();
                    }
                }
            });
        }
    }

    private function backfillRowUuids(): void
    {
        foreach (['subscriptions', 'purchase_transactions', 'invoices', 'payments', 'invoice_email_logs'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->select('id')
                ->chunkById(500, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');
        }
    }

    private function backfillForeignUuids(): void
    {
        $this->updateFromTable('subscriptions', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('subscriptions', 'package_id', 'package_uuid', 'packages');

        $this->updateFromTable('purchase_transactions', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('purchase_transactions', 'subscription_id', 'subscription_uuid', 'subscriptions');

        $this->updateFromTable('invoices', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('invoices', 'purchase_transaction_id', 'purchase_transaction_uuid', 'purchase_transactions');
        $this->updateFromTable('invoices', 'subscription_id', 'subscription_uuid', 'subscriptions');

        $this->updateFromTable('payments', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('payments', 'subscription_id', 'subscription_uuid', 'subscriptions');
        $this->updateFromTable('payments', 'purchase_transaction_id', 'purchase_transaction_uuid', 'purchase_transactions');
        $this->updateFromTable('payments', 'invoice_id', 'invoice_uuid', 'invoices');

        $this->updateFromTable('invoice_email_logs', 'invoice_id', 'invoice_uuid', 'invoices');
    }

    private function addIndexesAndConstraints(): void
    {
        $this->safeIndex('subscriptions', 'company_uuid', 'subscriptions_company_uuid_idx');
        $this->safeIndex('subscriptions', 'package_uuid', 'subscriptions_package_uuid_idx');

        $this->safeIndex('purchase_transactions', 'company_uuid', 'purchase_transactions_company_uuid_idx');
        $this->safeIndex('purchase_transactions', 'subscription_uuid', 'purchase_transactions_subscription_uuid_idx');

        $this->safeIndex('invoices', 'company_uuid', 'invoices_company_uuid_idx');
        $this->safeIndex('invoices', 'purchase_transaction_uuid', 'invoices_purchase_transaction_uuid_idx');
        $this->safeIndex('invoices', 'subscription_uuid', 'invoices_subscription_uuid_idx');

        $this->safeIndex('payments', 'company_uuid', 'payments_company_uuid_idx');
        $this->safeIndex('payments', 'subscription_uuid', 'payments_subscription_uuid_idx');
        $this->safeIndex('payments', 'purchase_transaction_uuid', 'payments_purchase_transaction_uuid_idx');
        $this->safeIndex('payments', 'invoice_uuid', 'payments_invoice_uuid_idx');

        $this->safeIndex('invoice_email_logs', 'invoice_uuid', 'invoice_email_logs_invoice_uuid_idx');

        $this->safeForeign('subscriptions', 'company_uuid', 'companies', 'uuid', 'subscriptions_company_uuid_fk', 'cascade');
        $this->safeForeign('subscriptions', 'package_uuid', 'packages', 'uuid', 'subscriptions_package_uuid_fk', 'restrict');

        $this->safeForeign('purchase_transactions', 'company_uuid', 'companies', 'uuid', 'purchase_transactions_company_uuid_fk', 'cascade');
        $this->safeForeign('purchase_transactions', 'subscription_uuid', 'subscriptions', 'uuid', 'purchase_transactions_subscription_uuid_fk', 'null');

        $this->safeForeign('invoices', 'company_uuid', 'companies', 'uuid', 'invoices_company_uuid_fk', 'cascade');
        $this->safeForeign('invoices', 'purchase_transaction_uuid', 'purchase_transactions', 'uuid', 'invoices_purchase_transaction_uuid_fk', 'null');
        $this->safeForeign('invoices', 'subscription_uuid', 'subscriptions', 'uuid', 'invoices_subscription_uuid_fk', 'null');

        $this->safeForeign('payments', 'company_uuid', 'companies', 'uuid', 'payments_company_uuid_fk', 'cascade');
        $this->safeForeign('payments', 'subscription_uuid', 'subscriptions', 'uuid', 'payments_subscription_uuid_fk', 'null');
        $this->safeForeign('payments', 'purchase_transaction_uuid', 'purchase_transactions', 'uuid', 'payments_purchase_transaction_uuid_fk', 'null');
        $this->safeForeign('payments', 'invoice_uuid', 'invoices', 'uuid', 'payments_invoice_uuid_fk', 'null');

        $this->safeForeign('invoice_email_logs', 'invoice_uuid', 'invoices', 'uuid', 'invoice_email_logs_invoice_uuid_fk', 'cascade');
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->index($column, $name);
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $tableBlueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } elseif ($onDelete === 'restrict') {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function updateFromTable(string $table, string $legacyIdColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $legacyIdColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        if (! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyIdColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyIdColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($legacyIdColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyIdColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyIdColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
    }
};
