<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterGateWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_redirects_guest_to_unified_landing_onboarding(): void
    {
        $this->get('/register')
            ->assertRedirect(route('landing', [
                'openOnboarding' => 1,
                'startMode' => 'pending_payment',
            ]));
    }

    public function test_legacy_register_variants_redirect_to_unified_landing_onboarding(): void
    {
        $expected = route('landing', [
            'openOnboarding' => 1,
            'startMode' => 'pending_payment',
        ]);

        $this->get('/register-2')->assertRedirect($expected);
        $this->get('/register-3')->assertRedirect($expected);
    }

    public function test_trial_route_without_params_redirects_to_unified_landing_onboarding(): void
    {
        $this->get('/trial')
            ->assertRedirect(route('landing', ['openOnboarding' => 1]));
    }

    public function test_trial_route_preserves_package_and_start_mode_as_unified_landing_params(): void
    {
        $package = Package::factory()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'status' => 'active',
        ]);

        $this->get('/trial?packageId='.$package->uuid)
            ->assertRedirect(route('landing', [
                'openOnboarding' => 1,
                'package' => $package->uuid,
            ]));

        $this->get('/trial?startMode=pending_payment')
            ->assertRedirect(route('landing', [
                'openOnboarding' => 1,
                'startMode' => 'pending_payment',
            ]));
    }

    public function test_landing_pricing_guides_guest_to_select_plan_before_company_onboarding(): void
    {
        $package = Package::factory()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'status' => 'active',
        ]);

        $response = $this->get('/landing');
        $response->assertOk();

        // Landing is now SPA — check static shell content
        $response
            ->assertSee('Arkav - Human Capital Management — Platform HR Digital Terintegrasi')
            ->assertSee('<div id="root"></div>', false)
            ->assertSee('landing-app-data')
            ->assertSee('"code":"starter"', false)
            ->assertSee('"name":"Starter"', false);
    }

    public function test_landing_pricing_hides_internal_global_admin_packages(): void
    {
        Package::factory()->create([
            'code' => 'starter-visible',
            'name' => 'Starter Visible',
            'status' => 'active',
            'is_global_admin_only' => false,
        ]);

        Package::factory()->create([
            'code' => 'unlimited-internal',
            'name' => 'Unlimited (Global Admin)',
            'description' => 'Paket internal khusus global super admin. Tidak ditampilkan ke katalog publik.',
            'status' => 'active',
            'is_global_admin_only' => true,
        ]);

        $response = $this->get('/landing');
        $response->assertOk();
        // Landing is now SPA — check package data in JSON bootstrap
        $response
            ->assertSee('"name":"Starter Visible"', false)
            ->assertSee('"code":"starter-visible"', false)
            ->assertDontSee('"name":"Unlimited (Global Admin)"', false)
            ->assertDontSee('"code":"unlimited-internal"', false);
    }
}
