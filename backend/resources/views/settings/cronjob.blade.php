<?php $page = 'cronjob'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Cronjob</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">Cronjob</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons ms-2">
                <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ url('custom-css') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Custom CSS</a>
                            <a href="{{ url('custom-js') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Custom JS</a>
                            <a href="{{ url('cronjob') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Cronjob</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header px-0 mx-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6 col-sm-4">
                                <h4>Cronjob Scheduler Configuration</h4>
                            </div>
                            <div class="col-md-6 col-sm-8 text-sm-end">
                                <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="collapse" data-bs-target="#cronjobGuide" aria-expanded="false" aria-controls="cronjobGuide">
                                    <i class="ti ti-info-circle me-1"></i>Panduan
                                </button>
                                <a href="{{ url('cronjob-schedule') }}" class="btn btn-dark"><i class="ti ti-clock-hour-4 me-2"></i>Cron Schedule</a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="collapse mb-3" id="cronjobGuide">
                            <div class="alert alert-info mb-0">
                                <div class="fw-semibold mb-1">Panduan Penggunaan Cronjob</div>
                                <ul class="mb-0 ps-3">
                                    <li>Halaman ini khusus global super admin (type-1) untuk mengatur jadwal automation lintas modul.</li>
                                    <li>Kolom <strong>Type</strong> menunjukkan frekuensi scheduler (<em>daily</em> = dievaluasi setiap hari, <em>monthly</em> = dievaluasi tiap bulan di hari yang dipilih).</li>
                                    <li>Kolom <strong>Panduan &amp; Tujuan</strong> menjelaskan detail maksud bisnis tiap cronjob: apa yang dicek, kenapa dijalankan, dan outcome yang diharapkan.</li>
                                    <li>Jika ada peringatan runtime override, artinya job tetap di-skip walau status Enabled aktif.</li>
                                </ul>
                            </div>
                        </div>

                        @if (session('cronjobStatus'))
                            <div class="alert alert-{{ session('cronjobStatus.type') === 'success' ? 'success' : 'danger' }} mb-3">
                                {{ session('cronjobStatus.message') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger mb-3" role="alert">
                                <strong>Validation failed.</strong> Please correct the highlighted fields and try again.
                            </div>
                        @endif

                        <div data-cronjob-loading-state class="mb-3">
                            <div class="placeholder-glow mb-2">
                                <span class="placeholder col-3"></span>
                            </div>
                            <div class="placeholder-glow mb-2">
                                <span class="placeholder col-12"></span>
                            </div>
                            <div class="placeholder-glow mb-2">
                                <span class="placeholder col-11"></span>
                            </div>
                            <div class="placeholder-glow">
                                <span class="placeholder col-10"></span>
                            </div>
                        </div>

                        <div data-cronjob-form-state class="d-none">
                        <form method="POST" action="{{ route('cronjob.update') }}" data-cronjob-form>
                            @csrf

                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Enabled</th>
                                            <th>Job</th>
                                            <th>Panduan &amp; Tujuan</th>
                                            <th>Type</th>
                                            <th>Time</th>
                                            <th>Day</th>
                                            <th>Timezone</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cronjobs as $key => $job)
                                            @php($config = $job['config'])
                                            @php($runtimeFlag = $runtimeFlagStates[$key] ?? null)
                                            <tr class="{{ is_array($runtimeFlag) && (($runtimeFlag['enabled'] ?? true) !== true) ? 'table-warning' : '' }}">
                                                <td>
                                                    <div class="form-check form-switch">
                                                        @php($enabledField = "jobs.$key.enabled")
                                                        @php($enabledValue = old($enabledField))
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="enabled_{{ $key }}"
                                                            name="jobs[{{ $key }}][enabled]"
                                                            value="1"
                                                            {{ $enabledValue !== null ? ((string) $enabledValue === '1' ? 'checked' : '') : (!empty($config['enabled']) ? 'checked' : '') }}
                                                        >
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="fw-medium mb-1">{{ $job['label'] }}</h6>
                                                    <small class="text-muted">{{ $job['description'] }}</small>
                                                    @if (is_array($runtimeFlag) && (($runtimeFlag['enabled'] ?? true) !== true))
                                                        <div class="small text-warning mt-1">
                                                            Runtime override aktif: <code>{{ $runtimeFlag['flag'] }}</code> = false, jadi scheduler akan di-skip walau row ini enabled.
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="small mb-1"><strong>Frekuensi:</strong> {{ $job['frequencyExplanation'] ?? '-' }}</div>
                                                    <div class="small mb-1"><strong>Tujuan:</strong> {{ $job['businessPurpose'] ?? '-' }}</div>
                                                    <div class="small text-muted"><strong>Output:</strong> {{ $job['expectedOutcome'] ?? '-' }}</div>
                                                </td>
                                                <td class="text-capitalize">{{ $job['scheduleType'] }}</td>
                                                <td>
                                                    @php($timeField = "jobs.$key.time")
                                                    <input
                                                        type="time"
                                                        class="form-control @error($timeField) is-invalid @enderror"
                                                        name="jobs[{{ $key }}][time]"
                                                        value="{{ old($timeField, $config['time'] ?? '00:00') }}"
                                                    >
                                                    @error($timeField)
                                                        <span class="invalid-feedback d-block" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    @if (($job['scheduleType'] ?? 'daily') === 'monthly')
                                                        @php($dayField = "jobs.$key.dayOfMonth")
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            max="28"
                                                            class="form-control @error($dayField) is-invalid @enderror"
                                                            name="jobs[{{ $key }}][dayOfMonth]"
                                                            value="{{ old($dayField, $config['dayOfMonth'] ?? 1) }}"
                                                        >
                                                        @error($dayField)
                                                            <span class="invalid-feedback d-block" role="alert">
                                                                <strong>{{ $message }}</strong>
                                                            </span>
                                                        @enderror
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php($timezoneField = "jobs.$key.timezone")
                                                    <select class="form-select @error($timezoneField) is-invalid @enderror" name="jobs[{{ $key }}][timezone]">
                                                        @foreach ($availableTimezones as $timezone)
                                                            <option value="{{ $timezone }}" {{ old($timezoneField, $config['timezone'] ?? 'Asia/Jakarta') === $timezone ? 'selected' : '' }}>
                                                                {{ $timezone }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error($timezoneField)
                                                        <span class="invalid-feedback d-block" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" data-cronjob-submit>
                                    <span data-cronjob-submit-label>Save Configuration</span>
                                    <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true" data-cronjob-submit-spinner></span>
                                </button>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<noscript>
    <style>
        [data-cronjob-loading-state] { display: none !important; }
        [data-cronjob-form-state] { display: block !important; }
    </style>
</noscript>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var loadingState = document.querySelector('[data-cronjob-loading-state]');
        var formState = document.querySelector('[data-cronjob-form-state]');
        if (loadingState) {
            loadingState.classList.add('d-none');
        }
        if (formState) {
            formState.classList.remove('d-none');
        }

        var form = document.querySelector('[data-cronjob-form]');
        var submitButton = document.querySelector('[data-cronjob-submit]');
        var submitLabel = document.querySelector('[data-cronjob-submit-label]');
        var submitSpinner = document.querySelector('[data-cronjob-submit-spinner]');

        if (form && submitButton && submitLabel && submitSpinner) {
            form.addEventListener('submit', function () {
                submitButton.setAttribute('disabled', 'disabled');
                submitLabel.textContent = 'Saving...';
                submitSpinner.classList.remove('d-none');
            });
        }
    });
</script>
@endsection
