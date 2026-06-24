<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Geofence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HcmGeofenceApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'code' => 'gf_test_co',
            'name' => 'Geofence Test Co',
            'legal_name' => 'Geofence Test Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = User::query()->create([
            'name' => 'GF Admin',
            'email' => 'gf.admin@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'gf.admin@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();
        $this->token = (string) $login->json('data.accessToken');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Code' => $this->company->code,
        ];
    }

    public function test_list_geofences_empty(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_create_geofence(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'Kantor Pusat',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 500,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Kantor Pusat')
            ->assertJsonPath('data.latitude', -6.2088)
            ->assertJsonPath('data.longitude', 106.8456)
            ->assertJsonPath('data.radius_meters', 500)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_show_geofence(): void
    {
        $gf = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Gudang',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences/'.$gf->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Gudang');
    }

    public function test_update_geofence(): void
    {
        $gf = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Cabang Lama',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->putJson('/v1/hcm/geofences/'.$gf->id, [
                'name' => 'Cabang Baru',
                'radius_meters' => 300,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cabang Baru')
            ->assertJsonPath('data.radius_meters', 300);
    }

    public function test_delete_geofence(): void
    {
        $gf = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'To Delete',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson('/v1/hcm/geofences/'.$gf->id)
            ->assertOk();

        $this->assertModelMissing($gf);
    }

    public function test_create_geofence_rejects_duplicate_name(): void
    {
        Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Duplikat',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'Duplikat',
                'latitude' => -6.3000,
                'longitude' => 106.7000,
                'radius_meters' => 300,
            ])
            ->assertStatus(422);
    }

    public function test_create_geofence_rejects_invalid_radius(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'Radius Kecil',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_create_geofence_rejects_invalid_coordinates(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'Invalid',
                'latitude' => 200,
                'longitude' => 106.8456,
                'radius_meters' => 200,
            ])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access_geofences(): void
    {
        $user = User::query()->create([
            'name' => 'Regular User',
            'email' => 'regular.gf@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'regular.gf@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();
        $userToken = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$userToken,
            'X-Company-Code' => $this->company->code,
        ])->getJson('/v1/hcm/geofences')
            ->assertStatus(403);
    }

    public function test_cross_company_geofence_invisible(): void
    {
        $gf = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Private',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        $otherCompany = Company::query()->create([
            'code' => 'other_gf_co',
            'name' => 'Other GF Co',
            'legal_name' => 'Other GF Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other GF Admin',
            'email' => 'other.gf@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'other.gf@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $otherCompany->code,
        ]);
        $login->assertOk();
        $otherToken = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$otherToken,
            'X-Company-Code' => $otherCompany->code,
        ])->getJson('/v1/hcm/geofences/'.$gf->id)
            ->assertStatus(404);
    }

    public function test_geofence_not_found_returns_404(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences/99999')
            ->assertStatus(404);
    }

    public function test_geofence_list_returns_paginated(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Geofence::query()->create([
                'company_id' => $this->company->id,
                'name' => 'Geofence '.$i,
                'latitude' => -6.2000 + ($i * 0.01),
                'longitude' => 106.8000 + ($i * 0.01),
                'radius_meters' => 200,
                'is_active' => true,
            ]);
        }

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_create_geofence_defaults_is_active(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'Active Default',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_meters' => 200,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_update_geofence_rejects_duplicate_name(): void
    {
        Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Alpha',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $beta = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Beta',
            'latitude' => -6.3000,
            'longitude' => 106.7000,
            'radius_meters' => 300,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->putJson('/v1/hcm/geofences/'.$beta->id, [
                'name' => 'Alpha',
            ])
            ->assertStatus(422);
    }

    public function test_list_geofences_search_filter(): void
    {
        Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Kantor Pusat',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Gudang',
            'latitude' => -6.3000,
            'longitude' => 106.7000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences?search=Pusat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kantor Pusat');
    }

    public function test_list_geofences_per_page_clamping(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Geofence::query()->create([
                'company_id' => $this->company->id,
                'name' => 'GF '.$i,
                'latitude' => -6.2000 + ($i * 0.01),
                'longitude' => 106.8000 + ($i * 0.01),
                'radius_meters' => 200,
                'is_active' => true,
            ]);
        }

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences?perPage=999')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.perPage', 100);
    }

    public function test_show_geofence_by_uuid(): void
    {
        $gf = Geofence::query()->create([
            'company_id' => $this->company->id,
            'name' => 'UUID Test',
            'latitude' => -6.2000,
            'longitude' => 106.8000,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/geofences/'.$gf->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $gf->uuid);
    }

    public function test_create_geofence_at_pole(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'North Pole',
                'latitude' => 90.0,
                'longitude' => 0.0,
                'radius_meters' => 100,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.latitude', 90);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/geofences', [
                'name' => 'South Pole',
                'latitude' => -90.0,
                'longitude' => 0.0,
                'radius_meters' => 100,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.latitude', -90);
    }
}
