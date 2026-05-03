<div class="me-1 notification_item">
    <a href="#" class="btn btn-menubar position-relative me-1" id="notification_popup"
        data-bs-toggle="dropdown">
        <i class="ti ti-bell"></i>
        <span class="notification-status-dot d-none" data-notification-status-dot></span>
        <span class="badge bg-danger rounded-pill d-none align-items-center justify-content-center header-badge" data-notification-unread-badge>0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end notification-dropdown p-4">
        <div class="d-flex align-items-center justify-content-between border-bottom p-0 pb-3 mb-3">
            <h4 class="notification-title" data-notification-title>Notifications (0)</h4>
            <div class="d-flex align-items-center">
                <a href="javascript:void(0);" class="text-primary fs-15 me-3 lh-1" data-notification-mark-all>Mark all as read</a>
            </div>
        </div>
        <div class="noti-content" data-notification-content>
            <div class="text-muted text-center py-3" data-notification-empty-state>Loading notifications...</div>
        </div>
        <div class="d-flex p-0">
            <a href="javascript:void(0);" class="btn btn-light w-100 me-2" data-notification-refresh>Refresh</a>
            @if ($isPrimarySuperAdmin)
            <a href="{{ url('activity') }}" class="btn btn-primary w-100">Open Activity</a>
            @else
            <a href="{{ url('notification-settings') }}" class="btn btn-primary w-100">Notification Settings</a>
            @endif
        </div>
    </div>
</div>
