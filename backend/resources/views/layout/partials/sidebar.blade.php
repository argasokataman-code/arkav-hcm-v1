<!-- Sidebar -->
@php
    $authUser = request( )->user( ) ?: auth( )->user( );
    $whiteLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_logo', URL::asset('build/img/image111.png'));
    $darkLogoUrl = \App\Support\WebsiteSettings::brandingUrl('dark_logo', URL::asset('build/img/logo-white.svg'));
    $whiteMiniLogoUrl = \App\Support\WebsiteSettings::brandingUrl('white_mini_logo', URL::asset('build/img/image111.png'));
    $darkMiniLogoUrl = \App\Support\WebsiteSettings::brandingUrl('dark_mini_logo', URL::asset('build/img/logo-white.svg'));
    $isHcmAdmin = (bool) ($authUser?->isHcmAdmin( ));
    $isGlobalHcmAdmin = (bool) ($authUser?->isGlobalHcmAdmin( ));
    $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
    $secondarySuperAdminEmail = strtolower(trim((string) config('hcm.secondary_admin_email', 'qa.hcm@example.com')));
    $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
    $isPrimarySuperAdmin = $authUser && $authUserEmail === $primarySuperAdminEmail;
    $isSecondarySuperAdmin = $authUser && $authUserEmail === $secondarySuperAdminEmail;
    $showTemplateCatalogMenus = $isPrimarySuperAdmin || $isGlobalHcmAdmin; // Super admin sees all template catalogs
    $isQaSuperAdmin = $authUser
        && (
            strtolower(trim((string) ($authUser->email ?? ''))) === strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')))
            || (bool) ($authUser->is_super_admin ?? false)
        );
    $hideTenantOperationalReports = $isGlobalHcmAdmin || $isQaSuperAdmin || $isSecondarySuperAdmin;
    $activeCompany = request( )->attributes->get('activeCompany');
    $activeCompanyRole = strtolower(trim((string) request( )->attributes->get('activeCompanyRole', '')));
    if ($activeCompanyRole === '' && $activeCompany instanceof \App\Models\Company && $authUser) {
        $activeCompanyRole = strtolower((string) (\App\Models\CompanyUser::query()
            ->where('company_id', $activeCompany->id)
            ->where('user_id', $authUser->id)
            ->value('role') ?? ''));
    }
    if ($activeCompanyRole === '' && $authUser) {
        $activeCompanyRole = strtolower((string) (\App\Models\CompanyUser::query()
            ->where('user_id', $authUser->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('role') ?? ''));
    }
    $isEmployeeScopedUser = in_array($activeCompanyRole, ['employee', 'member'], true);
    $activeCompanySubscription = $activeCompany instanceof \App\Models\Company
        ? $activeCompany->activeSubscription( )
        : null;
    $hasCompanyBillingAccess = $isHcmAdmin && (bool) $activeCompanySubscription;
    $hasAssetManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('asset_management') ?? false);
    $hasTickets = (bool) ($activeCompanySubscription?->package?->hasFeature('tickets') ?? false);
    $hasTraining = (bool) ($activeCompanySubscription?->package?->hasFeature('training') ?? false);
    $hasEmployeeManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_management') ?? false);
    $hasAttendance = (bool) ($activeCompanySubscription?->package?->hasFeature('attendance') ?? false);
    $hasAttendanceCorrection = (bool) ($activeCompanySubscription?->package?->hasFeature('attendance_correction') ?? false);
    $hasLeaveManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('leave_management') ?? false);
    $hasHolidayCalendar = (bool) ($activeCompanySubscription?->package?->hasFeature('holiday_calendar') ?? false);
    $hasEmployeeLifecycle = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_lifecycle') ?? false);
    $isInTenantContext = $activeCompany instanceof \App\Models\Company;
    // Global super admin bypass applies only outside tenant context.
    $featureBypass = $isGlobalHcmAdmin && !$isInTenantContext;
    $canSeeAssetManagementMenu = $featureBypass || ($hasAssetManagement && !$isEmployeeScopedUser && $isHcmAdmin);
    $hasPayroll = (bool) ($activeCompanySubscription?->package?->hasFeature('payroll') ?? false);
    $hasPerformance = (bool) ($activeCompanySubscription?->package?->hasFeature('performance') ?? false);
    $activeCompanyIdentifier = $activeCompany instanceof \App\Models\Company
        ? ((string) ($activeCompany->uuid ?? '') !== '' ? (string) $activeCompany->uuid : (string) ((int) ($activeCompany->id ?? 0)))
        : null;
    $canManageTrainingMenu = $featureBypass || ($hasTraining && $isHcmAdmin && (bool) ($authUser?->hasPermissionForCompany('training.manage', $activeCompanyIdentifier)));
    $canViewTrainingMenu = $canManageTrainingMenu || ($hasTraining && $isHcmAdmin && (bool) ($authUser?->hasPermissionForCompany('training.view', $activeCompanyIdentifier)));
    $hasDocumentCenter = (bool) ($activeCompanySubscription?->package?->hasFeature('employee_document_center') ?? false);
    $canManageDocumentCenterMenu = $isGlobalHcmAdmin || $featureBypass || ($hasDocumentCenter && (bool) ($authUser?->hasPermissionForCompany('document_center.manage', $activeCompanyIdentifier)));
    $canViewDocumentCenterMenu = $canManageDocumentCenterMenu || ($hasDocumentCenter && (bool) ($authUser?->hasPermissionForCompany('document_center.view', $activeCompanyIdentifier)));
    $canSeeTicketsMenu = $featureBypass || $hasTickets;
    $canSeePerformanceMenu = $featureBypass || ($hasPerformance && !$isEmployeeScopedUser && $isHcmAdmin);
    $isSuperAdminDeveloperMode = $featureBypass;
    $superAdminUnlockedPayroll = $hasPayroll || $isSuperAdminDeveloperMode;
    $superAdminUnlockedPerformance = $hasPerformance || $isSuperAdminDeveloperMode;
    $canSeePayrollMenu = $featureBypass || ($hasPayroll && !$isEmployeeScopedUser && ($isHcmAdmin || $isGlobalHcmAdmin));
    $canSeeMyPayslipMenu = $hasPayroll && $isEmployeeScopedUser;
    $hasPayrollThr = (bool) ($activeCompanySubscription?->package?->hasFeature('payroll_thr') ?? false);
    $hasTaxGovernance = (bool) ($activeCompanySubscription?->package?->hasFeature('tax_governance') ?? false);
    $hasBpjsGovernance = (bool) ($activeCompanySubscription?->package?->hasFeature('bpjs_governance') ?? false);
    $hasSptMasa = (bool) ($activeCompanySubscription?->package?->hasFeature('spt_masa_pph21') ?? false);
    $canSeePayrollThrMenu = $featureBypass || ($hasPayrollThr && !$isEmployeeScopedUser && $isHcmAdmin);
    $canSeeTaxGovernanceMenu = $featureBypass || ($hasTaxGovernance && $isHcmAdmin);
    $canSeeBpjsGovernanceMenu = $featureBypass || ($hasBpjsGovernance && $isHcmAdmin);
    $canSeeSptMasaMenu = $featureBypass || ($hasSptMasa && $isHcmAdmin);
    $canSeeEmployeesMenu = $featureBypass || $hasEmployeeManagement || $hasDocumentCenter || $hasEmployeeLifecycle;
    $canSeeAttendanceMenu = $featureBypass || $hasAttendance || $hasLeaveManagement;
    $canSeeAttendanceCorrectionMenu = $featureBypass || ($hasAttendanceCorrection && $isHcmAdmin);
    $hasOvertime = (bool) ($activeCompanySubscription?->package?->hasFeature('overtime') ?? false);
    $canSeeOvertimeMenu = $featureBypass || $hasOvertime;
    $canSeeHolidaysMenu = $featureBypass || ($hasHolidayCalendar && $isHcmAdmin);
    $canSeeEmployeeLifecycleMenu = $featureBypass || ($hasEmployeeLifecycle && $isHcmAdmin);

    // Super admin hanya melihat menu platform/SaaS, bukan HRM operasional tenant
    if ($isGlobalHcmAdmin) {
        $canSeePayrollMenu          = false;
        $canSeeEmployeesMenu        = false;
        $canSeeAttendanceMenu       = false;
        $canSeeHolidaysMenu         = false;
        $canSeePerformanceMenu      = false;
        $canViewTrainingMenu        = false;
        $canManageTrainingMenu      = false;
        $canSeeEmployeeLifecycleMenu = false;
        $canSeeTicketsMenu          = false;
        $canSeeAssetManagementMenu  = false;
        $showTemplateCatalogMenus   = false;
        $canSeePayrollThrMenu       = false;
        $canSeeTaxGovernanceMenu    = false;
        $canSeeBpjsGovernanceMenu   = false;
        $canSeeSptMasaMenu          = false;
    }
@endphp

@include('layout.partials.sidebar.main-sidebar')
@include('layout.partials.sidebar.horizontal-menu')
@include('layout.partials.sidebar.two-col-sidebar')
@include('layout.partials.sidebar.stacked-sidebar')
