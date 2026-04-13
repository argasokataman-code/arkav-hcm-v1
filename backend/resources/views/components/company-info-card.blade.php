{{-- Company Info Card Component --}}
{{-- Usage: @include('components.company-info-card') or use data-company-card-auto-load on element --}}

<div id="company-card-container" class="mb-4">
    <div class="spinner-border spinner-border-sm" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <span class="ms-2">Loading company information...</span>
</div>

@push('scripts')
<script src="{{ url('build/js/company-info.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.CompanyInfo?.loadCompanyCard('#company-card-container');
    });
</script>
@endpush
