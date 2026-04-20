<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterGateWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_gate_page_directs_guest_to_company_onboarding_flow(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Daftarkan company Anda dari landing page')
            ->assertSee('/landing#pricing', false)
            ->assertSee(route('trial'), false)
            ->assertDontSee('Please enter your details to sign up');
    }

    public function test_legacy_register_variants_redirect_to_single_register_gate(): void
    {
        $this->get('/register-2')->assertRedirect(route('register'));
        $this->get('/register-3')->assertRedirect(route('register'));
    }

    public function test_landing_pricing_guides_guest_to_select_plan_before_company_onboarding(): void
    {
        $package = Package::factory()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'status' => 'active',
        ]);

        $this->get('/landing')
            ->assertOk()
            ->assertSee('Pilih plan yang aktif, lalu lanjut ke form onboarding company dengan paket yang sudah terpilih.')
            ->assertSee('Pilih plan')
            ->assertSee('/trial?packageId='.$package->uuid, false)
            ->assertSee('Daftarkan company');
    }

    public function test_trial_page_preselects_package_from_landing_choice(): void
    {
        Package::factory()->create([
            'code' => 'trial',
            'name' => 'Trial',
            'status' => 'active',
        ]);

        $selectedPackage = Package::factory()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'status' => 'active',
        ]);

        $this->get('/trial?packageId='.$selectedPackage->uuid)
            ->assertOk()
            ->assertSee('Pilih plan lalu buat company & owner', false)
            ->assertSee('Plan yang kamu pilih dari landing akan otomatis terbawa ke form ini.')
            ->assertSee('value="'.$selectedPackage->uuid.'" selected', false)
            ->assertSee('Daftarkan company');
    }
}