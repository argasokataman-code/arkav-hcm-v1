<?php

/**
 * Termination Policy Profiles — Settlement Calculation Config
 *
 * Each key maps to a policy_profile_key resolved by `resolvePolicyProfileKey()`.
 *
 * Formula fields:
 *   up_multiplier    — multiplier applied on top of the statutory UP months
 *                      (1.0 = full severance per UU 13/2003 scale, 0 = not applicable)
 *   upmk_applicable  — whether Uang Penghargaan Masa Kerja (UPMK) is included
 *   uph_applicable   — whether Uang Penggantian Hak (15% × UP+UPMK) is included
 *   up_label         — display label for the UP line item
 *   formula_notes    — human-readable note on the legal basis
 *
 * UP scale (UU No.13/2003 Table, referenced by `upMonthsByServiceYear()`):
 *   < 1 yr → 1 mo,  1 yr → 1,  2 yr → 2,  3 yr → 3,  4 yr → 4,  5 yr → 5,
 *   6 yr → 6,  7 yr → 7,  ≥ 8 yr → 9
 *
 * UPMK scale: 3–6 yr → 2 mo,  6–9 → 3,  9–12 → 4,  12–15 → 5,  15–18 → 6,
 *   18–21 → 7,  21–24 → 8,  ≥ 24 → 10
 *
 * Last updated: 2026-05 (verify against internal HR policy before production use)
 */
return [
    'profiles' => [
        /**
         * PKWT contract ended — compensation via PkwtCompensationService; no UP/UPMK.
         */
        'pkwt_end_of_contract' => [
            'up_multiplier' => 0.0,
            'upmk_applicable' => false,
            'uph_applicable' => false,
            'up_label' => 'PKWT End-of-Contract (N/A)',
            'formula_notes' => 'PKWT termination uses separate PKWT compensation formula (PP 35/2021 Pasal 16). No pesangon.',
        ],

        /**
         * Normal retirement — UP × 1.75, UPMK, UPH.
         */
        'retirement' => [
            'up_multiplier' => 1.75,
            'upmk_applicable' => true,
            'uph_applicable' => true,
            'up_label' => 'Severance Pay (Pensiunan)',
            'formula_notes' => 'UU 13/2003 Pasal 167 — pensiun normal mendapat 1.75× pesangon, UPMK, dan penggantian hak.',
        ],

        /**
         * Company-initiated termination (efficiency, closure, force majeure).
         * Using 1× UP per UU 13/2003 policy baseline (PP 35/2021 allows 0.5× minimum;
         * companies commonly pay 1× as a policy decision — review with HR before deploy).
         */
        'company_termination' => [
            'up_multiplier' => 1.0,
            'upmk_applicable' => true,
            'uph_applicable' => true,
            'up_label' => 'Severance Pay (PHK Perusahaan)',
            'formula_notes' => 'UU 13/2003 Pasal 156 / PP 35/2021 — PHK oleh perusahaan. Minimal 0.5× menurut PP 35/2021; ditetapkan 1× sesuai kebijakan internal. Konfirmasi HR.',
        ],

        /**
         * Disciplinary or court order — no pesangon, UPMK still applies per policy.
         */
        'disciplinary_or_court' => [
            'up_multiplier' => 0.0,
            'upmk_applicable' => false,
            'uph_applicable' => false,
            'up_label' => 'Severance Pay (N/A — Disiplin)',
            'formula_notes' => 'UU 13/2003 Pasal 158/160 — PHK karena pelanggaran berat tidak mendapat pesangon. Verifikasi dengan kuasa hukum HR.',
        ],

        /**
         * Employee death — UP × 2, UPMK, UPH paid to legal heirs.
         */
        'deceased_employee' => [
            'up_multiplier' => 2.0,
            'upmk_applicable' => true,
            'uph_applicable' => true,
            'up_label' => 'Severance Pay (Meninggal Dunia)',
            'formula_notes' => 'UU 13/2003 Pasal 166 — meninggal dunia: 2× pesangon, UPMK, UPH dibayarkan kepada ahli waris.',
        ],

        /**
         * Long-term illness / medical termination — UP × 2, UPMK, UPH.
         */
        'medical_termination' => [
            'up_multiplier' => 2.0,
            'upmk_applicable' => true,
            'uph_applicable' => true,
            'up_label' => 'Severance Pay (Sakit/Cacat Permanen)',
            'formula_notes' => 'UU 13/2003 Pasal 172 — sakit berkepanjangan/cacat: 2× pesangon, UPMK, UPH.',
        ],

        /**
         * Voluntary resignation or unclassified reason — no UP (self-resignation
         * is not entitled to pesangon under Indonesian law); leave payout only.
         */
        'general_other' => [
            'up_multiplier' => 0.0,
            'upmk_applicable' => false,
            'uph_applicable' => false,
            'up_label' => 'Severance Pay (N/A — Pengunduran Diri)',
            'formula_notes' => 'Pengunduran diri sukarela umumnya tidak mendapat pesangon per UU 13/2003 kecuali ada kebijakan perusahaan berbeda. Payout utama adalah sisa cuti.',
        ],
    ],

    /**
     * Leave payout working-days denominator (standard: 25 hari kerja/bulan).
     * daily_rate = base_salary / working_days_per_month
     * leave_payout = remaining_balance_days × daily_rate
     */
    'leave_payout_working_days_per_month' => 25,

    /**
     * UPH rate: 15% of (UP + UPMK) — standard formula.
     */
    'uph_rate' => 0.15,
];
