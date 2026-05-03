<?php

namespace Tests\Feature;

use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class WilayahSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_upserts_and_prunes_wilayah_rows(): void
    {
        WilayahProvince::query()->create([
            'code' => '99',
            'name' => 'Legacy Province',
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($url === 'https://wilayah.id/api/provinces.json') {
                return Http::response([
                    'data' => [
                        ['code' => '11', 'name' => 'Aceh'],
                        ['code' => '31', 'name' => 'DKI Jakarta'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/regencies/11.json') {
                return Http::response([
                    'data' => [
                        ['code' => '11.01', 'name' => 'Kabupaten Aceh Selatan'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/regencies/31.json') {
                return Http::response([
                    'data' => [
                        ['code' => '31.74', 'name' => 'Kota Administrasi Jakarta Selatan'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/districts/11.01.json') {
                return Http::response([
                    'data' => [
                        ['code' => '11.01.01', 'name' => 'Bakongan'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/districts/31.74.json') {
                return Http::response([
                    'data' => [
                        ['code' => '31.74.09', 'name' => 'Jagakarsa'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/villages/11.01.01.json') {
                return Http::response([
                    'data' => [
                        ['code' => '11.01.01.1001', 'name' => 'Paya Dapur'],
                    ],
                ]);
            }

            if ($url === 'https://wilayah.id/api/villages/31.74.09.json') {
                return Http::response([
                    'data' => [
                        ['code' => '31.74.09.1001', 'name' => 'Jagakarsa'],
                    ],
                ]);
            }

            return Http::response(['data' => []]);
        });

        $this->artisan('wilayah:sync')->assertExitCode(0);

        $this->assertDatabaseMissing('wilayah_provinces', ['code' => '99']);
        $this->assertDatabaseHas('wilayah_provinces', ['code' => '11', 'name' => 'Aceh']);
        $this->assertDatabaseHas('wilayah_provinces', ['code' => '31', 'name' => 'DKI Jakarta']);

        $province = WilayahProvince::query()->where('code', '11')->firstOrFail();
        $regency = WilayahRegency::query()->where('code', '11.01')->firstOrFail();
        $district = WilayahDistrict::query()->where('code', '11.01.01')->firstOrFail();

        $this->assertSame((int) $province->id, (int) $regency->province_id);
        $this->assertSame((int) $regency->id, (int) $district->regency_id);
        $this->assertDatabaseHas('wilayah_villages', ['code' => '11.01.01.1001', 'name' => 'Paya Dapur']);
        $this->assertDatabaseHas('wilayah_villages', ['code' => '31.74.09.1001', 'name' => 'Jagakarsa']);
    }

    public function test_sync_command_is_idempotent(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match ($url) {
                'https://wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '11', 'name' => 'Aceh']]]),
                'https://wilayah.id/api/regencies/11.json' => Http::response(['data' => [['code' => '11.01', 'name' => 'Kabupaten Aceh Selatan']]]),
                'https://wilayah.id/api/districts/11.01.json' => Http::response(['data' => [['code' => '11.01.01', 'name' => 'Bakongan']]]),
                'https://wilayah.id/api/villages/11.01.01.json' => Http::response(['data' => [['code' => '11.01.01.1001', 'name' => 'Paya Dapur']]]),
                default => Http::response(['data' => []]),
            };
        });

        $this->artisan('wilayah:sync')->assertExitCode(0);
        $this->artisan('wilayah:sync')->assertExitCode(0);

        $this->assertSame(1, WilayahProvince::query()->where('code', '11')->count());
        $this->assertSame(1, WilayahRegency::query()->where('code', '11.01')->count());
        $this->assertSame(1, WilayahDistrict::query()->where('code', '11.01.01')->count());
        $this->assertSame(1, WilayahVillage::query()->where('code', '11.01.01.1001')->count());
    }

    public function test_locations_pages_render_synced_data_after_sync(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match ($url) {
                'https://wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '11', 'name' => 'Aceh']]]),
                'https://wilayah.id/api/regencies/11.json' => Http::response(['data' => [['code' => '11.01', 'name' => 'Kabupaten Aceh Selatan']]]),
                'https://wilayah.id/api/districts/11.01.json' => Http::response(['data' => [['code' => '11.01.01', 'name' => 'Bakongan']]]),
                'https://wilayah.id/api/villages/11.01.01.json' => Http::response(['data' => [['code' => '11.01.01.1001', 'name' => 'Paya Dapur']]]),
                default => Http::response(['data' => []]),
            };
        });

        $this->artisan('wilayah:sync')->assertExitCode(0);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/countries')
            ->assertOk()
            ->assertSee('Provinces')
            ->assertSee('Aceh')
            ->assertDontSee('United States')
            ->assertDontSee('Canada');

        $this->actingAs($user)
            ->get('/states')
            ->assertOk()
            ->assertSee('Regencies / Cities')
            ->assertSee('Kabupaten Aceh Selatan')
            ->assertDontSee('California');

        $this->actingAs($user)
            ->get('/cities')
            ->assertOk()
            ->assertSee('Districts / Subdistricts')
            ->assertSee('Bakongan')
            ->assertDontSee('Los Angeles');

        $this->actingAs($user)
            ->get('/villages')
            ->assertOk()
            ->assertSee('Villages / Subvillages')
            ->assertSee('Paya Dapur')
            ->assertDontSee('New York');
    }

    public function test_locations_manual_sync_endpoint_runs_and_sets_flash_message(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match ($url) {
                'https://wilayah.id/api/provinces.json' => Http::response(['data' => [['code' => '11', 'name' => 'Aceh']]]),
                'https://wilayah.id/api/regencies/11.json' => Http::response(['data' => [['code' => '11.01', 'name' => 'Kabupaten Aceh Selatan']]]),
                'https://wilayah.id/api/districts/11.01.json' => Http::response(['data' => [['code' => '11.01.01', 'name' => 'Bakongan']]]),
                'https://wilayah.id/api/villages/11.01.01.json' => Http::response(['data' => [['code' => '11.01.01.1001', 'name' => 'Paya Dapur']]]),
                default => Http::response(['data' => []]),
            };
        });

        $user = User::factory()->create([
            'email' => (string) config('hcm.admin_email', 'qa.login@example.com'),
        ]);
        Cache::forget(\App\Services\Wilayah\WilayahSyncService::PROGRESS_CACHE_KEY);
        $csrfToken = 'test-sync-token';
        $response = $this->actingAs($user)
            ->withSession(['_token' => $csrfToken])
            ->from('/states')
            ->post('/locations/sync', ['_token' => $csrfToken]);

        $response->assertRedirect('/states');
        $response->assertSessionHas('wilayahSyncStatus');
        $this->assertDatabaseHas('wilayah_provinces', ['code' => '11', 'name' => 'Aceh']);
    }

    public function test_locations_manual_sync_endpoint_is_blocked_for_non_global_admin(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Cache::forget(\App\Services\Wilayah\WilayahSyncService::PROGRESS_CACHE_KEY);
        $csrfToken = 'test-sync-token-forbidden';

        $response = $this->actingAs($user)
            ->withSession(['_token' => $csrfToken])
            ->from('/states')
            ->post('/locations/sync', ['_token' => $csrfToken]);

        $response->assertRedirect('/employee-dashboard');
        Http::assertNothingSent();
    }

    public function test_locations_pages_support_search_and_pagination_controls(): void
    {
        WilayahProvince::query()->create([
            'code' => '11',
            'name' => 'Aceh',
        ]);
        WilayahProvince::query()->create([
            'code' => '31',
            'name' => 'DKI Jakarta',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/countries?q=Aceh&perPage=25')
            ->assertOk()
            ->assertSee('Aceh')
            ->assertDontSee('DKI Jakarta')
            ->assertSee('Search code / province');
    }
}