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

        return view('public.landing', [
            'packages' => $packages,
        ]);
    }

    public function trial(Request $request): View
    {
        if (auth()->check()) {
            return redirect()->route('index');
        }

        $startMode = trim((string) $request->query('startMode', 'trial'));
        if (! in_array($startMode, ['trial', 'pending_payment'], true)) {
            $startMode = 'trial';
        }

        $isPendingPaymentMode = $startMode === 'pending_payment';

        try {
            $packagesQuery = Package::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('monthly_price');

            if ($isPendingPaymentMode) {
                $packagesQuery->where('code', '!=', 'trial');
            }

            $packages = $packagesQuery->get(['uuid', 'code', 'name', 'monthly_price', 'yearly_price']);
        } catch (\Throwable $e) {
            $packages = collect();
        }

        $selectedPackageId = trim((string) $request->query('packageId', ''));
        if ($selectedPackageId === '') {
            if ($isPendingPaymentMode) {
                $selectedPackageId = (string) ($packages->first()->uuid ?? '');
            } else {
                try {
                    $selectedPackageId = (string) (Package::query()
                        ->where('status', 'active')
                        ->where('code', 'trial')
                        ->value('uuid') ?? '');
                } catch (\Throwable $e) {
                    $selectedPackageId = '';
                }
            }

            $selectedPackageId = $selectedPackageId !== '' ? $selectedPackageId : null;
        }

        return view('public.trial', [
            'packages' => $packages,
            'selectedPackageId' => $selectedPackageId,
            'startMode' => $startMode,
        ]);
    }
}

