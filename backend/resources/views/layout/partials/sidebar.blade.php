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
    $hasCompanyBillingAccess = (bool) $activeCompanySubscription;
    $hasAssetManagement = (bool) ($activeCompanySubscription?->package?->hasFeature('asset_management') ?? false);
    $hasTickets = (bool) ($activeCompanySubscription?->package?->hasFeature('tickets') ?? false);
    $hasTraining = (bool) ($activeCompanySubscription?->package?->hasFeature('training') ?? false);
    // Feature bypass: global/QA admin only bypasses feature gates when NOT inside a specific tenant context.
    // When operating within a tenant (activeCompany set), feature menus follow the tenant's subscription.
    $isInTenantContext = $activeCompany instanceof \App\Models\Company;
    $featureBypass = $isGlobalHcmAdmin && !$isInTenantContext;
    $canSeeAssetManagementMenu = $featureBypass || ($hasAssetManagement && !$isEmployeeScopedUser && $isHcmAdmin);
    $hasPayroll = (bool) ($activeCompanySubscription?->package?->hasFeature('payroll') ?? false);
    $hasPerformance = (bool) ($activeCompanySubscription?->package?->hasFeature('performance') ?? false);
    $activeCompanyIdentifier = $activeCompany instanceof \App\Models\Company
        ? ((string) ($activeCompany->uuid ?? '') !== '' ? (string) $activeCompany->uuid : (string) ((int) ($activeCompany->id ?? 0)))
        : null;
    $canManageTrainingMenu = $featureBypass || ($hasTraining && (bool) ($authUser?->hasPermissionForCompany('training.manage', $activeCompanyIdentifier)));
    $canViewTrainingMenu = $canManageTrainingMenu || ($hasTraining && (bool) ($authUser?->hasPermissionForCompany('training.view', $activeCompanyIdentifier)));
    $canSeeTicketsMenu = $featureBypass || $hasTickets;
    $canSeePerformanceMenu = $featureBypass || ($hasPerformance && !$isEmployeeScopedUser && $isHcmAdmin);
    $isSuperAdminDeveloperMode = $featureBypass;
    $superAdminUnlockedPayroll = $hasPayroll || $isSuperAdminDeveloperMode;
    $superAdminUnlockedPerformance = $hasPerformance || $isSuperAdminDeveloperMode;
    $canSeePayrollMenu = $featureBypass || ($hasPayroll && !$isEmployeeScopedUser && ($isHcmAdmin || $isGlobalHcmAdmin));
@endphp

@include('layout.partials.sidebar.main-sidebar')
@include('layout.partials.sidebar.horizontal-menu')
@include('layout.partials.sidebar.two-col-sidebar')
@include('layout.partials.sidebar.stacked-sidebar')
