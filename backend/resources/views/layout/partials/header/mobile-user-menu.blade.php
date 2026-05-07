@php
    $mobileActiveCompanyRole = strtolower(trim((string) request()->attributes->get('activeCompanyRole', '')));
    $mobileSettingsUrl = $mobileActiveCompanyRole === 'owner' ? url('company-profile') : url('profile-settings');
@endphp

<!-- Mobile Menu -->
<div class="dropdown mobile-user-menu">
    <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
    <div class="dropdown-menu dropdown-menu-end">
        <a class="dropdown-item" href="{{url('notification-settings')}}">Notifications</a>
        <a class="dropdown-item" href="{{ $mobileSettingsUrl }}">{{ $mobileActiveCompanyRole === 'owner' ? 'Company Profile' : 'My Profile' }}</a>
        <a class="dropdown-item" href="{{ $mobileSettingsUrl }}">Settings</a>
        <a class="dropdown-item" href="javascript:void(0);" data-auth-logout>Logout</a>
    </div>
</div>
<!-- /Mobile Menu -->
