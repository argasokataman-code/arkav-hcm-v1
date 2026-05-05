@php
    $variant = $variant ?? 'warning';
    $title = $title ?? 'Lifecycle payroll';
@endphp
<div class="alert alert-{{ $variant }} border mb-4" role="alert">
    <strong>{{ $title }}:</strong>
    Perubahan di payroll items dan salary components hanya berlaku untuk draft berikutnya atau draft yang dihitung ulang.
    Selama run belum <strong>paid / ditransfer</strong>, operator bisa klik <strong>Calculate Draft</strong> ulang di halaman
    <a href="{{ url('payroll-run') }}" class="alert-link fw-semibold">Payroll Run Bulanan</a> untuk refresh data terbaru.
    Jika run sudah <strong>paid / ditransfer</strong>, run tidak bisa dibatalkan agar rekonsiliasi tidak rancu.
</div>