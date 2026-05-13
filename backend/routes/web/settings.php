<?php

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Profile / account settings (any authenticated user)
Route::get('/profile-settings', function (Request $request) {
    $activeCompany = $request->attributes->get('activeCompany');
    $activeCompanyRole = strtolower(trim((string) $request->attributes->get('activeCompanyRole', '')));

    if ($activeCompany instanceof Company && $activeCompanyRole === 'owner') {
        return to_route('company-profile');
    }

    return view('settings.profile-settings');
})->name('profile-settings');

Route::get('/company-profile', function () {
    return view('settings.company-profile');
})->middleware('hcm.web.admin')->name('company-profile');

Route::get('/company-overview', function (Request $request) {
    $role = strtolower(trim((string) $request->attributes->get('activeCompanyRole', '')));
    if ($role !== 'owner') {
        return redirect()->route('company-profile');
    }
    return view('settings.company-overview');
})->middleware('hcm.web.admin')->name('company-overview');

Route::redirect('/profile-settingsrout', '/profile-settings', 301)->name('profile-settings.alias');

Route::get('/security-settings', function () {
    return view('settings.security-settings');
})->name('security-settings');

Route::get('/notification-settings', function () {
    return view('settings.notification-settings');
})->name('notification-settings');

// PDP Compliance — Security Incidents (admin only, Pasal 46 UU PDP)
Route::get('/security-incidents', function () {
    return view('security-incidents');
})->middleware('hcm.web.admin')->name('security-incidents');

// Admin-level settings
Route::get('/approval-settings', function () {
    return view('settings.approval-settings');
})->middleware('hcm.web.admin')->name('approval-settings');

Route::get('/invoice-settings', function () {
    return view('finance.invoice-settings');
})->middleware('hcm.web.admin')->name('invoice-settings');

Route::get('/preferences', function () {
    return view('settings.preferences');
})->middleware('hcm.web.admin')->name('preferences');

// Tenant tax governance (company admin)
Route::middleware('hcm.web.admin')->prefix('tax-employees')->group(function (): void {
    Route::get('/', function () {
        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'landing',
            'taxGovernancePolicyUuid' => null,
        ]);
    })->name('tax-employees');

    Route::get('/policies', function () {
        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'tenant-policies',
            'taxGovernancePolicyUuid' => null,
        ]);
    })->name('tax-employees.policies');

    Route::get('/policies/{policyUuid}/edit', function (string $policyUuid) {
        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'policy-editor',
            'taxGovernancePolicyUuid' => $policyUuid,
        ]);
    })->name('tax-employees.policies.edit');

    Route::get('/tenant-compliance', function () {
        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'tenant-compliance',
            'taxGovernancePolicyUuid' => null,
        ]);
    })->name('tax-employees.tenant-compliance');

    Route::get('/employee-tax-profiles', function () {
        $statusOptions = collect((array) config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']))
            ->map(fn ($status) => strtoupper(trim((string) $status)))
            ->filter(fn (string $status) => $status !== '')
            ->merge(['TK', 'K'])
            ->unique()
            ->values()
            ->all();

        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'employee-tax-profiles',
            'taxGovernancePolicyUuid' => null,
            'taxStatusOptions' => $statusOptions,
        ]);
    })->name('tax-employees.employee-tax-profiles');

    Route::get('/reports', function () {
        return view('finance.tax-rates', [
            'taxGovernanceScreen' => 'tenant-reports',
            'taxGovernancePolicyUuid' => null,
        ]);
    })->name('tax-employees.reports');


});

Route::middleware('hcm.web.admin')->prefix('bpjs-governance')->name('bpjs-governance.')->group(function (): void {
    Route::get('/', function () {
        return view('finance.bpjs-governance', [
            'bpjsGovernanceScreen' => 'landing',
        ]);
    })->name('index');

    Route::get('/policies', function () {
        return view('finance.bpjs-governance', [
            'bpjsGovernanceScreen' => 'policies',
        ]);
    })->name('policies');

    Route::get('/employee-membership', function () {
        return view('finance.bpjs-governance', [
            'bpjsGovernanceScreen' => 'employee-membership',
        ]);
    })->name('employee-membership');

    Route::get('/reports', function () {
        return view('finance.bpjs-governance', [
            'bpjsGovernanceScreen' => 'reports',
        ]);
    })->name('reports');

    Route::get('/rate-baselines', function () {
        return view('finance.bpjs-governance', [
            'bpjsGovernanceScreen' => 'rate-baselines',
        ]);
    })->name('rate-baselines');
});

Route::middleware('hcm.web.admin')->prefix('employee-allowance-governance')->name('employee-allowance-governance.')->group(function (): void {
    Route::get('/', function () {
        return view('finance.employee-allowance-governance', [
            'allowanceGovernanceScreen' => 'landing',
        ]);
    })->name('index');

    Route::get('/policies', function () {
        return view('finance.employee-allowance-governance', [
            'allowanceGovernanceScreen' => 'policies',
        ]);
    })->name('policies');

    Route::get('/assignments', function () {
        return view('finance.employee-allowance-governance', [
            'allowanceGovernanceScreen' => 'assignments',
        ]);
    })->name('assignments');

    Route::get('/reports', function () {
        return redirect()->route('employee-allowance-governance.index');
    })->name('reports');
});

Route::get('/appearance', function () {
    return view('settings.appearance');
})->middleware('hcm.web.admin')->name('appearance');

// Global-admin-only settings
Route::get('/notification-observability', function () {
    return view('administration.monitoring.notification-observability');
})->middleware('hcm.web.global-admin')->name('notification-observability');

Route::get('/business-settings', function () {
    return view('settings.bussiness-settings');
})->middleware('hcm.web.global-admin')->name('business-settings');

Route::redirect('/bussiness-settings', '/business-settings', 301)
    ->middleware('hcm.web.global-admin')
    ->name('bussiness-settings');

Route::get('/seo-settings', function () {
    return view('settings.seo-settings');
})->middleware('hcm.web.global-admin')->name('seo-settings');

Route::get('/localization-settings', function () {
    return view('settings.localization-settings');
})->middleware('hcm.web.global-admin')->name('localization-settings');

Route::get('/language', function () {
    return view('settings.language');
})->middleware('hcm.web.global-admin')->name('language');

Route::get('/language-web', function () {
    return view('settings.language-web');
})->middleware('hcm.web.global-admin')->name('language-web');

Route::get('/add-language', function () {
    return view('administration.localization.add-language');
})->middleware('hcm.web.global-admin')->name('add-language');

Route::get('/authentication-settings', function () {
    return view('settings.authentication-settings');
})->middleware('hcm.web.global-admin')->name('authentication-settings');

Route::get('/ai-settings', function () {
    return view('settings.ai-settings');
})->middleware('hcm.web.global-admin')->name('ai-settings');

Route::get('/custom-css', function () {
    return view('settings.custom-css');
})->middleware('hcm.web.global-admin')->name('custom-css');

Route::get('/custom-js', function () {
    return view('settings.custom-js');
})->middleware('hcm.web.global-admin')->name('custom-js');

// Tax compliance (global-admin)
Route::prefix('platform-tax-compliance')->middleware('hcm.web.global-admin')->group(function (): void {
    Route::get('/policies', function () {
        return view('tax-platform-compliance-settings', [
            'taxGovernanceScreen' => 'platform-tax-compliance',
        ]);
    })->name('platform-tax-compliance.policies');

    Route::get('/reports', function () {
        return view('tax-platform-compliance-settings', [
            'taxGovernanceScreen' => 'platform-tax-compliance',
        ]);
    })->name('platform-tax-compliance.reports');
});
