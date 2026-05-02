<?php

namespace App\Services\Ai;

/**
 * Classifies a natural language message into a known AI intent.
 *
 * This is a keyword-based classifier (no LLM call) — fast, deterministic, and safe.
 * Intent must be confirmed BEFORE any auth/data operation is initiated.
 *
 * To add a new intent: add an entry to $intentPatterns with keyword arrays.
 * Order matters: more specific patterns should appear first.
 */
class AiIntentClassifier
{
    /**
     * @var array<string, array<string>>
     */
    private array $intentPatterns = [
        // ── Leave self ─────────────────────────────────────────────────────
        'leave.balance.self' => [
            'sisa cuti saya', 'saldo cuti saya', 'cuti saya berapa', 'cuti saya tinggal',
            'berapa cuti saya', 'jatah cuti saya', 'jatah cuti aku', 'sisa jatah cuti',
            'berapa hari cuti saya', 'berapa hari cuti aku', 'cuti tersisa',
            'leave balance saya', 'leave balance aku', 'remaining leave',
            'masih ada cuti', 'cuti masih ada', 'tinggal berapa cuti',
            'sisa cuti', 'saldo cuti', 'cuti saya', 'berapa cuti',
            'leave balance', 'cuti tersisa', 'jatah cuti',
            // Procedural / how-to (routed here so LLM gets some HR context)
            'cara mengajukan cuti', 'cara apply cuti', 'bagaimana mengajukan cuti',
            'cara request cuti', 'gimana cara cuti', 'minta cuti gimana',
            'prosedur cuti', 'alur pengajuan cuti', 'cara buat pengajuan cuti',
            'cara mengambil cuti', 'how to apply leave', 'how to request leave',
        ],
        'leave.history.self' => [
            'riwayat cuti saya', 'histori cuti saya', 'kapan saya cuti', 'cuti bulan ini saya',
            'cuti saya bulan', 'cuti saya tahun', 'cuti yang sudah saya', 'pengajuan cuti saya',
            'pernah cuti', 'leave history saya', 'kapan terakhir cuti',
            'cuti yang sudah diambil', 'cuti sudah dipakai', 'cuti yang dipakai',
            'riwayat cuti', 'histori cuti', 'kapan cuti', 'cuti bulan ini',
            'cuti tahun ini', 'leave history', 'cuti yang sudah',
        ],

        // ── Leave admin (other / company-wide) ────────────────────────────
        'leave.balance.other' => [
            'sisa cuti karyawan', 'saldo cuti karyawan', 'cuti karyawan berapa',
            'berapa cuti semua karyawan', 'rekap saldo cuti', 'cuti semua orang',
        ],
        'leave.history.other' => [
            'riwayat cuti karyawan', 'histori cuti karyawan', 'cuti karyawan bulan',
            'siapa yang pernah cuti', 'rekap pengajuan cuti karyawan',
            'siapa karyawan yang pernah ajukan cuti', 'siapa karyawan yang pernah mengajukan cuti',
            'karyawan yang ajukan cuti periode kemarin', 'karyawan yang mengajukan cuti periode kemarin',
            'siapa karyawan cuti bulan lalu', 'siapa pegawai yang ajukan cuti bulan lalu',
            'pengajuan cuti periode kemarin', 'riwayat pengajuan cuti periode kemarin',
        ],
        'leave.summary.company' => [
            'rekap cuti perusahaan', 'ringkasan cuti', 'berapa karyawan cuti hari ini',
            'siapa yang cuti', 'siapa cuti hari ini', 'summary cuti', 'leave summary',
            'rekap cuti', 'ringkasan cuti bulan',
        ],

        // ── Attendance self ────────────────────────────────────────────────
        'attendance.today.self' => [
            'sudah absen belum', 'sudah clock in', 'sudah check in', 'sudah masuk belum',
            'absen saya hari ini', 'clock-in saya', 'jam masuk saya', 'jam masuk tadi',
            'saya sudah absen', 'saya sudah masuk', 'checkin hari ini',
            'sudah absen', 'clock-in', 'clock in', 'absen hari ini',
            'jam masuk', 'sudah masuk', 'attendance today', 'checkin',
            // Procedural
            'cara absen', 'cara check in', 'cara clock in', 'bagaimana cara absen',
            'gimana cara absen', 'cara checkin', 'cara masuk absen',
            'how to check in', 'cara absensi', 'cara rekam kehadiran',
        ],
        'attendance.history.self' => [
            'riwayat absen saya', 'rekap absensi saya', 'absensi saya bulan', 'kehadiran saya',
            'berapa hari saya masuk', 'berapa hari masuk saya', 'kapan terakhir absen',
            'berapa hari tidak masuk', 'berapa hari alfa', 'berapa hari alpha',
            'history absen', 'attendance history', 'rekap kehadiran saya',
            'riwayat absen', 'rekap absensi', 'absensi bulan',
            'berapa hari masuk', 'attendance history',
        ],
        'attendance.summary.company' => [
            'kehadiran hari ini', 'siapa tidak masuk hari ini', 'siapa tidak absen',
            'absensi karyawan hari ini', 'attendance karyawan', 'rekap kehadiran harian',
            'siapa alpha hari ini', 'siapa alfa hari ini',
            'siapa tidak masuk', 'absensi karyawan',
            'attendance perusahaan', 'rekap kehadiran',
        ],

        // ── Payslip self ───────────────────────────────────────────────────
        'payslip.latest.self' => [
            'gaji saya bulan ini', 'slip gaji saya', 'payslip saya', 'gaji bulan ini',
            'gaji terakhir saya', 'gaji pokok saya', 'take home saya', 'berapa gaji saya',
            'komponen gaji saya', 'tunjangan saya berapa', 'berapa tunjangan saya',
            'gaji bersih saya', 'gaji kotor saya', 'gaji sudah masuk',
            'gaji saya', 'slip gaji', 'payslip', 'gaji bulan', 'take home',
            'gaji bersih', 'gaji kotor', 'gaji terakhir', 'komponen gaji',
            // Procedural
            'cara lihat gaji', 'cara cek gaji', 'cara lihat payslip',
            'cara lihat slip gaji', 'bagaimana melihat gaji',
            'how to check payslip', 'cara akses payslip',
        ],
        'payslip.history.self' => [
            'riwayat gaji saya', 'histori payslip saya', 'gaji saya 3 bulan',
            'gaji beberapa bulan lalu', 'payslip sebelumnya',
            'riwayat gaji', 'histori payslip', 'payslip 3 bulan', 'gaji beberapa bulan',
        ],

        // ── Payroll admin ──────────────────────────────────────────────────
        'payroll.run.status' => [
            'payroll sudah jalan', 'payroll bulan ini sudah', 'status payroll bulan',
            'run payroll sudah', 'penggajian sudah diproses', 'penggajian bulan ini',
            'payroll sudah', 'payroll bulan ini', 'status payroll', 'run payroll',
            'penggajian sudah',
        ],
        'payroll.run.summary' => [
            'total pengeluaran gaji', 'total payroll perusahaan', 'nominal penggajian total',
            'berapa total gaji karyawan', 'total biaya gaji', 'total gaji bulan ini',
            'total pengeluaran gaji', 'total payroll', 'nominal penggajian',
            'berapa total gaji',
        ],

        // ── Tickets self ───────────────────────────────────────────────────
        'ticket.status.self' => [
            'status tiket saya', 'tiket saya sudah diproses', 'pengaduan saya diproses',
            'sudah dibalas tiket saya', 'balasan tiket saya', 'ticket status saya',
            'status tiket', 'tiket saya', 'pengaduan saya', 'sudah diproses',
            'balasan tiket', 'ticket status',
        ],
        'ticket.list.self' => [
            'daftar tiket saya', 'ada tiket saya', 'list tiket saya', 'tiket saya ada berapa',
            'tiket yang saya buat', 'semua tiket saya',
            'daftar tiket', 'tiket saya ada', 'ada tiket', 'list tiket saya',
            // Procedural
            'cara buat tiket', 'cara bikin tiket', 'cara submit tiket',
            'cara lapor masalah', 'cara pengaduan', 'bagaimana membuat tiket',
            'how to create ticket', 'cara komplain', 'cara report masalah',
        ],

        // ── Tickets admin ──────────────────────────────────────────────────
        'ticket.list.all' => [
            'semua tiket masuk', 'tiket karyawan semua', 'antrian tiket',
            'list tiket admin', 'daftar tiket karyawan', 'tiket yang belum selesai',
            'tiket open', 'ada berapa tiket', 'total tiket',
            'semua tiket', 'tiket karyawan', 'list tiket admin',
        ],

        // ── Profile self ───────────────────────────────────────────────────
        'profile.info.self' => [
            'departemen saya apa', 'divisi saya apa', 'jabatan saya apa', 'posisi saya apa',
            'data profil saya', 'profil saya', 'atasan saya siapa', 'manager saya siapa',
            'tanggal masuk saya', 'hire date saya', 'tgl masuk saya',
            'departemen saya', 'divisi saya', 'jabatan saya', 'posisi saya',
            'data saya', 'atasan saya', 'my department', 'my position',
        ],

        // ── Subscription package features (tenant runtime) ───────────────
        'subscription.features.current' => [
            'paket saya apa', 'paket saya sekarang', 'paket saat ini apa',
            'fitur paket saya', 'fitur paket saat ini', 'fitur paket sekarang',
            'fitur langganan saya', 'fitur subscription saya', 'paket berlangganan saya',
            'saya berlangganan paket apa', 'saya berlangganan paket saat ini fiturnya apa aja',
            'fitur enterprise', 'fitur paket enterprise',
            'current package features', 'current subscription features',
            'my package features', 'what features are included in my package',
        ],

        // ── Employee admin ─────────────────────────────────────────────────
        'employee.list.company' => [
            'berapa jumlah karyawan', 'total karyawan berapa', 'berapa orang karyawan',
            'jumlah headcount', 'berapa pegawai', 'berapa staff', 'berapa tenaga kerja',
            'daftar karyawan aktif', 'siapa saja karyawan', 'list karyawan',
            'berapa karyawan', 'jumlah karyawan', 'headcount', 'employee list',
            'daftar karyawan',
        ],

        // ── Department ─────────────────────────────────────────────────────
        'department.info' => [
            'ada berapa departemen', 'ada berapa divisi', 'berapa departemen',
            'departemen apa saja', 'divisi apa saja', 'list departemen',
            'daftar divisi', 'departemen yang ada', 'divisi yang ada',
            'info departemen', 'department list',
        ],

        // ── Global Admin ───────────────────────────────────────────────────
        'saas.company.summary' => [
            'berapa company', 'jumlah company', 'company aktif', 'company berlangganan',
            'company trial', 'berapa tenant', 'jumlah tenant', 'tenant aktif',
            'berapa klien', 'jumlah klien', 'berapa pelanggan', 'jumlah pelanggan',
            'berapa organisasi', 'berapa perusahaan yang', 'berapa perusahaan sudah',
            'daftar company', 'list company', 'semua company', 'total company',
            'how many company', 'how many tenant', 'active companies',
        ],
        'saas.billing.summary' => [
            'total revenue', 'pendapatan bulan', 'invoice belum dibayar',
            'billing summary', 'total pendapatan', 'revenue bulan', 'pemasukan bulan',
            'invoice belum lunas', 'invoice outstanding', 'invoice unpaid',
            'berapa revenue', 'berapa pendapatan', 'berapa pemasukan',
            'pendapatan bersih', 'net revenue', 'net income',
            'subscription aktif', 'subscription trial', 'berapa langganan',
            'total tagihan', 'tagihan belum dibayar',
        ],
        'saas.tax.monthly' => [
            'pajak bulan ini', 'tax bulan ini', 'tax this month',
            'pajak yang dibayarkan bulan ini', 'pajak dibayar bulan ini',
            'berapa pajak yang kita bayarkan', 'berapa pajak yang dibayarkan',
            'tax paid this month', 'government tax this month',
            'pajak ke pemerintah', 'tax ke pemerintah',
            'ppn bulan ini', 'pajak platform bulan ini',
        ],
    ];

    /**
     * Classify a message. Returns 'unknown' if no pattern matches.
     */
    public function classify(string $message): string
    {
        $lower = mb_strtolower(trim($message));

        // Heuristic fallback for typo/colloquial company-admin leave questions.
        // Example: "siapa karywan yg pernah ajukan cuti di peridoe kmaren?"
        if (
            $this->containsAny($lower, ['cuti'])
            && $this->containsAny($lower, ['siapa', 'karyawan', 'karywan', 'pegawai'])
            && $this->containsAny($lower, ['ajukan', 'mengajukan', 'pengajuan', 'pernah', 'riwayat', 'histori'])
        ) {
            return 'leave.history.other';
        }

        foreach ($this->intentPatterns as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, mb_strtolower($keyword))) {
                    return $intent;
                }
            }
        }

        return 'unknown';
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
