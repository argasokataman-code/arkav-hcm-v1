<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesWebTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Privacy Policy
    // -------------------------------------------------------------------------

    public function test_privacy_policy_route_returns_200(): void
    {
        $this->get('/privacy-policy')
            ->assertOk();
    }

    public function test_privacy_policy_renders_without_admin_sidebar(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        // guest-legal layout should NOT contain sidebar navigation
        $response->assertDontSee('sidebar', false);
        $response->assertDontSee('mainlayout', false);
    }

    public function test_privacy_policy_contains_uu_pdp_reference(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Undang-Undang Nomor 27 Tahun 2022')
            ->assertSee('UU PDP');
    }

    public function test_privacy_policy_contains_dpo_contact_from_config(): void
    {
        $dpoEmail = config('pdp.dpo_email', 'dpo@arcav.id');

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee($dpoEmail);
    }

    public function test_privacy_policy_contains_all_required_sections(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();

        // 9 sections per UU PDP
        $response->assertSee('Identitas Pengendali');
        $response->assertSee('Data Pribadi yang Kami Kumpulkan');
        $response->assertSee('Dasar dan Tujuan Pemrosesan');
        $response->assertSee('Pihak Ketiga');
        $response->assertSee('Retensi Data');
        $response->assertSee('Hak Subjek Data');
        $response->assertSee('Keamanan Data');
        $response->assertSee('Notifikasi Insiden');
        $response->assertSee('Hubungi Kami');
    }

    public function test_privacy_policy_contains_breach_notification_3x24h(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('3 × 24 jam')
            ->assertSee('Pasal 46');
    }

    // -------------------------------------------------------------------------
    // Terms & Conditions
    // -------------------------------------------------------------------------

    public function test_terms_condition_route_returns_200(): void
    {
        $this->get('/terms-condition')
            ->assertOk();
    }

    public function test_terms_condition_renders_without_admin_sidebar(): void
    {
        $response = $this->get('/terms-condition');

        $response->assertOk();
        $response->assertDontSee('sidebar', false);
        $response->assertDontSee('mainlayout', false);
    }

    public function test_terms_condition_references_uu_pdp(): void
    {
        $this->get('/terms-condition')
            ->assertOk()
            ->assertSee('UU No. 27 Tahun 2022')
            ->assertSee('Pelindungan Data Pribadi');
    }

    public function test_terms_condition_references_indonesian_law(): void
    {
        $this->get('/terms-condition')
            ->assertOk()
            ->assertSee('hukum Republik Indonesia');
    }

    public function test_terms_condition_contains_all_required_sections(): void
    {
        $response = $this->get('/terms-condition');

        $response->assertOk();

        // 13 sections per legal audit checklist
        $response->assertSee('Definisi');
        $response->assertSee('Penerimaan dan Perubahan');
        $response->assertSee('Pendaftaran dan Akun');
        $response->assertSee('Langganan');
        $response->assertSee('Kewajiban Pengguna');
        $response->assertSee('Data Pribadi');
        $response->assertSee('Hak Kekayaan Intelektual');
        $response->assertSee('Ketersediaan Layanan');
        $response->assertSee('Pembatasan Tanggung Jawab');
        $response->assertSee('Hukum yang Berlaku');
        $response->assertSee('Penyelesaian Sengketa');
        $response->assertSee('Pengakhiran');
        $response->assertSee('Hubungi Kami');
    }

    public function test_terms_condition_contains_subscription_billing_section(): void
    {
        $this->get('/terms-condition')
            ->assertOk()
            ->assertSee('pending_payment')
            ->assertSee('pembayaran');
    }

    public function test_terms_condition_contains_dpo_email_from_config(): void
    {
        $dpoEmail = config('pdp.dpo_email', 'dpo@arcav.id');

        $this->get('/terms-condition')
            ->assertOk()
            ->assertSee($dpoEmail);
    }

    public function test_terms_condition_does_not_contain_smarthr_brand(): void
    {
        $this->get('/terms-condition')
            ->assertOk()
            ->assertDontSee('SmartHR')
            ->assertDontSee('Smarthr')
            ->assertDontSee('smarthr');
    }

    public function test_terms_condition_links_to_privacy_policy(): void
    {
        $this->get('/terms-condition')
            ->assertOk()
            ->assertSee('Kebijakan Privasi');
    }

    // -------------------------------------------------------------------------
    // Cross-linking
    // -------------------------------------------------------------------------

    public function test_privacy_policy_and_terms_are_cross_linked(): void
    {
        // T&C should link to privacy policy
        $this->get('/terms-condition')
            ->assertSee('privacy-policy');

        // Both should be accessible from guest context (no auth required)
        $this->get('/privacy-policy')->assertOk();
        $this->get('/terms-condition')->assertOk();
    }
}
