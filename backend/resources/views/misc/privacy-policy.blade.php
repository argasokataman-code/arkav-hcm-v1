<?php
$page = 'privacy-policy';
$dpoName = (string) config('pdp.dpo_name', 'Tim Data Protection ARCAV HCM');
$dpoEmail = (string) config('pdp.dpo_email', 'dpo@arcav.id');
$privacyContactUrl = (string) config('pdp.privacy_contact_url', url('/privacy-policy'));
?>
@extends('layout.guest-legal')
@section('title', 'Kebijakan Privasi')
@section('content')

                <div class="mb-4">
                    <span class="badge badge-soft-primary mb-3">Dokumen Legal</span>
                    <h1 class="mb-2">Kebijakan Privasi</h1>
                    <p class="text-muted mb-0">Dokumen ini menjelaskan dasar hukum, tujuan pemrosesan, hak subjek data, dan kontak resmi perlindungan data ARCAV HCM.</p>
                </div>

                <p class="text-muted small">Berlaku efektif: 1 Mei 2026 &mdash; Terakhir diperbarui: 1 Mei 2026</p>

                <p>
                    ARCAV HCM ("kami", "Layanan") berkomitmen melindungi data pribadi Anda sesuai dengan
                    <strong>Undang-Undang Nomor 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)</strong>
                    Republik Indonesia. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan,
                    menyimpan, mengungkap, dan melindungi data pribadi Anda.
                </p>

                <h5 class="mt-4 mb-2">1. Identitas Pengendali Data Pribadi</h5>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1">
                        <i class="ti ti-point-filled fs-10 me-1"></i>
                        <span><strong>Nama Pengendali:</strong> ARCAV HCM</span>

                        <i class="ti ti-point-filled fs-10 me-1"></i>
                        <span><strong>Kontak DPO:</strong>
                            <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></span>
                    </li>
                </ul>

                <h5 class="mt-4 mb-2">2. Data Pribadi yang Kami Kumpulkan</h5>
                <h6 class="fw-semibold mb-1">a. Data Pribadi Umum</h6>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Nama lengkap, alamat email, nomor telepon, alamat domisili</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data ketenagakerjaan: jabatan, departemen, tanggal bergabung, status kontrak</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data log akses: alamat IP, user-agent browser, waktu akses</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data absensi: waktu masuk/keluar, lokasi GPS (dengan persetujuan)</li>
                </ul>
                <h6 class="fw-semibold mb-1">b. Data Pribadi Spesifik (Pasal 4 ayat 2 UU PDP)</h6>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>NIK/KTP</strong> — untuk verifikasi identitas karyawan</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>NPWP, status pajak (PTKP)</strong> — untuk keperluan pemotongan pajak penghasilan (PPh 21)</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Data rekening bank</strong> (nama bank, nomor rekening) — untuk transfer gaji</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Nomor BPJS Kesehatan &amp; Ketenagakerjaan</strong> — untuk administrasi jaminan sosial</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Data biometrik (foto selfie)</strong> — opsional, hanya dengan persetujuan eksplisit karyawan</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Data lokasi GPS</strong> — opsional, hanya dengan persetujuan eksplisit karyawan</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Agama, status perkawinan, kewarganegaraan</strong> — untuk keperluan penghitungan tunjangan/pajak</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Data gaji dan kompensasi</strong> — untuk pemrosesan payroll</li>
                </ul>

                <h5 class="mt-4 mb-2">3. Dasar dan Tujuan Pemrosesan (Pasal 20–21 UU PDP)</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr><th>Tujuan Pemrosesan</th><th>Dasar Hukum</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Manajemen data karyawan</td><td>Pelaksanaan kontrak kerja (Pasal 20 b)</td></tr>
                            <tr><td>Penggajian &amp; pemotongan PPh 21</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
                            <tr><td>Administrasi BPJS</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
                            <tr><td>Absensi berbasis foto selfie &amp; GPS</td><td>Persetujuan eksplisit (Pasal 20 a)</td></tr>
                            <tr><td>Keamanan platform</td><td>Kepentingan sah (Pasal 20 f)</td></tr>
                            <tr><td>Onboarding akun perusahaan</td><td>Persetujuan (Pasal 20 a)</td></tr>
                        </tbody>
                    </table>
                </div>

                <h5 class="mt-4 mb-2">4. Pengungkapan kepada Pihak Ketiga</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr><th>Pihak Ketiga</th><th>Data Dibagikan</th><th>Tujuan</th><th>Lokasi Server</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Midtrans</td><td>Nama, email, jumlah tagihan</td><td>Pembayaran langganan</td><td>Indonesia</td></tr>
                            <tr><td>Penyedia AI (kompatibel OpenAI)</td><td>Pesan teks intent (tanpa PII langsung)</td><td>Asisten AI HCM</td><td>Bervariasi *</td></tr>
                            <tr><td>Cloudflare Turnstile</td><td>Token captcha</td><td>Pencegahan bot</td><td>Amerika Serikat *</td></tr>
                        </tbody>
                    </table>
                    <p class="small text-muted">* Transfer internasional dilakukan berdasarkan pelaksanaan kontrak layanan (Pasal 49 huruf b UU PDP) dan dilindungi dengan kontrol kontraktual serta teknis sesuai Pasal 56 UU PDP.</p>
                </div>

                <h5 class="mt-4 mb-2">5. Retensi Data</h5>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data karyawan aktif: selama hubungan kerja berlangsung</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data karyawan yang telah berhenti: maksimal <strong>5 tahun</strong></li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Data payroll &amp; perpajakan: <strong>10 tahun</strong></li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Log akses keamanan: maksimal <strong>1 tahun</strong></li>
                </ul>

                <h5 class="mt-4 mb-2">6. Hak Subjek Data Pribadi (Pasal 5–13 UU PDP)</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr><th>Hak</th><th>Cara Menggunakan</th><th>Batas Waktu</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Hak Akses</strong></td><td>Email ke <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></td><td>14 hari kerja</td></tr>
                            <tr><td><strong>Hak Perbaikan</strong></td><td>Menu profil di aplikasi atau email DPO</td><td>14 hari kerja</td></tr>
                            <tr><td><strong>Hak Penghapusan</strong></td><td>Menu Privasi di aplikasi atau email DPO</td><td>30 hari kerja</td></tr>
                            <tr><td><strong>Hak Pembatasan Pemrosesan</strong></td><td>Email ke DPO</td><td>14 hari kerja</td></tr>
                            <tr><td><strong>Hak Portabilitas Data</strong></td><td>Email ke DPO</td><td>14 hari kerja</td></tr>
                            <tr><td><strong>Hak Mencabut Persetujuan</strong></td><td>Pengaturan di aplikasi atau email DPO</td><td>Segera</td></tr>
                        </tbody>
                    </table>
                </div>

                <h5 class="mt-4 mb-2">7. Keamanan Data</h5>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Enkripsi HTTPS/TLS untuk semua transmisi data</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Hashing kata sandi menggunakan Bcrypt</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Kontrol akses berbasis peran (RBAC) per tenant</li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Audit log untuk ekspor data massal</li>
                </ul>

                <h5 class="mt-4 mb-2">8. Notifikasi Insiden Data (Pasal 46 UU PDP)</h5>
                <p>
                    Dalam hal terjadi insiden keamanan data, kami berkomitmen memberikan notifikasi kepada
                    pihak berwenang dan subjek data terdampak dalam waktu <strong>3 × 24 jam</strong>
                    setelah insiden terdeteksi.
                </p>

                <h5 class="mt-4 mb-2">9. Hubungi Kami (DPO)</h5>
                <ul class="list-styled mb-3">
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Email:</strong>&nbsp;<a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        <strong>Halaman privasi:</strong>&nbsp;<a href="{{ $privacyContactUrl }}">{{ $privacyContactUrl }}</a></li>
                    <li class="d-flex align-items-baseline mb-1"><i class="ti ti-point-filled fs-10 me-1"></i>
                        Respons dalam 3 hari kerja</li>
                </ul>

            </div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

@endsection
