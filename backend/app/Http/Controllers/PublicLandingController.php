<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLandingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('index');
        }

        try {
            $packages = Package::query()
                ->with(['features' => function ($q) {
                    $q->orderBy('feature_name');
                }])
                ->where('status', 'active')
                ->where('is_global_admin_only', false)
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get([
                    'uuid',
                    'code',
                    'name',
                    'description',
                    'monthly_price',
                    'yearly_price',
                    'billing_unit',
                    'color',
                    'sort_order',
                ]);
        } catch (\Throwable $e) {
            $packages = collect();
        }

        // Check if any subscription with trial status exists (indicates trial is available)
        $hasActiveTrialPackages = Subscription::where('status', 'trial')->exists();

        $landingBootstrap = [
            'companyName' => \App\Support\WebsiteSettings::businessCompanyName(),
            'loginUrl' => url('/login'),
            'trialUrl' => url('/trial'),
            'turnstileEnabled' => (bool) config('turnstile.enabled'),
            'turnstileHideTestNotice' => (bool) config('turnstile.hide_test_notice'),
            'turnstileSiteKey' => (string) config('turnstile.site_key'),
            'packages' => $packages->map(function ($package) {
                $featureHighlights = $package->features
                    ->filter(fn ($feature) => method_exists($feature, 'isIncluded') ? $feature->isIncluded() : true)
                    ->take(4)
                    ->map(fn ($feature) => [
                        'code' => (string) ($feature->feature_code ?? ''),
                        'name' => (string) ($feature->feature_name ?: $feature->feature_code),
                    ])
                    ->values();

                return [
                    'uuid' => (string) $package->uuid,
                    'code' => (string) $package->code,
                    'name' => (string) $package->name,
                    'description' => (string) ($package->description ?? ''),
                    'monthlyPrice' => (float) $package->monthly_price,
                    'yearlyPrice' => (float) $package->yearly_price,
                    'billingUnit' => (string) ($package->billing_unit ?? 'company'),
                    'color' => (string) ($package->color ?: '#2D7FF9'),
                    'featureHighlights' => $featureHighlights,
                ];
            })->values(),
            'hasActiveTrialPackages' => $hasActiveTrialPackages,
        ];

        return view('public.landing-new', [
            'landingBootstrap' => $landingBootstrap,
        ]);
    }
}
