<?php

namespace Tests\Feature;

use App\Services\Ai\AiIntentClassifier;
use Tests\TestCase;

/**
 * Regression tests for AiIntentClassifier.
 *
 * Covers: exact keyword match, typo/colloquial variations, noisy phrasing,
 * and duplicate-key regression (ensures procedural keywords are classified
 * to the correct intent, not silently overwritten by a duplicate key).
 */
class HcmAiClassifierNoiseTest extends TestCase
{
    private AiIntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new AiIntentClassifier();
    }

    // ─── leave.balance.self ────────────────────────────────────────────────

    /** @test */
    public function it_classifies_exact_leave_balance(): void
    {
        $this->assertSame('leave.balance.self', $this->classifier->classify('sisa cuti saya berapa?'));
    }

    /** @test */
    public function it_classifies_procedural_cara_mengajukan_cuti(): void
    {
        // Regression: before duplicate-key fix this would have been overwritten
        $this->assertSame('leave.balance.self', $this->classifier->classify('cara mengajukan cuti'));
    }

    /** @test */
    public function it_classifies_procedural_how_to_apply_leave(): void
    {
        $this->assertSame('leave.balance.self', $this->classifier->classify('how to apply leave'));
    }

    /** @test */
    public function it_classifies_noisy_leave_balance_with_typo(): void
    {
        // "cuti saya" appears in keywords despite surrounding noise
        $this->assertSame('leave.balance.self', $this->classifier->classify('bro cuti saya masih ada ga?'));
    }

    // ─── attendance.today.self ─────────────────────────────────────────────

    /** @test */
    public function it_classifies_exact_attendance_today(): void
    {
        $this->assertSame('attendance.today.self', $this->classifier->classify('sudah absen belum?'));
    }

    /** @test */
    public function it_classifies_procedural_cara_absen(): void
    {
        $this->assertSame('attendance.today.self', $this->classifier->classify('cara absen'));
    }

    /** @test */
    public function it_classifies_procedural_cara_checkin_typo(): void
    {
        $this->assertSame('attendance.today.self', $this->classifier->classify('cara checkin'));
    }

    /** @test */
    public function it_classifies_noisy_attendance_question(): void
    {
        $this->assertSame('attendance.today.self', $this->classifier->classify('eh gw udah absen hari ini belum ya?'));
    }

    // ─── payslip.latest.self ───────────────────────────────────────────────

    /** @test */
    public function it_classifies_exact_payslip(): void
    {
        $this->assertSame('payslip.latest.self', $this->classifier->classify('berapa gaji saya bulan ini?'));
    }

    /** @test */
    public function it_classifies_procedural_cara_lihat_gaji(): void
    {
        $this->assertSame('payslip.latest.self', $this->classifier->classify('cara lihat gaji'));
    }

    /** @test */
    public function it_classifies_procedural_cara_akses_payslip(): void
    {
        $this->assertSame('payslip.latest.self', $this->classifier->classify('cara akses payslip'));
    }

    /** @test */
    public function it_classifies_noisy_gaji_question(): void
    {
        $this->assertSame('payslip.latest.self', $this->classifier->classify('slip gaji saya yang bulan lalu'));
    }

    // ─── ticket.list.self ──────────────────────────────────────────────────

    /** @test */
    public function it_classifies_exact_ticket_list(): void
    {
        $this->assertSame('ticket.list.self', $this->classifier->classify('tiket yang saya buat'));
    }

    /** @test */
    public function it_classifies_procedural_cara_buat_tiket(): void
    {
        $this->assertSame('ticket.list.self', $this->classifier->classify('cara buat tiket'));
    }

    /** @test */
    public function it_classifies_procedural_how_to_create_ticket(): void
    {
        $this->assertSame('ticket.list.self', $this->classifier->classify('how to create ticket'));
    }

    /** @test */
    public function it_classifies_cara_komplain(): void
    {
        $this->assertSame('ticket.list.self', $this->classifier->classify('cara komplain ke hr'));
    }

    // ─── subscription.features.current ───────────────────────────────────

    /** @test */
    public function it_classifies_current_subscription_features_question(): void
    {
        $this->assertSame(
            'subscription.features.current',
            $this->classifier->classify('saya berlangganan paket saat ini fiturnya apa aja?')
        );
    }

    /** @test */
    public function it_classifies_english_current_package_features_question(): void
    {
        $this->assertSame(
            'subscription.features.current',
            $this->classifier->classify('what features are included in my package?')
        );
    }

    // ─── leave.history.other (admin + typo heuristic) ─────────────────────

    /** @test */
    public function it_classifies_exact_leave_history_other(): void
    {
        $this->assertSame('leave.history.other', $this->classifier->classify('siapa yang pernah cuti'));
    }

    /** @test */
    public function it_classifies_typo_karywan_pernah_cuti(): void
    {
        $this->assertSame('leave.history.other', $this->classifier->classify('siapa karywan yg pernah ajukan cuti di peridoe kmaren?'));
    }

    /** @test */
    public function it_classifies_colloquial_pegawai_ajukan_cuti(): void
    {
        $this->assertSame('leave.history.other', $this->classifier->classify('siapa pegawai yang ajukan cuti bulan lalu'));
    }

    // ─── unknown intent ────────────────────────────────────────────────────

    /** @test */
    public function it_returns_unknown_for_unrelated_question(): void
    {
        $this->assertSame('unknown', $this->classifier->classify('cuaca hari ini bagaimana?'));
    }

    /** @test */
    public function it_returns_unknown_for_empty_noise(): void
    {
        $this->assertSame('unknown', $this->classifier->classify('   '));
    }

    // ─── Duplicate key regression ─────────────────────────────────────────

    /**
     * Ensure "cara mengajukan cuti" resolves to leave.balance.self (NOT unknown),
     * which would happen if the duplicate key was silently clobbering the first definition.
     *
     * @test
     */
    public function duplicate_key_regression_procedural_leave_not_unknown(): void
    {
        $intent = $this->classifier->classify('cara mengajukan cuti');
        $this->assertNotSame('unknown', $intent, 'Procedural leave keyword must not fall through as unknown after duplicate-key fix');
        $this->assertSame('leave.balance.self', $intent);
    }

    /**
     * @test
     */
    public function duplicate_key_regression_procedural_attendance_not_unknown(): void
    {
        $intent = $this->classifier->classify('cara clock in');
        $this->assertNotSame('unknown', $intent);
        $this->assertSame('attendance.today.self', $intent);
    }

    /**
     * @test
     */
    public function duplicate_key_regression_procedural_payslip_not_unknown(): void
    {
        $intent = $this->classifier->classify('cara cek gaji');
        $this->assertNotSame('unknown', $intent);
        $this->assertSame('payslip.latest.self', $intent);
    }

    /**
     * @test
     */
    public function duplicate_key_regression_procedural_ticket_not_unknown(): void
    {
        $intent = $this->classifier->classify('cara bikin tiket');
        $this->assertNotSame('unknown', $intent);
        $this->assertSame('ticket.list.self', $intent);
    }
}
