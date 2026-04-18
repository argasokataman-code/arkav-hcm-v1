<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLandingController extends Controller
{
    public function index(Request $request): View
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
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get([
                    'id',
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

        return view('public.landing', [
            'packages' => $packages,
        ]);
    }

    public function trial(Request $request): View
    {
        if (auth()->check()) {
            return redirect()->route('index');
        }

        try {
            $packages = Package::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('monthly_price')
                ->get(['id', 'code', 'name', 'monthly_price', 'yearly_price']);
        } catch (\Throwable $e) {
            $packages = collect();
        }

        $selectedPackageId = $request->query('packageId');
        $selectedPackageId = is_numeric($selectedPackageId) ? (int) $selectedPackageId : null;
        if (! $selectedPackageId) {
            try {
                $selectedPackageId = (int) (Package::query()
                    ->where('status', 'active')
                    ->where('code', 'trial')
                    ->value('id') ?? 0);
            } catch (\Throwable $e) {
                $selectedPackageId = 0;
            }
            $selectedPackageId = $selectedPackageId > 0 ? $selectedPackageId : null;
        }

        return view('public.trial', [
            'packages' => $packages,
            'selectedPackageId' => $selectedPackageId,
        ]);
    }
}

