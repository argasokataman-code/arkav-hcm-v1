<?php

namespace Tests\Unit;

use App\Services\Hcm\BpjsContributionCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BpjsContributionCalculatorTest extends TestCase
{
    private BpjsContributionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new BpjsContributionCalculator();
    }

    // ────────────────────────────────────────────────────
    // BPJS Kesehatan
    // ────────────────────────────────────────────────────

    public function test_bpjs_kes_employee_no_cap(): void
    {
        $result = $this->calc->calculateBpjsKesehatan(5_000_000, 1.0, 'employee');

        $this->assertSame('bpjs_kesehatan', $result['program']);
        $this->assertSame('employee', $result['contribution_party']);
        $this->assertSame(5_000_000.0, $result['base_salary_used']);
        $this->assertSame(1.0, $result['rate_used']);
        $this->assertSame(50_000.0, $result['contribution_amount']); // 5_000_000 * 1% = 50_000
        $this->assertFalse($result['cap_applied']);
    }

    public function test_bpjs_kes_applies_default_cap(): void
    {
        // Gaji 15 jt > default cap 12 jt
        $result = $this->calc->calculateBpjsKesehatan(15_000_000, 1.0, 'employee');

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(12_000_000.0, $result['base_salary_used']);
        $this->assertSame(120_000.0, $result['contribution_amount']); // 12_000_000 * 1% = 120_000
    }

    public function test_bpjs_kes_applies_tenant_override_cap(): void
    {
        $result = $this->calc->calculateBpjsKesehatan(10_000_000, 4.0, 'employer', 8_000_000.0);

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(8_000_000.0, $result['base_salary_used']);
        $this->assertSame(320_000.0, $result['contribution_amount']); // 8_000_000 * 4% = 320_000
    }

    // ────────────────────────────────────────────────────
    // JP
    // ────────────────────────────────────────────────────

    public function test_jp_no_cap_below_default(): void
    {
        $result = $this->calc->calculateJp(5_000_000, 1.0, 'employee');

        $this->assertFalse($result['cap_applied']);
        $this->assertSame(5_000_000.0, $result['base_salary_used']);
        $this->assertSame(50_000.0, $result['contribution_amount']);
    }

    public function test_jp_applies_default_cap(): void
    {
        // Gaji 12 jt > default cap 9.077.600
        $result = $this->calc->calculateJp(12_000_000, 1.0, 'employee');

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(BpjsContributionCalculator::JP_DEFAULT_CAP, $result['base_salary_used']);
        $expected = round(BpjsContributionCalculator::JP_DEFAULT_CAP * 0.01, 2);
        $this->assertSame($expected, $result['contribution_amount']);
    }

    public function test_jp_applies_tenant_cap(): void
    {
        $result = $this->calc->calculateJp(12_000_000, 1.0, 'employer', 10_000_000.0);

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(10_000_000.0, $result['base_salary_used']);
        $this->assertSame(100_000.0, $result['contribution_amount']);
    }

    // ────────────────────────────────────────────────────
    // JKK
    // ────────────────────────────────────────────────────

    public function test_jkk_risk_category_1(): void
    {
        $result = $this->calc->calculateJkk(5_000_000, 1);

        $this->assertSame('jkk', $result['program']);
        $this->assertSame('employer', $result['contribution_party']);
        $this->assertSame(0.24, $result['rate_used']);
        $this->assertSame(round(5_000_000 * 0.0024, 2), $result['contribution_amount']);
        $this->assertSame('risk_category', $result['rate_source']);
        $this->assertSame(1, $result['riskCategory']);
    }

    public function test_jkk_risk_category_5(): void
    {
        $result = $this->calc->calculateJkk(5_000_000, 5);

        $this->assertSame(1.74, $result['rate_used']);
        $this->assertSame(round(5_000_000 * 0.0174, 2), $result['contribution_amount']);
    }

    public function test_jkk_all_risk_rates_match_constants(): void
    {
        foreach (BpjsContributionCalculator::JKK_RISK_RATES as $cat => $rate) {
            $result = $this->calc->calculateJkk(10_000_000, $cat);
            $this->assertSame($rate, $result['rate_used'], "JKK risk category {$cat} rate mismatch");
            $this->assertSame(round(10_000_000 * ($rate / 100), 2), $result['contribution_amount']);
        }
    }

    public function test_jkk_invalid_risk_category_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculateJkk(5_000_000, 6);
    }

    public function test_jkk_invalid_risk_category_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculateJkk(5_000_000, 0);
    }

    public function test_jkk_override_rate_clamped_to_max(): void
    {
        // Override lebih besar dari max (1.74) — harus di-clamp
        $result = $this->calc->calculateJkk(5_000_000, 3, 99.0);

        $this->assertSame(1.74, $result['rate_used']);
        $this->assertSame('tenant_override', $result['rate_source']);
    }

    public function test_jkk_override_rate_negative_clamped_to_zero(): void
    {
        $result = $this->calc->calculateJkk(5_000_000, 2, -0.5);

        $this->assertSame(0.0, $result['rate_used']);
        $this->assertSame(0.0, $result['contribution_amount']);
    }

    // ────────────────────────────────────────────────────
    // JHT
    // ────────────────────────────────────────────────────

    public function test_jht_no_cap(): void
    {
        $result = $this->calc->calculateJht(8_000_000, 2.0, 'employee');

        $this->assertSame('jht', $result['program']);
        $this->assertFalse($result['cap_applied']);
        $this->assertSame(8_000_000.0, $result['base_salary_used']);
        $this->assertSame(160_000.0, $result['contribution_amount']); // 8_000_000 * 2% = 160_000
    }

    // ────────────────────────────────────────────────────
    // JKM
    // ────────────────────────────────────────────────────

    public function test_jkm_employer_flat_rate(): void
    {
        $result = $this->calc->calculateJkm(10_000_000, 0.3);

        $this->assertSame('jkm', $result['program']);
        $this->assertSame('employer', $result['contribution_party']);
        $this->assertSame(30_000.0, $result['contribution_amount']); // 10_000_000 * 0.3% = 30_000
    }

    // ────────────────────────────────────────────────────
    // Generic calculate() dispatcher
    // ────────────────────────────────────────────────────

    public function test_generic_calculate_dispatches_bpjs_kes(): void
    {
        $result = $this->calc->calculate('bpjs_kesehatan', 'employee', 5_000_000, 1.0, [
            'bpjsKesSalaryCap' => 4_000_000,
        ]);

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(4_000_000.0, $result['base_salary_used']);
    }

    public function test_generic_calculate_dispatches_jp(): void
    {
        $result = $this->calc->calculate('jp', 'employee', 12_000_000, 1.0, [
            'jpSalaryCap' => 9_000_000,
        ]);

        $this->assertTrue($result['cap_applied']);
        $this->assertSame(9_000_000.0, $result['base_salary_used']);
    }

    public function test_generic_calculate_dispatches_jkk(): void
    {
        $result = $this->calc->calculate('jkk', 'employer', 10_000_000, 0.89, [
            'riskCategory' => 3,
        ]);

        $this->assertSame('jkk', $result['program']);
        $this->assertSame(0.89, $result['rate_used']);
    }

    public function test_generic_calculate_unknown_program_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->calculate('unknown_program', 'employee', 5_000_000, 1.0);
    }
}
