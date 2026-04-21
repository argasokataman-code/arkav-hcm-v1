<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterGateWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_redirects_guest_directly_to_company_onboarding_form(): void
    {
        $this->get('/register')
            ->assertRedirect(route('trial', ['startMode' => 'pending_payment']));
    }

    public function test_legacy_register_variants_redirect_directly_to_company_onboarding_form(): void
    {
        $this->get('/register-2')->assertRedirect(route('trial', ['startMode' => 'pending_payment']));
        $this->get('/register-3')->assertRedirect(route('trial', ['startMode' => 'pending_payment']));
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
            ->assertSee('Pilih paket yang cocok')
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
            ->assertSeeText('Pilih plan lalu buat company & owner')
            ->assertSee('Plan yang kamu pilih dari landing akan otomatis terbawa ke form ini.')
            ->assertSee('value="'.$selectedPackage->uuid.'" selected', false)
            ->assertSee('Daftarkan company');
    }

    public function test_register_mode_uses_paid_onboarding_copy_and_hides_trial_wording(): void
    {
        Package::factory()->create([
            'code' => 'trial',
            'name' => 'Trial',
            'status' => 'active',
        ]);

        Package::factory()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'status' => 'active',
        ]);

        $this->get('/trial?startMode=pending_payment')
            ->assertOk()
            ->assertSee('Registrasi Resmi Company')
            ->assertSee('Pilih paket berlangganan lalu buat company & owner')
            ->assertSee('value="pending_payment"', false)
            ->assertDontSee('Coba Trial Gratis')
            ->assertDontSee('value="trial" selected', false);
    }
}