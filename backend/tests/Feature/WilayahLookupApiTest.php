<?php

namespace Tests\Feature;

use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WilayahLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wilayah_lookup_endpoints_accept_id_based_relations(): void
    {
        $auth = $this->createHcmAdminWithCompany();

        $province = WilayahProvince::query()->create([
            'code' => '11',
            'name' => 'Aceh',
        ]);
        $regency = WilayahRegency::query()->create([
            'province_id' => $province->id,
            'code' => '11.01',
            'name' => 'Kabupaten Aceh Selatan',
        ]);
        $district = WilayahDistrict::query()->create([
            'regency_id' => $regency->id,
            'code' => '11.01.01',
            'name' => 'Bakongan',
        ]);
        WilayahVillage::query()->create([
            'district_id' => $district->id,
            'code' => '11.01.01.1001',
            'name' => 'Paya Dapur',
        ]);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer '.$auth['token'],
        ], $auth['company_id']);

        $this->getJson('/v1/hcm/wilayah/provinces', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $province->id,
                'code' => '11',
                'name' => 'Aceh',
            ]);

        $this->getJson('/v1/hcm/wilayah/regencies?provinceId='.$province->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $regency->id,
                'province_id' => $province->id,
                'code' => '11.01',
                'name' => 'Kabupaten Aceh Selatan',
            ]);

        $this->getJson('/v1/hcm/wilayah/districts?regencyId='.$regency->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $district->id,
                'regency_id' => $regency->id,
                'code' => '11.01.01',
                'name' => 'Bakongan',
            ]);

        $this->getJson('/v1/hcm/wilayah/villages?districtId='.$district->id, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'district_id' => $district->id,
                'code' => '11.01.01.1001',
                'name' => 'Paya Dapur',
            ]);
    }
}
