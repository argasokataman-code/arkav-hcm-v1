<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaasCompanyBillingOverviewController extends Controller
{
    /**
     * GET /v1/saas/companies/billing-overview
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'tab' => ['required', 'string', Rule::in(['trial', 'subscribed'])],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tab = $validated['tab'];
        $search = trim((string) ($validated['search'] ?? ''));

        $subscriptionStatusesForTab = $tab === 'trial'
            ? ['trial']
            : ['active', 'pending_payment', 'inactive', 'expired', 'cancelled', 'suspended'];

        $query = Company::query()
            ->with([
                'subscriptions' => function ($subQuery) use ($subscriptionStatusesForTab): void {
                    $subQuery->whereIn('status', $subscriptionStatusesForTab)
                        ->with(['package', 'latestInvoice.latestEmailLog'])
                        ->orderByDesc('created_at')
                        ->orderByDesc('id');
                },
                'latestInvoice.latestEmailLog',
            ]);

        if ($tab === 'trial') {
            $query->whereHas('subscriptions', function ($subQuery) use ($subscriptionStatusesForTab): void {
                $subQuery->whereIn('status', $subscriptionStatusesForTab);
            });
        } else {
            $query->where(function ($companyQuery) use ($subscriptionStatusesForTab): void {
                $companyQuery->whereHas('subscriptions', function ($subQuery) use ($subscriptionStatusesForTab): void {
                    $subQuery->whereIn('status', $subscriptionStatusesForTab);
                })->orWhere(function ($legacyCompanyQuery): void {
                    $legacyCompanyQuery->whereHas('invoices')
                        ->whereDoesntHave('subscriptions');
                });
            });
        }

        if ($search !== '') {
            $query->where(function ($companyQuery) use ($search): void {
                $companyQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $companies = $query->orderByDesc('id')->paginate($perPage);

        $rows = collect($companies->items())
            ->map(function (Company $company): array {
                $subscription = $company->subscriptions->first();
                $package = $subscription?->package;
                $invoice = $subscription?->latestInvoice ?? $company->latestInvoice;
                $emailLog = $invoice?->latestEmailLog;

                $emailStatus = 'no_invoice';
                if ($invoice) {
                    $emailStatus = 'not_sent';
                }
                if ($emailLog) {
                    $emailStatus = $emailLog->status === 'sent' ? 'sent' : 'failed';
                }

                $stateBadges = [];
                if (($subscription?->status ?? null) === 'pending_payment') {
                    if (! $invoice) {
                        $stateBadges[] = [
                            'code' => 'INVOICE_MISSING',
                            'label' => 'Invoice Missing',
                            'kind' => 'warning',
                            'message' => 'Subscription pending payment tetapi invoice terbaru tidak ditemukan.',
                        ];
                    }

                    if ($invoice && (bool) $invoice->is_paid) {
                        $stateBadges[] = [
                            'code' => 'STATE_MISMATCH',
                            'label' => 'State Mismatch',
                            'kind' => 'warning',
                            'message' => 'Invoice sudah paid tetapi subscription masih pending payment.',
                        ];
                    }
                }

                return [
                    'company' => [
                        'id' => $company?->id,
                        'uuid' => $company?->uuid,
                        'code' => (string) ($company?->code ?? ''),
                        'name' => (string) ($company?->name ?? ''),
                    ],
                    'subscription' => [
                        'id' => $subscription?->id,
                        'uuid' => $subscription?->uuid,
                        'status' => (string) ($subscription?->status ?? ''),
                        'billingCycle' => (string) ($subscription?->billing_cycle ?? ''),
                        'startsAt' => $subscription?->starts_at,
                        'endsAt' => $subscription?->ends_at,
                        'trialEndsAt' => $subscription?->trial_ends_at,
                        'planCode' => (string) ($subscription?->plan_code ?? ''),
                        'packageId' => $package?->uuid,
                        'packageName' => (string) ($package?->name ?? ''),
                        'amount' => $subscription?->amount,
                    ],
                    'latestInvoice' => $invoice ? [
                        'id' => $invoice->id,
                        'uuid' => $invoice->uuid,
                        'invoiceNumber' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date,
                        'dueDate' => $invoice->due_date,
                        'amountDue' => $invoice->amount_due,
                        'isPaid' => (bool) $invoice->is_paid,
                        'status' => (string) $invoice->status,
                        'detailUrl' => url('/saas/billing-overview/invoices/'.$invoice->uuid),
                    ] : null,
                    'email' => [
                        'status' => $emailStatus,
                        'sentAt' => $emailLog?->created_at,
                        'lastError' => $emailLog?->error_message,
                    ],
                    'stateBadges' => $stateBadges,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'total' => $companies->total(),
                'per_page' => $companies->perPage(),
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
            ],
        ]);
    }

    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}

