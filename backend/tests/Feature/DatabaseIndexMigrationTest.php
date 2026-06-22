<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = '2026_06_22_000001_add_performance_indexes';

    private const EXPECTED_INDEXES = [
        'subscriptions' => [
            'subscriptions_status_ends_at_index' => ['status', 'ends_at'],
        ],
        'invoices' => [
            'invoices_paid_due_index' => ['is_paid', 'due_date'],
            'invoices_company_paid_due_index' => ['company_id', 'is_paid', 'due_date'],
        ],
        'overtime_requests' => [
            'overtime_requests_company_user_date_status_index' => ['company_id', 'user_id', 'work_date', 'status'],
        ],
        'hcm_payroll_periods' => [
            'hcm_payroll_periods_status_company_period_idx' => ['status', 'company_id', 'period_year', 'period_month'],
        ],
    ];

    public function test_migration_up_creates_expected_indexes(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $this->assertTrue(
                    Schema::hasIndex($table, $indexName),
                    "Index [{$indexName}] should exist on [{$table}] table after migration up."
                );
            }
        }
    }

    public function test_migration_rollback_drops_expected_indexes(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $this->assertFalse(
                    Schema::hasIndex($table, $indexName),
                    "Index [{$indexName}] should NOT exist on [{$table}] table after rollback."
                );
            }
        }
    }

    public function test_migration_is_idempotent(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $this->assertTrue(
                    Schema::hasIndex($table, $indexName),
                    "Index [{$indexName}] should still exist after re-running migration."
                );
            }
        }
    }

    public function test_migration_full_cycle_up_down_up(): void
    {
        // Up
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        // Down
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        // Up again
        $this->artisan('migrate', ['--path' => 'database/migrations/'.self::MIGRATION_PATH.'.php'])
            ->assertSuccessful();

        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            foreach ($indexes as $indexName => $columns) {
                $this->assertTrue(
                    Schema::hasIndex($table, $indexName),
                    "Index [{$indexName}] should exist after full cycle up-down-up on [{$table}]."
                );
            }
        }
    }
}
