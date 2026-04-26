<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmExtrasApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Hcm Extra',
            'email' => 'hcmextra@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'hcmextra@example.com',
            'password' => 'StrongPass1',
        ]);

        $login->assertOk();

        $token = $login->json('data.accessToken');
        $this->assertNotEmpty($token);

        return $token;
    }

    private function hcmAdminBearerToken(string $email = 'hcmextra-admin@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Hcm Extra Admin',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Admin']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_holidays_crud_smoke(): void
    {
        $token = $this->hcmAdminBearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/holidays')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/holidays', [
            'title' => 'Nyepi',
            'holidayDate' => '2026-04-01',
            'description' => 'test',
            'isActive' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $h = Holiday::query()->first();
        $this->assertNotNull($h);
        $this->assertDatabaseHas('holiday_calendars', [
            'holiday_id' => $h->id,
            'date' => '2026-04-01 00:00:00',
            'name' => 'Nyepi',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/v1/hcm/holidays/'.$h->id, [
            'title' => 'Nyepi',
            'holidayDate' => '2026-04-01',
            'description' => 'updated',
            'isActive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson('/v1/hcm/holidays/'.$h->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('holiday_calendars', [
            'date' => '2026-04-01',
            'name' => 'Nyepi',
        ]);
    }

    public function test_holidays_forbidden_for_non_hcm_admin(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/holidays')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays', [
                'title' => 'X',
                'holidayDate' => '2026-01-01',
            ])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays/sync-indonesia', [
                'year' => 2026,
            ])
            ->assertStatus(403);
    }

    public function test_holidays_sync_indonesia_upserts_api_rows(): void
    {
        $token = $this->hcmAdminBearerToken();
        Holiday::query()->create([
            'title' => 'Hari Buruh',
            'holiday_date' => '2026-05-01',
            'description' => 'manual override',
            'is_active' => true,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        Http::fake([
            'https://libur.deno.dev/api?year=2026' => Http::response([
                [
                    'date' => '2026-05-01',
                    'name' => 'Hari Buruh',
                ],
                [
                    'date' => '2026-12-25',
                    'name' => 'Hari Natal',
                ],
            ], 200),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays/sync-indonesia', [
                'year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skippedManual', 1);

        $this->assertDatabaseHas('holidays', [
            'title' => 'Hari Natal',
            'holiday_date' => '2026-12-25 00:00:00',
            'source' => 'api',
        ]);
        $this->assertDatabaseHas('holiday_calendars', [
            'date' => '2026-12-25 00:00:00',
            'name' => 'Hari Natal',
            'source' => 'api',
        ]);
        $this->assertDatabaseHas('holidays', [
            'title' => 'Hari Buruh',
            'holiday_date' => '2026-05-01 00:00:00',
            'source' => 'manual',
            'description' => 'manual override',
        ]);
        $this->assertDatabaseHas('holiday_calendars', [
            'date' => '2026-05-01 00:00:00',
            'name' => 'Hari Buruh',
            'source' => 'manual',
        ]);

        $natal = Holiday::query()->where('title', 'Hari Natal')->firstOrFail();
        $buruh = Holiday::query()->where('title', 'Hari Buruh')->firstOrFail();
        $this->assertDatabaseHas('holiday_calendars', [
            'holiday_id' => $natal->id,
            'name' => 'Hari Natal',
        ]);
        $this->assertDatabaseHas('holiday_calendars', [
            'holiday_id' => $buruh->id,
            'name' => 'Hari Buruh',
        ]);
    }

    public function test_holidays_sync_uses_fallback_provider_when_primary_fails(): void
    {
        $token = $this->hcmAdminBearerToken('hcmextra-admin-fallback@example.com');

        Http::fake([
            'https://libur.deno.dev/api?year=2026' => Http::response(['error' => 'temporary'], 503),
            'https://date.nager.at/api/v3/PublicHolidays/2026/ID' => Http::response([
                [
                    'date' => '2026-05-20',
                    'localName' => 'Hari Kebangkitan Nasional',
                    'name' => 'National Awakening Day',
                    'types' => ['Public'],
                ],
            ], 200),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays/sync-indonesia', ['year' => 2026])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'libur.deno.dev/api?year=2026');
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'date.nager.at/api/v3/PublicHolidays/2026/ID');
        });

        $this->assertDatabaseHas('holidays', [
            'title' => 'Hari Kebangkitan Nasional',
            'holiday_date' => '2026-05-20 00:00:00',
            'source' => 'api',
            'description' => 'Synced from date.nager.at [Public]',
        ]);
    }

    public function test_holiday_sync_reconciles_api_rows_without_removing_manual_rows(): void
    {
        $token = $this->hcmAdminBearerToken('hcmextra-admin-sync2@example.com');

        $manual = Holiday::query()->create([
            'title' => 'Ulang Tahun Perusahaan',
            'holiday_date' => '2026-10-10',
            'description' => 'manual company event',
            'is_active' => true,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);
        HolidayCalendar::query()->create([
            'company_id' => null,
            'holiday_id' => $manual->id,
            'date' => '2026-10-10',
            'name' => 'Ulang Tahun Perusahaan',
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'manual',
            'last_synced_at' => null,
        ]);

        $staleApi = Holiday::query()->create([
            'title' => 'API Stale Holiday',
            'holiday_date' => '2026-01-01',
            'description' => 'old api',
            'is_active' => true,
            'source' => 'api',
            'last_synced_at' => now()->subYear(),
        ]);
        HolidayCalendar::query()->create([
            'company_id' => null,
            'holiday_id' => $staleApi->id,
            'date' => '2026-01-01',
            'name' => 'API Stale Holiday',
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'api',
            'last_synced_at' => now()->subYear(),
        ]);

        Http::fake([
            'https://libur.deno.dev/api?year=2026' => Http::response([
                [
                    'date' => '2026-05-01',
                    'name' => 'Hari Buruh',
                ],
            ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays/sync-indonesia', ['year' => 2026])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cleanedStaleApi', 1)
            ->assertJsonPath('meta.linkage.unlinkedRows', 0);

        $this->assertDatabaseHas('holidays', [
            'title' => 'Ulang Tahun Perusahaan',
            'holiday_date' => '2026-10-10 00:00:00',
            'source' => 'manual',
        ]);
        $this->assertDatabaseMissing('holidays', [
            'id' => $staleApi->id,
        ]);
        $this->assertDatabaseMissing('holiday_calendars', [
            'holiday_id' => $staleApi->id,
        ]);
        $this->assertDatabaseHas('holiday_calendars', [
            'holiday_id' => $manual->id,
            'name' => 'Ulang Tahun Perusahaan',
            'source' => 'manual',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/holidays')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.linkage.unlinkedRows', 0);
    }

    public function test_holidays_sync_skips_cuti_bersama_rows_from_primary_calendar(): void
    {
        $token = $this->hcmAdminBearerToken('hcmextra-admin-cutibersama@example.com');

        Http::fake([
            'https://libur.deno.dev/api?year=2026' => Http::response([
                [
                    'date' => '2026-05-01',
                    'name' => 'Hari Buruh',
                ],
                [
                    'date' => '2026-05-02',
                    'name' => 'Cuti Bersama Hari Buruh',
                ],
            ], 200),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/holidays/sync-indonesia', ['year' => 2026])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skippedNonPrimary', 1);

        $this->assertDatabaseHas('holidays', [
            'title' => 'Hari Buruh',
            'holiday_date' => '2026-05-01 00:00:00',
            'source' => 'api',
        ]);
        $this->assertDatabaseMissing('holidays', [
            'title' => 'Cuti Bersama Hari Buruh',
            'holiday_date' => '2026-05-02 00:00:00',
            'source' => 'api',
        ]);
    }

    public function test_leave_request_create_and_me_scope(): void
    {
        $token = $this->bearerToken();

        // Seed balance for the test user
        $user = \App\Models\User::where('email', 'hcmextra@example.com')->first();
        $leaveType = \App\Models\LeaveType::where('code', 'annual_leave')->first();
        $companyId = $user->company_id ?? 1;

        \App\Models\EmployeeLeaveBalance::create([
            'company_id' => $companyId,
            'employee_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'balance' => 10.0,
            'used' => 0.0,
            'expired' => 0.0,
            'carried_forward' => 0.0,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/leave-requests', [
            'leaveType' => 'Annual',
            'dateFrom' => '2026-05-01',
            'dateTo' => '2026-05-02',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/leave-requests?scope=me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_overtime_request_create(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/v1/hcm/overtime-requests', [
            'workDate' => '2026-05-01',
            'minutes' => 120,
            'projectName' => 'Arcav',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
