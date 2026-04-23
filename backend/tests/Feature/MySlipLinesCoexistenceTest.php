<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * M2/M3 — Coexistence test untuk `GET /v1/hcm/payroll/my-slip-lines`:
 *  - Monthly finalized + THR finalized di periode sama: `data.runs[]` berisi 2 entri.
 *  - Monthly finalized + PKWT finalized di periode sama: `data.runs[]` berisi 2 entri.
 *  - Monthly + THR + PKWT bersama: `data.runs[]` berisi 3 entri.
 *  - `data.run` (primary) tetap mengambil monthly ketika ada monthly.
 *
 * Setup memakai register+login (sama dengan HcmPayrollPeriodApiTest) sehingga
 * middleware tenant dan Sanctum guard berjalan apa adanya.
 */
#[IgnoreDeprecations]
class MySlipLinesCoexistenceTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndLogin(string $email, string $name, float $baseSalary = 4_000_000): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()->where('user_id', $user->id)->value('company_id');
        $this->assertGreaterThan(0, $companyId, 'Registered user must be attached to a company.');

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $companyId,
                'employment_status' => 'active',
                'base_salary' => $baseSalary,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return [
            'user' => $user,
            'companyId' => $companyId,
            'token' => (string) $login->json('data.accessToken'),
        ];
    }

    private function makeRunWithLine(
        int $companyId,
        int $periodId,
        int $userId,
        string $purpose,
        string $componentCode,
        float $amount,
        string $kind = 'addition'
    ): HcmPayrollRun {
        $run = HcmPayrollRun::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_period_id' => $periodId,
            'purpose' => $purpose,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);
        HcmPayrollLine::query()->create([
            'company_id' => $companyId,
            'hcm_payroll_run_id' => $run->id,
            'user_id' => $userId,
            'component_code' => $componentCode,
            'component_name' => ucfirst(str_replace('_', ' ', $componentCode)),
            'kind' => $kind,
            'category' => $kind === 'addition' ? 'other_addition' : 'other_deduction',
            'amount' => $amount,
            'sort_order' => 1,
            'meta' => ['source' => 'coexistence-test'],
        ]);

        return $run;
    }

    public function test_monthly_and_thr_runs_coexist_on_my_slip_lines(): void
    {
        $ctx = $this->registerAndLogin('coexist-thr@example.com', 'Coexist Thr');

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $ctx['companyId'],
            'period_year' => 2027,
            'period_month' => 5,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_MONTHLY, 'upah_pokok', 4_000_000);
        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_THR, 'thr', 4_000_000);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$ctx['token']])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2027&periodMonth=5')
            ->assertOk();

        $runs = (array) $res->json('data.runs');
        $this->assertCount(2, $runs, 'Expected both monthly and THR runs listed.');

        $purposes = array_map(static fn (array $r) => $r['purpose'] ?? null, $runs);
        $this->assertContains(HcmPayrollRun::PURPOSE_MONTHLY, $purposes);
        $this->assertContains(HcmPayrollRun::PURPOSE_THR, $purposes);

        $this->assertSame(HcmPayrollRun::PURPOSE_MONTHLY, $res->json('data.run.purpose'));

        $codes = array_map(static fn (array $l) => $l['componentCode'] ?? null, (array) $res->json('data.lines'));
        $this->assertContains('upah_pokok', $codes);
        $this->assertContains('thr', $codes);
    }

    public function test_monthly_and_pkwt_runs_coexist_on_my_slip_lines(): void
    {
        $ctx = $this->registerAndLogin('coexist-pkwt@example.com', 'Coexist Pkwt');

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $ctx['companyId'],
            'period_year' => 2027,
            'period_month' => 6,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_MONTHLY, 'upah_pokok', 4_500_000);
        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, 'kompensasi_pkwt', 2_250_000);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$ctx['token']])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2027&periodMonth=6')
            ->assertOk();

        $runs = (array) $res->json('data.runs');
        $this->assertCount(2, $runs, 'Expected both monthly and PKWT runs listed.');

        $purposes = array_map(static fn (array $r) => $r['purpose'] ?? null, $runs);
        $this->assertContains(HcmPayrollRun::PURPOSE_MONTHLY, $purposes);
        $this->assertContains(HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $purposes);

        $codes = array_map(static fn (array $l) => $l['componentCode'] ?? null, (array) $res->json('data.lines'));
        $this->assertContains('upah_pokok', $codes);
        $this->assertContains('kompensasi_pkwt', $codes);
    }

    public function test_three_run_purposes_coexist_in_same_period(): void
    {
        $ctx = $this->registerAndLogin('coexist-all@example.com', 'Coexist All');

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $ctx['companyId'],
            'period_year' => 2028,
            'period_month' => 1,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_MONTHLY, 'upah_pokok', 100);
        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_THR, 'thr', 100);
        $this->makeRunWithLine($ctx['companyId'], $period->id, $ctx['user']->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, 'kompensasi_pkwt', 100);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$ctx['token']])
            ->getJson('/v1/hcm/payroll/my-slip-lines?periodYear=2028&periodMonth=1')
            ->assertOk();

        $runs = (array) $res->json('data.runs');
        $this->assertCount(3, $runs, 'Expect monthly + thr + pkwt runs listed separately.');

        $this->assertSame(HcmPayrollRun::PURPOSE_MONTHLY, $res->json('data.run.purpose'));
    }
}
