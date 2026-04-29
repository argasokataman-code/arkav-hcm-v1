<?php $page = 'chat'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            @include('partials.chat.breadcrumb')

            <div class="chat-wrapper">
                @include('partials.chat.sidebar-header')
                @include('partials.chat.sidebar-contacts-a')
                @include('partials.chat.sidebar-contacts-b')
                @include('partials.chat.chat-window-header')
                @include('partials.chat.chat-messages-a')
                @include('partials.chat.chat-messages-b')
                @include('partials.chat.chat-footer')
            </div>

        </div>
    </div>
    <!-- /Page Wrapper -->

@endsection
