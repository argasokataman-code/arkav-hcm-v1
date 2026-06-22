<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Subscription;
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
                $cancellation = $this->buildCancellationMetadata($subscription, $invoice);

                $emailStatus = 'no_invoice';
                if ($invoice) {
                    $emailStatus = 'not_sent';
                }
                if ($emailLog) {
                    $emailStatus = $emailLog->status === 'sent' ? 'sent' : 'failed';
                }

                $stateBadges = $this->buildStateBadges($subscription, $invoice, $cancellation['reason']);

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
                        'cancellationReason' => $cancellation['reason'],
                        'cancellationDescription' => $cancellation['description'],
                        'cancelledAt' => $cancellation['cancelledAt'],
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

    private function buildStateBadges(?Subscription $subscription, ?Invoice $invoice, ?string $cancellationReason): array
    {
        if (! $subscription) {
            return [];
        }

        $badges = [];
        $status = (string) ($subscription->status ?? '');

        if ($status === 'pending_payment') {
            if (! $invoice) {
                $badges[] = [
                    'code' => 'INVOICE_MISSING',
                    'label' => 'Invoice Missing',
                    'kind' => 'warning',
                    'message' => 'Subscription pending payment tetapi invoice terbaru tidak ditemukan.',
                ];
            }

            if ($invoice && (bool) $invoice->is_paid) {
                $badges[] = [
                    'code' => 'STATE_MISMATCH',
                    'label' => 'State Mismatch',
                    'kind' => 'warning',
                    'message' => 'Invoice sudah paid tetapi subscription masih pending payment.',
                ];
            }

            if ($invoice && ! (bool) $invoice->is_paid && $invoice->due_date && $invoice->due_date->isPast()) {
                $badges[] = [
                    'code' => 'PAYMENT_OVERDUE',
                    'label' => 'Payment Overdue',
                    'kind' => 'danger',
                    'message' => 'Tagihan sudah melewati jatuh tempo dan belum dibayar.',
                ];
            }
        }

        if ($status === 'trial' && $subscription->trial_ends_at) {
            $hoursLeft = now()->diffInHours($subscription->trial_ends_at, false);
            if ($hoursLeft >= 0 && $hoursLeft <= 72) {
                $badges[] = [
                    'code' => 'TRIAL_EXPIRING_SOON',
                    'label' => 'Trial Expiring Soon',
                    'kind' => 'warning',
                    'message' => 'Trial akan berakhir kurang dari 3 hari.',
                ];
            }
        }

        if ($status === 'cancelled') {
            if ($cancellationReason === 'trial_expired') {
                $badges[] = [
                    'code' => 'CANCELLED_TRIAL_EXPIRED',
                    'label' => 'Cancelled: Trial Expired',
                    'kind' => 'secondary',
                    'message' => 'Langganan dibatalkan karena trial berakhir tanpa pembayaran lanjutan.',
                ];
            } elseif ($cancellationReason === 'payment_overdue') {
                $badges[] = [
                    'code' => 'CANCELLED_PAYMENT_OVERDUE',
                    'label' => 'Cancelled: Payment Overdue',
                    'kind' => 'secondary',
                    'message' => 'Langganan dibatalkan karena pembayaran tertunggak.',
                ];
            }
        }

        return $badges;
    }

    private function buildCancellationMetadata(?Subscription $subscription, ?Invoice $invoice): array
    {
        if (! $subscription || (string) ($subscription->status ?? '') !== 'cancelled') {
            return [
                'reason' => null,
                'description' => null,
                'cancelledAt' => null,
            ];
        }

        $terminationReason = strtolower(trim((string) ($subscription->termination_reason ?? '')));
        $metadata = (array) ($subscription->metadata ?? []);
        $seedSource = strtolower(trim((string) ($metadata['seed'] ?? '')));
        $reason = 'unknown';
        $description = 'Sistem tidak memiliki metadata alasan pembatalan yang spesifik.';

        if ($seedSource === 'saas_ui_flow' && $terminationReason === '') {
            $reason = 'seeded_demo_state';
            $description = 'Status cancelled berasal dari data demo seed untuk simulasi UI, bukan aksi pembatalan oleh user.';
        } elseif ($subscription->trial_ends_at && now()->greaterThan($subscription->trial_ends_at) && ! ($invoice?->is_paid ?? false)) {
            $reason = 'trial_expired';
            $description = 'Trial berakhir dan tidak ada pembayaran lanjutan untuk aktivasi subscription.';
        } elseif ($invoice && ! (bool) $invoice->is_paid && $invoice->due_date && $invoice->due_date->isPast()) {
            $reason = 'payment_overdue';
            $description = 'Pembayaran tertunggak melewati due date, sehingga subscription dibatalkan.';
        } elseif ($terminationReason !== '' && str_contains($terminationReason, 'tenant-initiated cancellation request')) {
            $reason = 'tenant_request';
            $description = 'Pembatalan berasal dari permintaan tenant.';
        } elseif ($terminationReason !== '') {
            $reason = 'manual_stop';
            $description = 'Langganan dihentikan dengan catatan internal: '.(string) $subscription->termination_reason;
        }

        return [
            'reason' => $reason,
            'description' => $description,
            'cancelledAt' => ($subscription->terminated_at ?? $subscription->updated_at)?->toIso8601String(),
        ];
    }
}
