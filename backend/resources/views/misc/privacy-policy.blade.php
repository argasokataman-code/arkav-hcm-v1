<?php
$page = 'privacy-policy';
$dpoName = (string) config('pdp.dpo_name', 'Tim Data Protection Arkav HCM');
$dpoEmail = (string) config('pdp.dpo_email', 'dpo@arcav.id');
$privacyContactUrl = (string) config('pdp.privacy_contact_url', url('/privacy-policy'));
?>
@extends('layout.guest-legal')
@section('title', 'Kebijakan Privasi')
@section('content')

<div class="legal-badge">Dokumen Legal</div>
<h1>Kebijakan Privasi</h1>
<p class="lead">Dokumen ini menjelaskan dasar hukum, tujuan pemrosesan, hak subjek data, dan kontak resmi perlindungan data Arkav HCM sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP).</p>
<p class="meta">Berlaku efektif: 1 Mei 2026 &mdash; Terakhir diperbarui: 1 Mei 2026</p>

<div class="section">
	<h2>1. Identitas Pengendali Data Pribadi</h2>
	<ul>
		<li><strong>Nama Pengendali:</strong> Arkav HCM</li>
		<li><strong>Kontak DPO:</strong> <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></li>
	</ul>
</div>

<div class="section">
	<h2>2. Data Pribadi yang Kami Kumpulkan</h2>

	<h3>a. Data Pribadi Umum</h3>
	<ul>
		<li>Nama lengkap, alamat email, nomor telepon, alamat domisili</li>
		<li>Data ketenagakerjaan: jabatan, departemen, tanggal bergabung, status kontrak</li>
		<li>Data log akses: alamat IP, user-agent browser, waktu akses</li>
		<li>Data absensi: waktu masuk/keluar, lokasi GPS (dengan persetujuan)</li>
	</ul>

	<h3>b. Data Pribadi Spesifik</h3>
	<ul>
		<li><strong>NIK/KTP</strong> — untuk verifikasi identitas karyawan</li>
		<li><strong>NPWP, status pajak (PTKP)</strong> — untuk keperluan pemotongan pajak penghasilan (PPh 21)</li>
		<li><strong>Data rekening bank</strong> (nama bank, nomor rekening) — untuk transfer gaji</li>
		<li><strong>Nomor BPJS Kesehatan & Ketenagakerjaan</strong> — untuk administrasi jaminan sosial</li>
		<li><strong>Data biometrik (foto selfie)</strong> — opsional, hanya dengan persetujuan eksplisit</li>
		<li><strong>Data lokasi GPS</strong> — opsional, hanya dengan persetujuan eksplisit</li>
		<li><strong>Agama, status perkawinan, kewarganegaraan</strong> — untuk penghitungan tunjangan/pajak</li>
		<li><strong>Data gaji dan kompensasi</strong> — untuk pemrosesan payroll</li>
	</ul>
</div>

<div class="section">
	<h2>3. Dasar dan Tujuan Pemrosesan</h2>
	<div class="table-wrap">
		<table>
			<thead>
				<tr><th>Tujuan Pemrosesan</th><th>Dasar Hukum</th></tr>
			</thead>
			<tbody>
				<tr><td>Manajemen data karyawan</td><td>Pelaksanaan kontrak kerja (Pasal 20 b)</td></tr>
				<tr><td>Penggajian & pemotongan PPh 21</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
				<tr><td>Administrasi BPJS</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
				<tr><td>Absensi berbasis foto selfie & GPS</td><td>Persetujuan eksplisit (Pasal 20 a)</td></tr>
				<tr><td>Keamanan platform</td><td>Kepentingan sah (Pasal 20 f)</td></tr>
				<tr><td>Onboarding akun perusahaan</td><td>Persetujuan (Pasal 20 a)</td></tr>
			</tbody>
		</table>
	</div>
</div>

<div class="section">
	<h2>4. Pengungkapan kepada Pihak Ketiga</h2>
	<div class="table-wrap">
		<table>
			<thead>
				<tr><th>Pihak Ketiga</th><th>Data Dibagikan</th><th>Tujuan</th><th>Lokasi Server</th></tr>
			</thead>
			<tbody>
				<tr><td>Midtrans</td><td>Nama, email, jumlah tagihan</td><td>Pembayaran langganan</td><td>Indonesia</td></tr>
				<tr><td>Penyedia AI (kompatibel OpenAI)</td><td>Pesan teks intent (tanpa PII langsung)</td><td>Asisten AI HCM</td><td>Bervariasi *</td></tr>
				<tr><td>Cloudflare Turnstile</td><td>Token captcha</td><td>Pencegahan bot</td><td>Amerika Serikat *</td></tr>
			</tbody>
		</table>
	</div>
	<p class="footnote">* Transfer internasional dilakukan berdasarkan pelaksanaan kontrak layanan (Pasal 49 huruf b UU PDP) dan dilindungi dengan kontrol kontraktual serta teknis sesuai Pasal 56 UU PDP.</p>
</div>

<div class="section">
	<h2>5. Retensi Data</h2>
	<ul>
		<li>Data karyawan aktif: selama hubungan kerja berlangsung</li>
		<li>Data karyawan yang telah berhenti: maksimal <strong>5 tahun</strong></li>
		<li>Data payroll & perpajakan: <strong>10 tahun</strong> (sesuai ketentuan perpajakan)</li>
		<li>Log akses keamanan: maksimal <strong>1 tahun</strong></li>
	</ul>
</div>

<div class="section">
	<h2>6. Hak Subjek Data Pribadi</h2>
	<div class="table-wrap">
		<table>
			<thead>
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
</div>

<div class="section">
	<h2>7. Keamanan Data</h2>
	<ul>
		<li>Enkripsi HTTPS/TLS untuk semua transmisi data</li>
		<li>Hashing kata sandi menggunakan Bcrypt</li>
		<li>Kontrol akses berbasis peran (RBAC) per tenant</li>
		<li>Audit log untuk ekspor data massal</li>
	</ul>
</div>

<div class="section">
	<h2>8. Notifikasi Insiden Data</h2>
	<p>Dalam hal terjadi insiden keamanan data, kami berkomitmen memberikan notifikasi kepada pihak berwenang dan subjek data terdampak dalam waktu <strong>3 × 24 jam</strong> setelah insiden terdeteksi, sesuai Pasal 46 UU PDP.</p>
</div>

<div class="section">
	<h2>9. Hubungi Kami (DPO)</h2>
	<ul>
		<li><strong>Email:</strong> <a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></li>
		<li><strong>Halaman privasi:</strong> <a href="{{ $privacyContactUrl }}">{{ $privacyContactUrl }}</a></li>
		<li>Respons dalam 3 hari kerja</li>
	</ul>
</div>

@endsection
