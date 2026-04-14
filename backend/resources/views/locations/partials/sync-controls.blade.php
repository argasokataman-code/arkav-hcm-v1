@php($syncStatus = session('wilayahSyncStatus'))
@if (is_array($syncStatus))
    <div class="alert alert-{{ $syncStatus['type'] ?? 'info' }} alert-dismissible fade show" role="alert">
        <div class="fw-semibold">{{ $syncStatus['message'] ?? 'Sync status' }}</div>
        @if (!empty($syncStatus['output']))
            <div class="small mt-1">{{ $syncStatus['output'] }}</div>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
<div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
    <form method="POST" action="{{ route('locations.sync') }}" class="mb-2" data-wilayah-sync-form>
        @csrf
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center" data-wilayah-sync-button>
            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true" data-wilayah-sync-spinner></span>
            <i class="ti ti-refresh-dot me-1" data-wilayah-sync-icon></i>
            <span data-wilayah-sync-text>Sync Data Wilayah</span>
        </button>
        <div class="small text-muted mt-1 d-none" data-wilayah-sync-hint>
            Sync sedang diproses di background, mohon tunggu lalu refresh halaman.
        </div>
    </form>
    <div class="mb-2 text-muted small">{{ $pageSubtitle }}</div>
</div>

<script>
    (function () {
        var form = document.querySelector('[data-wilayah-sync-form]');
        if (!form) {
            return;
        }

        var button = form.querySelector('[data-wilayah-sync-button]');
        var spinner = form.querySelector('[data-wilayah-sync-spinner]');
        var icon = form.querySelector('[data-wilayah-sync-icon]');
        var text = form.querySelector('[data-wilayah-sync-text]');
        var hint = form.querySelector('[data-wilayah-sync-hint]');

        form.addEventListener('submit', function () {
            if (button) {
                button.disabled = true;
            }
            if (spinner) {
                spinner.classList.remove('d-none');
            }
            if (icon) {
                icon.classList.add('d-none');
            }
            if (text) {
                text.textContent = 'Memulai Sync...';
            }
            if (hint) {
                hint.classList.remove('d-none');
            }
        });
    })();
</script>
