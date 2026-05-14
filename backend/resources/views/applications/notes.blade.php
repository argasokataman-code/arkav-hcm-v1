<?php $page = 'notes'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page wrapper -->
    <div class="page-wrapper">
        <div class="content pb-4">

            @include('partials.notes.breadcrumb')

            <div class="row g-3">
                @include('partials.notes.sidebar')
                <div class="col-xl-9 budget-role-notes">

                    {{-- Toolbar --}}
                    <div class="bg-white rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3">
                        {{-- Search --}}
                        <div class="input-group" style="max-width:280px;">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="ti ti-search text-muted"></i>
                            </span>
                            <input type="text" id="notes-search-input" class="form-control border-start-0 ps-0" placeholder="Search notes…">
                        </div>
                        {{-- Sort --}}
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fs-13 me-1"><i class="ti ti-filter me-1"></i></span>
                            <select id="notes-sort-select" class="form-select form-select-sm" style="min-width:160px;">
                                <option value="recent">Recent</option>
                                <option value="oldest">Oldest</option>
                                <option value="az">A → Z</option>
                                <option value="za">Z → A</option>
                            </select>
                        </div>
                    </div>

                    {{-- Tab content --}}
                    <div class="tab-content" id="v-pills-tabContent2">
                        <!-- All Notes tab -->
                        <div class="tab-pane fade active show" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab">
                            <!-- Important pinned section (populated by notes-data.js) -->
                            <div id="notes-important-section"></div>
                            <!-- All notes grid -->
                            <div class="row g-3" id="notes-all-grid">
                                <div class="col-12 text-center text-muted py-5">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Loading notes…
                                </div>
                            </div>
                        </div>

                        <!-- Important tab -->
                        <div class="tab-pane fade" id="v-pills-messages" role="tabpanel" aria-labelledby="v-pills-messages-tab">
                            <div class="row g-3" id="notes-important-grid">
                                <div class="col-12 text-center text-muted py-5">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Loading important notes…
                                </div>
                            </div>
                        </div>

                        <!-- Trash tab -->
                        <div class="tab-pane fade" id="v-pills-settings" role="tabpanel" aria-labelledby="v-pills-settings-tab">
                            <div class="d-flex align-items-center gap-2 mb-3 p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                                <i class="ti ti-alert-triangle text-danger"></i>
                                <span class="text-danger fs-13">Notes in trash will be permanently deleted after 30 days.</span>
                            </div>
                            <div class="row g-3" id="notes-trash-grid">
                                <div class="col-12 text-center text-muted py-5">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Loading trash…
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Page wrapper -->

    @component('components.modal-popup')
    @endcomponent

@endsection

@push('scripts')
<script src="{{ URL::asset('build/js/documents/notes-data.js') }}"></script>
@endpush
