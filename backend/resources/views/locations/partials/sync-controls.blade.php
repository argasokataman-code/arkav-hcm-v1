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

<div class="card border mb-3 d-none" data-wilayah-progress-panel>
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small fw-semibold" data-wilayah-progress-stage>Sync Status</span>
            <span class="small text-muted" data-wilayah-progress-percent>0%</span>
        </div>
        <div class="progress" role="progressbar" aria-label="Wilayah sync progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-wilayah-progress-root>
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%" data-wilayah-progress-bar></div>
        </div>
        <div class="small text-muted mt-1" data-wilayah-progress-message>Belum ada sync berjalan.</div>
    </div>
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
        var progressPanel = document.querySelector('[data-wilayah-progress-panel]');
        var progressRoot = document.querySelector('[data-wilayah-progress-root]');
        var progressBar = document.querySelector('[data-wilayah-progress-bar]');
        var progressPercent = document.querySelector('[data-wilayah-progress-percent]');
        var progressStage = document.querySelector('[data-wilayah-progress-stage]');
        var progressMessage = document.querySelector('[data-wilayah-progress-message]');
        var statusUrl = @json(route('locations.sync-status'));
        var pollingTimer = null;

        function titleCase(value) {
            var normalized = String(value || '').replace(/_/g, ' ').trim();
            if (!normalized) {
                return 'Sync Status';
            }

            return normalized.charAt(0).toUpperCase() + normalized.slice(1);
        }

        function applyProgress(status) {
            if (!status || !progressPanel || !progressBar || !progressPercent || !progressMessage || !progressRoot || !progressStage) {
                return;
            }

            var progress = Number(status.progress || 0);
            if (Number.isNaN(progress) || progress < 0) {
                progress = 0;
            }
            if (progress > 100) {
                progress = 100;
            }

            var shouldShow = Boolean(status.running) || progress > 0 || Boolean(status.error);
            if (shouldShow) {
                progressPanel.classList.remove('d-none');
            } else {
                progressPanel.classList.add('d-none');
            }

            progressBar.style.width = progress + '%';
            progressRoot.setAttribute('aria-valuenow', String(progress));
            progressPercent.textContent = progress + '%';

            var stageText = titleCase(status.stage);
            if (status.processed && status.total) {
                stageText += ' (' + status.processed + '/' + status.total + ')';
            }
            progressStage.textContent = stageText;

            var message = String(status.message || 'Sync status tidak tersedia.');
            if (status.error) {
                message += ' Error: ' + status.error;
            }
            progressMessage.textContent = message;

            if (!status.running && progress >= 100) {
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('progress-bar-striped');
                progressBar.classList.add('bg-success');
            } else {
                progressBar.classList.add('progress-bar-animated');
                progressBar.classList.add('progress-bar-striped');
                progressBar.classList.remove('bg-success');
            }
        }

        function fetchProgress() {
            return fetch(statusUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Status endpoint returned HTTP ' + response.status);
                    }

                    return response.json().catch(function () {
                        throw new Error('Status endpoint did not return valid JSON.');
                    });
                })
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        throw new Error('Status endpoint returned invalid payload.');
                    }

                    applyProgress(payload.data || {});

                    var running = Boolean(payload.data && payload.data.running);
                    if (button) {
                        button.disabled = running;
                    }

                    if (running && !pollingTimer) {
                        pollingTimer = window.setInterval(fetchProgress, 2000);
                    }

                    if (!running && pollingTimer) {
                        window.clearInterval(pollingTimer);
                        pollingTimer = null;
                    }
                })
                .catch(function (error) {
                    if (progressPanel) {
                        progressPanel.classList.remove('d-none');
                    }

                    if (progressMessage) {
                        progressMessage.textContent = 'Tidak dapat membaca progress sync. ' + (error && error.message ? error.message : 'Unknown error.');
                    }

                    if (progressStage) {
                        progressStage.textContent = 'Status endpoint error';
                    }

                    if (progressPercent) {
                        progressPercent.textContent = '-';
                    }

                    if (button) {
                        button.disabled = false;
                    }

                    if (pollingTimer) {
                        window.clearInterval(pollingTimer);
                        pollingTimer = null;
                    }
                });
        }

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

            window.setTimeout(fetchProgress, 500);
            if (!pollingTimer) {
                pollingTimer = window.setInterval(fetchProgress, 2000);
            }
        });

        fetchProgress();
    })();
</script>
