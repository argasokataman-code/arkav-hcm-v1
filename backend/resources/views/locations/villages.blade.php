<?php $page = 'villages'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">{{ $pageTitle }}</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                            <li class="breadcrumb-item">Locations</li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                        </ol>
                    </nav>
                </div>
                @include('locations.partials.sync-controls')
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">{{ $pageTitle }} List</h5>
                        <p class="text-muted mb-0">Total {{ $totalLabel }}: {{ number_format((int) ($totalCount ?? 0)) }}</p>
                    </div>
                    <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="search" name="q" class="form-control" style="min-width: 240px;" placeholder="Search code / village / district / regency / province" value="{{ $filters['q'] ?? '' }}">
                        <select name="perPage" class="form-select" style="width: auto;">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" {{ (int) ($filters['perPage'] ?? 25) === (int) $option ? 'selected' : '' }}>
                                    {{ $option }} / page
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-primary">Apply</button>
                        <a href="{{ url()->current() }}" class="btn btn-light">Reset</a>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Village / Subvillage</th>
                                    <th>District</th>
                                    <th>Regency / City</th>
                                    <th>Province</th>
                                    <th>Last Sync</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>{{ $row->code }}</td>
                                        <td><h6 class="fw-medium mb-0">{{ $row->name }}</h6></td>
                                        <td>{{ $row->district?->name ?? '-' }}</td>
                                        <td>{{ $row->district?->regency?->name ?? '-' }}</td>
                                        <td>{{ $row->district?->regency?->province?->name ?? '-' }}</td>
                                        <td>{{ optional($row->updated_at)->format('d M Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No villages synced yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($rows->hasPages())
                    <div class="card-footer d-flex justify-content-end">
                        {{ $rows->links('pagination::simple-bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection