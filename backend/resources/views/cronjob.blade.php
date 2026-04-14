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
                            <a href="{{ url('storage-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Storage</a>
                            <a href="{{ url('ban-ip-address') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Ban IP Address</a>
                            <a href="{{ url('backup') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Backup</a>
                            <a href="{{ url('clear-cache') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Clear Cache</a>
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
                                <a href="{{ url('cronjob-schedule') }}" class="btn btn-dark"><i class="ti ti-clock-hour-4 me-2"></i>Cron Schedule</a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        @if (session('cronjobStatus'))
                            <div class="alert alert-{{ session('cronjobStatus.type') === 'success' ? 'success' : 'danger' }} mb-3">
                                {{ session('cronjobStatus.message') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('cronjob.update') }}">
                            @csrf

                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Enabled</th>
                                            <th>Job</th>
                                            <th>Type</th>
                                            <th>Time</th>
                                            <th>Day</th>
                                            <th>Timezone</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cronjobs as $key => $job)
                                            @php($config = $job['config'])
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            id="enabled_{{ $key }}"
                                                            name="jobs[{{ $key }}][enabled]"
                                                            value="1"
                                                            {{ !empty($config['enabled']) ? 'checked' : '' }}
                                                        >
                                                    </div>
                                                </td>
                                                <td>
                                                    <h6 class="fw-medium mb-1">{{ $job['label'] }}</h6>
                                                    <small class="text-muted">{{ $job['description'] }}</small>
                                                </td>
                                                <td class="text-capitalize">{{ $job['scheduleType'] }}</td>
                                                <td>
                                                    <input
                                                        type="time"
                                                        class="form-control"
                                                        name="jobs[{{ $key }}][time]"
                                                        value="{{ $config['time'] ?? '00:00' }}"
                                                    >
                                                </td>
                                                <td>
                                                    @if (($job['scheduleType'] ?? 'daily') === 'monthly')
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            max="28"
                                                            class="form-control"
                                                            name="jobs[{{ $key }}][dayOfMonth]"
                                                            value="{{ $config['dayOfMonth'] ?? 1 }}"
                                                        >
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <select class="form-select" name="jobs[{{ $key }}][timezone]">
                                                        @foreach ($availableTimezones as $timezone)
                                                            <option value="{{ $timezone }}" {{ ($config['timezone'] ?? 'Asia/Jakarta') === $timezone ? 'selected' : '' }}>
                                                                {{ $timezone }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary">Save Configuration</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
