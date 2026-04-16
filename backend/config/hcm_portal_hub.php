<?php

/**
 * Peta halaman HCM untuk layar /pages — tautan nyata ke route web yang terdaftar.
 * Bukan konten CMS; hanya indeks navigasi produk. Update bila route baru ditambahkan.
 */
return [
    'page_title' => 'Peta halaman HCM',
    'page_subtitle' => 'Indeks cepat ke modul Arcav HCM yang terhubung API. Data per baris berasal dari konfigurasi aplikasi, bukan database konten.',
    'sections' => [
        [
            'title' => 'Awal & dasbor',
            'icon' => 'ti-smart-home',
            'items' => [
                ['label' => 'Dasbor admin', 'route' => 'index', 'description' => 'Ringkasan & chart (sesuai peran).'],
                ['label' => 'Dasbor karyawan', 'route' => 'employee-dashboard', 'description' => 'Self-service karyawan.'],
            ],
        ],
        [
            'title' => 'Karyawan & organisasi',
            'icon' => 'ti-users',
            'items' => [
                ['label' => 'Daftar karyawan', 'route' => 'employees', 'description' => 'Directory & CRUD (admin).'],
                ['label' => 'Karyawan (grid)', 'route' => 'employees-grid', 'description' => 'Tampilan grid.'],
                ['label' => 'Departemen', 'route' => 'departments', 'description' => 'Master departemen.'],
                ['label' => 'Jabatan', 'route' => 'designations', 'description' => 'Master jabatan.'],
                ['label' => 'Kebijakan', 'route' => 'policy', 'description' => 'Master policy.'],
                ['label' => 'Laporan karyawan', 'route' => 'employee-report', 'description' => 'Agregasi daftar (admin).'],
            ],
        ],
        [
            'title' => 'Cuti & kalender',
            'icon' => 'ti-calendar-event',
            'items' => [
                ['label' => 'Libur nasional', 'route' => 'holidays', 'description' => 'Master hari libur.'],
                ['label' => 'Cuti (admin)', 'route' => 'leaves', 'description' => 'Persetujuan & manajemen.'],
                ['label' => 'Cuti (karyawan)', 'route' => 'leaves-employee', 'description' => 'Pengajuan self.'],
                ['label' => 'Pengaturan cuti', 'route' => 'leave-settings', 'description' => 'Kuota & aturan.'],
                ['label' => 'Tipe cuti', 'route' => 'leave-type', 'description' => 'Katalog tipe (admin).'],
                ['label' => 'Laporan cuti', 'route' => 'leave-report', 'description' => 'Placeholder laporan.'],
            ],
        ],
        [
            'title' => 'Absensi & waktu',
            'icon' => 'ti-clock',
            'items' => [
                ['label' => 'Absensi admin', 'route' => 'attendance-admin', 'description' => 'Monitoring & koreksi.'],
                ['label' => 'Absensi karyawan', 'route' => 'attendance-employee', 'description' => 'Punch & riwayat self.'],
                ['label' => 'Timesheet', 'route' => 'timesheets', 'description' => 'Rekap jam.'],
                ['label' => 'Jadwal per user', 'route' => 'schedule-timing', 'description' => 'Penjadwalan.'],
                ['label' => 'Master shift', 'route' => 'shift-master', 'description' => 'Shift.'],
                ['label' => 'Master lembur', 'route' => 'overtime-master', 'description' => 'Tipe lembur.'],
                ['label' => 'Lembur (admin)', 'route' => 'overtime', 'description' => 'Persetujuan lembur.'],
                ['label' => 'Lembur (karyawan)', 'route' => 'overtime-employee', 'description' => 'Pengajuan self.'],
                ['label' => 'Laporan absensi', 'route' => 'attendance-report', 'description' => 'Laporan.'],
            ],
        ],
        [
            'title' => 'Payroll & kompensasi',
            'icon' => 'ti-currency-dollar',
            'items' => [
                ['label' => 'Payroll items', 'route' => 'payroll', 'description' => 'Katalog komponen gaji.'],
                ['label' => 'Payroll lembur', 'route' => 'payroll-overtime', 'description' => 'Alokasi lembur ke payroll.'],
                ['label' => 'Payroll potongan', 'route' => 'payroll-deduction', 'description' => 'Item potongan.'],
                ['label' => 'Run bulanan', 'route' => 'payroll-run', 'description' => 'Periode aktif & finalisasi.'],
                ['label' => 'Histori run', 'route' => 'payroll-run-history', 'description' => 'Audit run.'],
                ['label' => 'THR', 'route' => 'payroll-thr', 'description' => 'Batch & slip THR.'],
                ['label' => 'Kompensasi PKWT', 'route' => 'payroll-pkwt-compensation', 'description' => 'Alur PKWT.'],
                ['label' => 'Gaji per karyawan', 'route' => 'employee-salary', 'description' => 'Assignment & kompensasi.'],
                ['label' => 'Slip gaji (self)', 'route' => 'payslip', 'description' => 'Slip final bulanan.'],
                ['label' => 'Laporan slip (admin)', 'route' => 'payslip-report', 'description' => 'Agregasi slip.'],
            ],
        ],
        [
            'title' => 'SDM lanjutan',
            'icon' => 'ti-briefcase',
            'items' => [
                ['label' => 'Promosi', 'route' => 'promotion', 'description' => 'Riwayat promosi.'],
                ['label' => 'Resignasi', 'route' => 'resignation', 'description' => 'Pengajuan & daftar.'],
                ['label' => 'PHK / terminasi', 'route' => 'termination', 'description' => 'Manajemen terminasi.'],
            ],
        ],
        [
            'title' => 'Kinerja, goal, pelatihan',
            'icon' => 'ti-chart-line',
            'items' => [
                ['label' => 'Performance indicator', 'route' => 'performance-indicator', 'description' => 'Master indikator.'],
                ['label' => 'Performance appraisal', 'route' => 'performance-appraisal', 'description' => 'Siklus review.'],
                ['label' => 'Performance review', 'route' => 'performance-review', 'description' => 'Form review.'],
                ['label' => 'Goal type', 'route' => 'goal-type', 'description' => 'Master tipe goal.'],
                ['label' => 'Goal tracking', 'route' => 'goal-tracking', 'description' => 'Target individu/tim.'],
                ['label' => 'Pelatihan', 'route' => 'training', 'description' => 'Jadwal pelatihan.'],
                ['label' => 'Tipe pelatihan', 'route' => 'training-type', 'description' => 'Katalog.'],
                ['label' => 'Trainer', 'route' => 'trainers', 'description' => 'Master trainer.'],
            ],
        ],
        [
            'title' => 'Tiket & dukungan',
            'icon' => 'ti-ticket',
            'items' => [
                ['label' => 'Tiket (admin)', 'route' => 'tickets-admin', 'description' => 'Antrian & SLA.'],
                ['label' => 'Tiket (karyawan)', 'route' => 'tickets-employee', 'description' => 'Tiket self.'],
                ['label' => 'Grid tiket', 'route' => 'tickets-grid', 'description' => 'Tampilan grid.'],
                ['label' => 'Master kategori', 'route' => 'ticket-master', 'description' => 'Kategori tiket.'],
            ],
        ],
        [
            'title' => 'SaaS & pengaturan',
            'icon' => 'ti-building',
            'items' => [
                ['label' => 'Perusahaan (tenant)', 'route' => 'companies', 'description' => 'Daftar company.'],
                ['label' => 'Paket SaaS', 'route' => 'saas.packages', 'description' => 'Tier & fitur.'],
                ['label' => 'Langganan', 'route' => 'saas.subscriptions', 'description' => 'Lifecycle subscription.'],
                ['label' => 'Domain', 'route' => 'saas.domains', 'description' => 'Custom domain.'],
                ['label' => 'Transaksi pembelian', 'route' => 'saas.transactions', 'description' => 'Purchase tx.'],
                ['label' => 'Invoice SaaS', 'route' => 'saas.invoices', 'description' => 'Penagihan.'],
                ['label' => 'Pembayaran SaaS', 'route' => 'saas.payments', 'description' => 'Pembayaran.'],
                ['label' => 'Laporan SaaS', 'route' => 'saas.reports', 'description' => 'KPI keuangan.'],
                ['label' => 'Pengingat', 'route' => 'saas.reminders', 'description' => 'Reminder tagihan.'],
                ['label' => 'Pengaturan lokalisasi', 'route' => 'localization-settings', 'description' => 'Bahasa & format.'],
                ['label' => 'Cronjob', 'route' => 'cronjob', 'description' => 'Jadwal scheduler.'],
            ],
        ],
    ],
];
