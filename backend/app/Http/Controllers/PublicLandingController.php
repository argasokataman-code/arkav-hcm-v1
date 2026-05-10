<?php

namespace App\Http\Controllers;

use App\Models\Package;
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
        $hasActiveTrialPackages = \App\Models\Subscription::where('status', 'trial')->exists();

        // Get all unique features from first package for display
        $allFeatures = collect();
        if ($packages->isNotEmpty()) {
            $allFeatures = $packages->first()->features ?? collect();
        }

        return view('public.landing', [
            'packages' => $packages,
            'hasActiveTrialPackages' => $hasActiveTrialPackages,
            'allFeatures' => $allFeatures,
        ]);
    }
}
