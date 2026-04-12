<?php $page = 'ticket-master'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-tickets-page="master">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Master Ticket</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">HCM</li>
                        <li class="breadcrumb-item active">Master Ticket</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Kategori Ticket</h5></div>
            <div class="card-body">
                <form class="row g-2 mb-3" data-ticket-category-form="add">
                    <div class="col-md-6"><input class="form-control" name="name" placeholder="Nama kategori" required maxlength="120"></div>
                    <div class="col-md-2"><input type="number" class="form-control" name="sortOrder" value="0" min="0"></div>
                    <div class="col-md-2">
                        <select class="form-select" name="isActive">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Tambah</button></div>
                </form>
                <div data-ticket-category-table>
                    <div class="text-muted">Memuat kategori...</div>
                </div>
            </div>
        </div>
    </div>
</div>
@component('components.modal-popup')@endcomponent
@endsection
