{{--
  Tab navigasi antar section payroll (HCM admin).
  @param string $payrollTab additions|overtime|deductions|thr
  @param bool $thrOnlyPayrollSectionTabs jika true (halaman THR): hanya tampilkan tombol THR, tanpa Additions/Overtime/Deductions
--}}
@php
    $payrollTab = $payrollTab ?? 'additions';
@endphp
<div class="payroll-btns">
    <a href="{{ url('payroll') }}" class="btn btn-white border me-2 {{ $payrollTab === 'additions' ? 'active' : '' }}">Additions</a>
    <a href="{{ url('payroll-overtime') }}" class="btn btn-white border me-2 {{ $payrollTab === 'overtime' ? 'active' : '' }}">Overtime</a>
    <a href="{{ url('payroll-deduction') }}" class="btn btn-white border me-2 {{ $payrollTab === 'deductions' ? 'active' : '' }}">Deductions</a>
</div>
