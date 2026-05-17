
<!-- jQuery -->
<script src="{{ URL::asset('build/js/vendor/jquery-3.7.1.min.js') }}"></script>

<!-- Bootstrap Core JS -->
<script src="{{ URL::asset('build/js/vendor/bootstrap.bundle.min.js') }}"></script>

<!-- Authorization/Permissions Utility (MUST BE LOADED EARLY) -->
<script>
    @php
        $footerAuthUser = request()->user()
            ?: auth()->user()
            ?: optional(\App\Support\ArcavAccessTokenResolver::validTokenFromRequest(request()))->user;
        $footerActiveCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
        $footerIsGlobalHcmAdmin = $footerAuthUser ? $footerAuthUser->isGlobalHcmAdmin() : false;
        $footerIsHcmAdmin = $footerAuthUser
            ? ($footerActiveCompanyId > 0
                ? $footerAuthUser->isHcmAdminForCompany($footerActiveCompanyId)
                : $footerAuthUser->isHcmAdmin())
            : false;
        $footerPermissionCodes = [];

        if ($footerAuthUser) {
            if ($footerIsGlobalHcmAdmin) {
                $footerPermissionCodes = \App\Models\HcmPermission::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->pluck('code')
                    ->map(static fn ($code): string => (string) $code)
                    ->values()
                    ->all();
            } else {
                $footerPermissionCodes = array_keys(
                    $footerActiveCompanyId > 0
                        ? $footerAuthUser->permissionsForContext($footerActiveCompanyId)
                        : $footerAuthUser->permissionsForContext()
                );

                sort($footerPermissionCodes);
            }
        }
    @endphp

	// Inject auth user context from blade template
	window.AuthUser = {
        id: {{ $footerAuthUser?->id ?? 'null' }},
        email: '{{ $footerAuthUser?->email ?? '' }}',
        isHcmAdmin: {{ $footerIsHcmAdmin ? 'true' : 'false' }},
        hcmGlobalAdmin: {{ $footerIsGlobalHcmAdmin ? 'true' : 'false' }},
        permissions: @json($footerPermissionCodes),
        name: '{{ $footerAuthUser?->name ?? '' }}',
	};
</script>
<script src="{{ URL::asset('build/js/core/auth-permissions-utils.js') }}"></script>

<!-- Feather Icon JS -->
<script src="{{ URL::asset('build/js/vendor/feather.min.js') }}"></script>

<!-- Slimscroll JS -->
<script src="{{ URL::asset('build/js/vendor/jquery.slimscroll.min.js') }}"></script>

<!-- Summernote JS -->
<script src="{{ URL::asset('build/plugins/summernote/summernote-lite.min.js') }}"></script>

<!-- Color Picker JS -->
<script src="{{ URL::asset('build/js/vendor/plyr-js.js') }}"></script>
<script src="{{ URL::asset('build/plugins/@simonwep/pickr/pickr.es5.min.js') }}"></script>

<!-- Datatable JS -->
<script src="{{ URL::asset('build/js/vendor/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/dataTables.bootstrap5.min.js') }}"></script>	

<!-- Bootstrap Tagsinput JS -->
<script src="{{ URL::asset('build/plugins/bootstrap-tagsinput/bootstrap-tagsinput.js') }}"></script>

<!-- Owl Carousel -->
<script src="{{ URL::asset('build/plugins/owlcarousel/owl.carousel.min.js') }}"></script>

<!-- Daterangepikcer JS -->
<script src="{{ URL::asset('build/js/vendor/moment.js') }}"></script>
<script src="{{ URL::asset('build/plugins/daterangepicker/daterangepicker.js') }}"></script>

@if (Route::is(['ui-rangeslider']))
    <!-- Rangeslider JS -->
    <script src="{{ URL::asset('build/plugins/ion-rangeslider/js/ion.rangeSlider.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/ion-rangeslider/js/custom-rangeslider.js') }}"></script>
@endif

<!-- Fullcalendar JS -->
<script src="{{ URL::asset('build/plugins/fullcalendar/index.global.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/fullcalendar/calendar-data.js') }}"></script>

<!-- Datetimepicker JS -->
<script src="{{ URL::asset('build/js/vendor/bootstrap-datetimepicker.min.js') }}"></script>

<!-- Select2 JS -->
<script src="{{ URL::asset('build/plugins/select2/js/select2.min.js') }}"></script>

<!-- Theiastickysidebar JS -->
<script src="{{ URL::asset('build/plugins/theia-sticky-sidebar/theia-sticky-sidebar.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/theia-sticky-sidebar/ResizeSensor.min.js') }}"></script>

<!-- Owl Carousel JS -->
<script src="{{ URL::asset('build/js/vendor/owl.carousel.min.js') }}"></script>

@if (Route::is(['ui-clipboard']))
    <!-- Clipboard JS -->
    <script src="{{ URL::asset('build/plugins/clipboard/clipboard.min.js') }}"></script>
@endif

@if (Route::is(['maps-vector']))

<script src="{{ URL::asset('build/plugins/jsvectormap/js/jsvectormap.min.js') }}"></script>
<!-- JSVector Maps MapsJS -->
<script src="{{ URL::asset('build/plugins/jsvectormap/maps/world-merc.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/us-merc-en.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/russia.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/spain.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/canada.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/jsvectormap.js') }}"></script>
<script src="{{ URL::asset('build/plugins/@simonwep/pickr/pickr.min.js') }}"></script>

@endif

@if (Route::is(['maps-leaflet']))

<script src="{{ URL::asset('build/plugins/leaflet/leaflet.js') }}"></script>
<script src="{{ URL::asset('build/js/vendor/leaflet.js') }}"></script>

@endif

@if (Route::is(['ui-drag-drop']))
    <!-- Dragula JS -->
    <script src="{{ URL::asset('build/plugins/dragula/js/dragula.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/dragula/js/drag-drop.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/dragula/js/draggable-cards.js') }}"></script>
@endif

@if (Route::is(['ui-sweetalerts', 'ui-ribbon',]))
    <!-- Sweetalert 2 -->
    <script src="{{ URL::asset('build/plugins/sweetalert/sweetalert2.all.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/sweetalert/sweetalerts.min.js') }}"></script>
@endif

@if (Route::is(['ui-stickynote', 'candidates-kanban']))
    <!-- Stickynote JS -->
    <script src="{{ URL::asset('build/js/vendor/jquery-ui.min.js') }}"></script>
    <script src="{{ URL::asset('build/js/vendor/jquery.ui.touch-punch.min.js') }}"></script>    
@endif

@if (Route::is(['ui-stickynote']))
<script src="{{ URL::asset('build/plugins/stickynote/sticky.js') }}"></script>
@endif

@if (Route::is([
    'chart-apex', 'index', 'employee-dashboard', 'file-manager', 'dashboard', 'companies', 'packages',
        'layout-horizontal', 'layout-detached', 'layout-modern', 'layout-horizontal-overlay', 'layout-two-column', 'layout-hovered', 'layout-box',
        'layout-horizontal-single', 'layout-horizontal-box', 'layout-horizontal-sidemenu', 'layout-vertical-transparent', 'layout-without-header',
    'layout-rtl', 'layout-dark','expenses-report','invoice-report','payment-report',
        'employee-report','payslip-report','attendance-report', 'leave-report', 'daily-report',
    'super-admin.employees-monitor', 'super-admin.package-compliance',
    ]))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/apexchart/apexcharts.min.js') }}?v={{ file_exists(public_path('build/plugins/apexchart/apexcharts.min.js')) ? filemtime(public_path('build/plugins/apexchart/apexcharts.min.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/plugins/apexchart/chart-data.js') }}?v={{ file_exists(public_path('build/plugins/apexchart/chart-data.js')) ? filemtime(public_path('build/plugins/apexchart/chart-data.js')) : time() }}"></script>
@endif

@if (Route::is(['chart-c3']))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/c3-chart/d3.v5.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/c3-chart/c3.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/c3-chart/chart-data.js') }}"></script>
@endif

@if (Route::is(['chart-js', 'index', 'dashboard', 'companies', 'layout-horizontal', 'layout-detached', 'layout-modern', 
'layout-horizontal-overlay', 'layout-two-column', 'layout-hovered', 'layout-box', 'layout-horizontal-single', 'layout-horizontal-box', 'layout-horizontal-sidemenu',
'layout-vertical-transparent', 'layout-without-header', 'layout-rtl', 'layout-dark'
]))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/chartjs/chart.min.js') }}?v={{ file_exists(public_path('build/plugins/chartjs/chart.min.js')) ? filemtime(public_path('build/plugins/chartjs/chart.min.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/plugins/chartjs/chart-data.js') }}?v={{ file_exists(public_path('build/plugins/chartjs/chart-data.js')) ? filemtime(public_path('build/plugins/chartjs/chart-data.js')) : time() }}"></script>
@endif

@if (Route::is(['chart-morris']))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/morris/raphael-min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/morris/morris.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/morris/chart-data.js') }}"></script>
@endif

@if (Route::is(['chart-peity', 'dashboard', 'companies', 'subscription', 'tickets-grid','tickets']))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/peity/jquery.peity.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/peity/chart-data.js') }}"></script>
@endif

@if (Route::is(['chart-flot']))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/flot/jquery.flot.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/flot/jquery.flot.fillbetween.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/flot/jquery.flot.pie.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/flot/chart-data.js') }}"></script>
@endif

<!-- Slimscroll JS -->
<script src="{{ URL::asset('build/js/vendor/jquery.slimscroll.min.js') }}"></script>

@if (Route::is(['ui-rating']))
    <!-- Rater JS -->
    <script src="{{ URL::asset('build/plugins/rater-js/index.js') }}"></script>

    <!-- Internal Ratings JS -->
    <script src="{{ URL::asset('build/js/vendor/ratings.js') }}"></script>
@endif

@if (Route::is(['ui-toasts']))
    <!-- Chart JS -->
    <script src="{{ URL::asset('build/plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/toastr/toastr.js') }}"></script>
@endif

@if (Route::is(['ui-counter']))
    <!-- Stickynote JS -->
    <script src="{{ URL::asset('build/plugins/countup/jquery.counterup.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/countup/jquery.waypoints.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/countup/jquery.missofis-countdown.js') }}"></script>
@endif

@if (Route::is(['ui-lightbox']))
    <!-- Alertify JS -->
    <script src="{{ URL::asset('build/plugins/lightbox/glightbox.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/lightbox/lightbox.js') }}"></script>
@endif

@if (Route::is(['form-wizard']))
    <!-- Wizard JS -->
    <script src="{{ URL::asset('build/plugins/twitter-bootstrap-wizard/jquery.bootstrap.wizard.min.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/twitter-bootstrap-wizard/prettify.js') }}"></script>
    <script src="{{ URL::asset('build/plugins/twitter-bootstrap-wizard/form-wizard.js') }}"></script>
@endif

@if (Route::is(['form-mask']))
    <!-- Mask JS -->
    <script src="{{ URL::asset('build/js/vendor/jquery.maskedinput.min.js') }}"></script>
    <script src="{{ URL::asset('build/js/core/mask.js') }}"></script>
@endif

<!-- Sticky Sidebar JS -->
<script src="{{ URL::asset('build/plugins/theia-sticky-sidebar/ResizeSensor.js') }}"></script>
<script src="{{ URL::asset('build/plugins/theia-sticky-sidebar/theia-sticky-sidebar.js') }}"></script>

@if (Route::is(['reset-password','reset-password-2','reset-password-3']))
<!-- Validation-->
<script src="{{ URL::asset('build/js/core/validation.js') }}"></script>
@endif

@if (Route::is(['email-verification','email-verification-2','email-verification-3','two-step-verification','two-step-verification-2','two-step-verification-3']))
<script src="{{ URL::asset('build/js/core/otp.js') }}"></script>
@endif



@if (Route::is(['form-fileupload']))
    <!-- Fileupload JS -->
    <script src="{{ URL::asset('build/plugins/fileupload/fileupload.min.js') }}"></script>
@endif

@if (Route::is(['employee-salary']))
<script src="{{ URL::asset('build/js/employees/employee-salary-data.js') }}?v={{ file_exists(public_path('build/js/employees/employee-salary-data.js')) ? filemtime(public_path('build/js/employees/employee-salary-data.js')) : time() }}"></script>
@endif

@if (Route::is(['assets', 'asset-categories']))
<script src="{{ URL::asset('build/js/assets/asset-management/utils.js') }}?v={{ file_exists(public_path('build/js/assets/asset-management/utils.js')) ? filemtime(public_path('build/js/assets/asset-management/utils.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/assets/asset-management-data.js') }}?v={{ file_exists(public_path('build/js/assets/asset-management-data.js')) ? filemtime(public_path('build/js/assets/asset-management-data.js')) : time() }}"></script>
@endif


<!-- Fancybox JS -->
<script src="{{ URL::asset('build/plugins/fancybox/jquery.fancybox.min.js') }}"></script>

{{-- Chart.js / chart-data hanya lewat blok @if chart-js di atas — jangan muat dua kali (Chart error: canvas already in use). --}}

@if (Route::is(['form-pickers']))
<script src="{{ URL::asset('build/plugins/flatpickr/flatpickr.js') }}"></script>
<script src="{{ URL::asset('build/plugins/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
<script src="{{ URL::asset('build/plugins/jquery-timepicker/jquery-timepicker.js') }}"></script>
<script src="{{ URL::asset('build/plugins/pickr/pickr.js') }}"></script>

<!-- Page JS -->
<script src="{{ URL::asset('build/js/core/forms-pickers.js') }}"></script>
@endif


@if (Route::is(['coming-soon']))
<script src="{{ URL::asset('build/js/crm/coming-soon.js') }}"></script>
@endif

<script src="{{ URL::asset('build/js/crm/email.js') }}"></script>
<script src="{{ URL::asset('build/js/crm/kanban.js') }}"></script>
<script src="{{ URL::asset('build/js/crm/invoice.js') }}"></script>
<script src="{{ URL::asset('build/js/crm/projects.js') }}"></script>
<script src="{{ URL::asset('build/js/crm/add-comments.js')}}"></script>
<script src="{{ URL::asset('build/js/crm/file-manager.js') }}"></script>
<script src="{{ URL::asset('build/js/core/api-client.js') }}"></script>
<script src="{{ URL::asset('build/js/core/arcav-validation.js') }}"></script>
<script src="{{ URL::asset('build/js/core/arcav-template-cleanup.js') }}"></script>
<script src="{{ URL::asset('build/js/core/auth-logout.js') }}"></script>
<script src="{{ URL::asset('build/js/notifications/notification-inbox-data.js') }}"></script>
<script src="{{ URL::asset('build/js/notifications/global-search-data.js') }}?v={{ file_exists(public_path('build/js/notifications/global-search-data.js')) ? filemtime(public_path('build/js/notifications/global-search-data.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/notifications/ai-chat-widget.js') }}?v={{ file_exists(public_path('build/js/notifications/ai-chat-widget.js')) ? filemtime(public_path('build/js/notifications/ai-chat-widget.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/employees/employees-view-toggle.js') }}"></script>
<script src="{{ URL::asset('build/js/employees/module-loaders.js') }}?v={{ file_exists(public_path('build/js/employees/module-loaders.js')) ? filemtime(public_path('build/js/employees/module-loaders.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/employees/employees-data.js') }}?v={{ file_exists(public_path('build/js/employees/employees-data.js')) ? filemtime(public_path('build/js/employees/employees-data.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/hcm/hcm-pages/utils.js') }}?v={{ file_exists(public_path('build/js/hcm/hcm-pages/utils.js')) ? filemtime(public_path('build/js/hcm/hcm-pages/utils.js')) : time() }}"></script>
<script src="{{ URL::asset('build/js/hcm/hcm-pages-data.js') }}?v={{ file_exists(public_path('build/js/hcm/hcm-pages-data.js')) ? filemtime(public_path('build/js/hcm/hcm-pages-data.js')) : time() }}"></script>
@if (Route::is(['pages']))
    <script src="{{ URL::asset('build/js/hcm/pages-hcm-hub.js') }}?v={{ file_exists(public_path('build/js/hcm/pages-hcm-hub.js')) ? filemtime(public_path('build/js/hcm/pages-hcm-hub.js')) : time() }}"></script>
@endif
@if (Route::is(['index']))
    <script src="{{ URL::asset('build/js/reports/index-dashboard-data.js') }}?v={{ file_exists(public_path('build/js/reports/index-dashboard-data.js')) ? filemtime(public_path('build/js/reports/index-dashboard-data.js')) : time() }}"></script>
@endif
@if (Route::is(['employee-dashboard']))
    <script src="{{ URL::asset('build/js/employees/employee-dashboard/template-binders.js') }}?v={{ file_exists(public_path('build/js/employees/employee-dashboard/template-binders.js')) ? filemtime(public_path('build/js/employees/employee-dashboard/template-binders.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/employees/employee-dashboard-data.js') }}?v={{ file_exists(public_path('build/js/employees/employee-dashboard-data.js')) ? filemtime(public_path('build/js/employees/employee-dashboard-data.js')) : time() }}"></script>
@endif
@if (Route::is(['attendance-employee', 'employee-dashboard']))
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endif
@if (Route::is(['schedule-timing']))
    <script src="{{ URL::asset('build/vendor/fullcalendar/core/index.global.min.js') }}"></script>
    <script src="{{ URL::asset('build/vendor/fullcalendar/core/locales-all.global.min.js') }}"></script>
    <script src="{{ URL::asset('build/vendor/fullcalendar/daygrid/index.global.min.js') }}"></script>
    <script src="{{ URL::asset('build/vendor/fullcalendar/timegrid/index.global.min.js') }}"></script>
    <script src="{{ URL::asset('build/vendor/fullcalendar/list/index.global.min.js') }}"></script>
@endif
<script type="module" src="{{ URL::asset('build/js/attendance/attendance-data.js') }}?v={{ file_exists(public_path('build/js/attendance/attendance-data.js')) ? filemtime(public_path('build/js/attendance/attendance-data.js')) : time() }}"></script>

@if (Route::is(['holidays', 'leaves', 'leaves-employee', 'leave-report', 'overtime', 'overtime-employee']))
    <script src="{{ URL::asset('build/js/hcm/hcm-extras/module-loaders.js') }}?v={{ file_exists(public_path('build/js/hcm/hcm-extras/module-loaders.js')) ? filemtime(public_path('build/js/hcm/hcm-extras/module-loaders.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/hcm/hcm-extras-data.js') }}?v={{ file_exists(public_path('build/js/hcm/hcm-extras-data.js')) ? filemtime(public_path('build/js/hcm/hcm-extras-data.js')) : time() }}"></script>
@endif

@if (Route::is(['tickets', 'tickets-admin', 'tickets-employee', 'tickets-grid', 'ticket-details', 'ticket-master']))
    <script src="{{ URL::asset('build/js/documents/tickets-data.js') }}"></script>
@endif

@if (Route::is(['profile']))
    <script src="{{ URL::asset('build/js/employees/profile-data.js') }}"></script>
@endif

@if (Route::is(['profile-settings']))
    <script src="{{ URL::asset('build/js/employees/profile-settings-data.js') }}"></script>
@endif

@if (Route::is(['company-profile']))
    <script src="{{ URL::asset('build/js/company/company-profile-data.js') }}"></script>
@endif

@if (Route::is(['company-overview']))
    <script src="{{ URL::asset('build/js/company/company-overview-data.js') }}"></script>
@endif

@if (Route::is(['invoice-settings']))
    <script src="{{ URL::asset('build/js/settings/invoice-settings-data.js') }}"></script>
@endif

@if (Route::is(['email-settings']))
    <script src="{{ URL::asset('build/js/settings/email-settings-data.js') }}?v={{ file_exists(public_path('build/js/settings/email-settings-data.js')) ? filemtime(public_path('build/js/settings/email-settings-data.js')) : time() }}"></script>
@endif

@if (Route::is(['notification-settings']))
    <script src="{{ URL::asset('build/js/notifications/notification-settings-data.js') }}"></script>
@endif

@if (Route::is(['security-settings']))
    <script src="{{ URL::asset('build/js/security/security-settings-data.js') }}"></script>
@endif

@if (Route::is(['security-incidents']))
    <script src="{{ URL::asset('build/js/security/security-incidents-data.js') }}?v={{ file_exists(public_path('build/js/security/security-incidents-data.js')) ? filemtime(public_path('build/js/security/security-incidents-data.js')) : time() }}"></script>
@endif

@if (Route::is(['notification-observability']))
    <script src="{{ URL::asset('build/js/notifications/notification-observability-data.js') }}"></script>
@endif

@if (Route::is(['activity']))
    <script src="{{ URL::asset('build/js/reports/activity-data.js') }}?v={{ file_exists(public_path('build/js/reports/activity-data.js')) ? filemtime(public_path('build/js/reports/activity-data.js')) : time() }}"></script>
@endif

@if (Route::is(['performance-indicator', 'performance-appraisal', 'performance-review']))
    <script src="{{ URL::asset('build/js/reports/performance/utils.js') }}?v={{ file_exists(public_path('build/js/reports/performance/utils.js')) ? filemtime(public_path('build/js/reports/performance/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/reports/performance-data.js') }}"></script>
@endif

@if (Route::is(['goal-tracking', 'goal-type']))
    <script src="{{ URL::asset('build/js/reports/goal-data.js') }}"></script>
@endif

@if (Route::is(['training', 'training-type']))
    <script src="{{ URL::asset('build/js/reports/training/utils.js') }}?v={{ file_exists(public_path('build/js/reports/training/utils.js')) ? filemtime(public_path('build/js/reports/training/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/reports/training-data.js') }}"></script>
@endif

@if (Route::is(['document-center']))
    <script src="{{ URL::asset('build/js/documents/document-center.js') }}?v={{ file_exists(public_path('build/js/documents/document-center.js')) ? filemtime(public_path('build/js/documents/document-center.js')) : time() }}"></script>
@endif

@if (Route::is(['users']))
    <script src="{{ URL::asset('build/js/employees/users-management.js') }}?v={{ file_exists(public_path('build/js/employees/users-management.js')) ? filemtime(public_path('build/js/employees/users-management.js')) : time() }}"></script>
@endif

@if (Route::is(['roles-permissions']))
    <script src="{{ URL::asset('build/js/security/roles-permissions.js') }}?v={{ file_exists(public_path('build/js/security/roles-permissions.js')) ? filemtime(public_path('build/js/security/roles-permissions.js')) : time() }}"></script>
@endif

@if (Route::is(['reports-hub']))
    <script src="{{ URL::asset('build/js/reports/reports-hub.js') }}?v={{ file_exists(public_path('build/js/reports/reports-hub.js')) ? filemtime(public_path('build/js/reports/reports-hub.js')) : time() }}"></script>
@endif

@if (request()->is('tax-rates*') || request()->is('tax-employees*') || request()->is('platform-tax-compliance*') || request()->is('saas/pricing*'))
    <script src="{{ URL::asset('build/js/payroll/tax-governance/dashboard-utils.js') }}?v={{ file_exists(public_path('build/js/payroll/tax-governance/dashboard-utils.js')) ? filemtime(public_path('build/js/payroll/tax-governance/dashboard-utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/tax-governance/compliance-renderers.js') }}?v={{ file_exists(public_path('build/js/payroll/tax-governance/compliance-renderers.js')) ? filemtime(public_path('build/js/payroll/tax-governance/compliance-renderers.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/tax-governance-dashboard.js') }}?v={{ file_exists(public_path('build/js/payroll/tax-governance-dashboard.js')) ? filemtime(public_path('build/js/payroll/tax-governance-dashboard.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/tax-employee-profiles.js') }}?v={{ file_exists(public_path('build/js/payroll/tax-employee-profiles.js')) ? filemtime(public_path('build/js/payroll/tax-employee-profiles.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/tax-tenant-compliance.js') }}?v={{ file_exists(public_path('build/js/payroll/tax-tenant-compliance.js')) ? filemtime(public_path('build/js/payroll/tax-tenant-compliance.js')) : time() }}"></script>
@endif

@if (Route::is(['invoice-report', 'payment-report', 'user-report', 'daily-report']))
    <script src="{{ URL::asset('build/js/reports/reports-api-sync.js') }}?v={{ file_exists(public_path('build/js/reports/reports-api-sync.js')) ? filemtime(public_path('build/js/reports/reports-api-sync.js')) : time() }}"></script>
@endif

@if (Route::is(['trainers']))
    <script src="{{ URL::asset('build/js/reports/training/utils.js') }}?v={{ file_exists(public_path('build/js/reports/training/utils.js')) ? filemtime(public_path('build/js/reports/training/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/reports/training-data.js') }}"></script>
@endif

@if (Route::is(['shift-master']))
    <script src="{{ URL::asset('build/js/payroll/shift-master-data.js') }}"></script>
@endif

@if (Route::is(['salary-component-master']))
    <script src="{{ URL::asset('build/js/payroll/salary-component-master/utils.js') }}?v={{ file_exists(public_path('build/js/payroll/salary-component-master/utils.js')) ? filemtime(public_path('build/js/payroll/salary-component-master/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/salary-component-master-data.js') }}"></script>
@endif
@if (request()->is('bpjs-governance*'))
    <script src="{{ URL::asset('build/js/payroll/bpjs-governance/utils.js') }}?v={{ file_exists(public_path('build/js/payroll/bpjs-governance/utils.js')) ? filemtime(public_path('build/js/payroll/bpjs-governance/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/payroll/bpjs-governance-data.js') }}?v={{ file_exists(public_path('build/js/payroll/bpjs-governance-data.js')) ? filemtime(public_path('build/js/payroll/bpjs-governance-data.js')) : time() }}"></script>
@endif
@if (request()->is('employee-allowance-governance*'))
    <script src="{{ URL::asset('build/js/employees/employee-allowance-governance-data.js') }}?v={{ file_exists(public_path('build/js/employees/employee-allowance-governance-data.js')) ? filemtime(public_path('build/js/employees/employee-allowance-governance-data.js')) : time() }}"></script>
@endif
@if (request()->is('spt-masa-pph21*'))
    <script src="{{ URL::asset('build/js/payroll/spt-masa-data.js') }}?v={{ file_exists(public_path('build/js/payroll/spt-masa-data.js')) ? filemtime(public_path('build/js/payroll/spt-masa-data.js')) : time() }}"></script>
@endif
@if (Route::is(['payroll-run']))
    <script src="{{ URL::asset('build/js/payroll-run.js') }}?v={{ file_exists(public_path('build/js/payroll-run.js')) ? filemtime(public_path('build/js/payroll-run.js')) : time() }}"></script>
@else
    @if (request()->path() === 'payroll-run')
        <script src="{{ URL::asset('build/js/payroll-run.js') }}?v={{ file_exists(public_path('build/js/payroll-run.js')) ? filemtime(public_path('build/js/payroll-run.js')) : time() }}"></script>
    @endif
@endif
@if (Route::is(['payroll-run-history']))
    <script src="{{ URL::asset('build/js/payroll/payroll-run-history-data.js') }}"></script>
@endif
@if (Route::is(['payroll-thr']))
    <script src="{{ URL::asset('build/js/payroll/payroll-thr-data.js') }}"></script>
    {{-- Cache-bust: bundel lama pernah mem-minify apiRequest jadi global `$` dan menimpa jQuery --}}
    <script src="{{ URL::asset('build/js/thr-payroll-batch.js') }}?v=20260411-disburse-modal"></script>
@endif
@if (Route::is(['payroll-pkwt-compensation']))
    <script src="{{ URL::asset('build/js/payroll/pkwt-compensation-data.js') }}"></script>
@endif

@if (Route::is(['payroll-overtime']))
    <script src="{{ URL::asset('build/js/payroll/payroll-overtime-data.js') }}"></script>
@endif

@if (Route::is(['payslip']))
    <script src="{{ URL::asset('build/js/payroll/payslip-data.js') }}?v={{ file_exists(public_path('build/js/payroll/payslip-data.js')) ? filemtime(public_path('build/js/payroll/payslip-data.js')) : time() }}"></script>
@endif

@if (Route::is(['payslip-report']))
    <script src="{{ URL::asset('build/js/payroll/payslip-admin-data.js') }}?v={{ file_exists(public_path('build/js/payroll/payslip-admin-data.js')) ? filemtime(public_path('build/js/payroll/payslip-admin-data.js')) : time() }}"></script>
@endif

@if (Route::is(['overtime-master']))
    <script src="{{ URL::asset('build/js/payroll/overtime-master-data.js') }}"></script>
@endif

@if (Route::is(['leave-settings']))
    <script src="{{ URL::asset('build/js/leave/leave-settings/utils.js') }}"></script>
    <script src="{{ URL::asset('build/js/leave/leave-settings-data.js') }}"></script>
@endif

@if (Route::is(['saas.billing-overview', 'saas.billing-overview.invoice-detail']))
    <script src="{{ URL::asset('build/js/saas/saas-billing-overview.js') }}?v={{ file_exists(public_path('build/js/saas/saas-billing-overview.js')) ? filemtime(public_path('build/js/saas/saas-billing-overview.js')) : time() }}"></script>
@endif

@if (Route::is(['saas.renewal-monitoring']))
    <script src="{{ URL::asset('build/js/saas/saas-renewal-monitoring.js') }}?v={{ file_exists(public_path('build/js/saas/saas-renewal-monitoring.js')) ? filemtime(public_path('build/js/saas/saas-renewal-monitoring.js')) : time() }}"></script>
@endif

@if (Route::is(['upgrade']))
    <script src="{{ URL::asset('build/js/saas/upgrade/utils.js') }}?v={{ file_exists(public_path('build/js/saas/upgrade/utils.js')) ? filemtime(public_path('build/js/saas/upgrade/utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/saas/upgrade-data.js') }}?v={{ file_exists(public_path('build/js/saas/upgrade-data.js')) ? filemtime(public_path('build/js/saas/upgrade-data.js')) : time() }}"></script>
@endif

@if (Route::is(['promotion', 'employee-details']))
    <script src="{{ URL::asset('build/js/super-admin/promotion-data.js') }}"></script>
@endif

@if (Route::is(['resignation', 'employee-details']))
    <script src="{{ URL::asset('build/js/super-admin/resignation-data.js') }}"></script>
@endif

@if (Route::is(['termination', 'employee-details']))
    <script src="{{ URL::asset('build/js/super-admin/termination-utils.js') }}?v={{ file_exists(public_path('build/js/super-admin/termination-utils.js')) ? filemtime(public_path('build/js/super-admin/termination-utils.js')) : time() }}"></script>
    <script src="{{ URL::asset('build/js/super-admin/termination-data.js') }}"></script>
@endif

@stack('scripts')

<!-- Custom JS -->
<script src="{{ URL::asset('build/js/crm/todo.js') }}"></script>
<script src="{{ URL::asset('build/js/core/theme-colorpicker.js') }}"></script>
<script src="{{ URL::asset('build/js/core/script.js') }}"></script>