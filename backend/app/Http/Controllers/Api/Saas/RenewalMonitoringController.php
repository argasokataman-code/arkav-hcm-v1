<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SubscriptionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenewalMonitoringController extends Controller
{
    /**
     * GET /v1/saas/renewal-monitoring/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $windowDays = (int) ($validated['days'] ?? 30);
        $baseQuery = $this->renewalInvoiceBaseQuery($windowDays);

        $records = (clone $baseQuery)
            ->with('subscription:id,status')
            ->get(['id', 'subscription_id', 'is_paid', 'renewal_reason_code', 'status']);

        $summary = [
            'totalRecords' => $records->count(),
            'paid' => $records->where('is_paid', true)->count(),
            'retrying' => $records->whereIn('renewal_reason_code', [
                'RENEWAL_RETRY_SCHEDULED',
                'AWAITING_GATEWAY_SETTLEMENT',
            ])->count(),
            'gracePeriod' => $records->filter(function (Invoice $invoice): bool {
                if (($invoice->subscription?->status ?? null) === 'grace_period') {
                    return true;
                }

                return in_array((string) $invoice->renewal_reason_code, ['RENEWAL_MAX_RETRY_EXCEEDED'], true);
            })->count(),
            'inactive' => $records->filter(function (Invoice $invoice): bool {
                if (($invoice->subscription?->status ?? null) === 'inactive') {
                    return true;
                }

                return in_array((string) $invoice->renewal_reason_code, ['RENEWAL_GRACE_EXPIRED'], true);
            })->count(),
            'suspended' => $records->filter(function (Invoice $invoice): bool {
                return ($invoice->subscription?->status ?? null) === 'suspended';
            })->count(),
            'anomalies' => $records->whereIn('renewal_reason_code', $this->anomalyReasonCodes())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'windowDays' => $windowDays,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * GET /v1/saas/renewal-monitoring/records
     */
    public function records(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'status' => ['nullable', 'string', 'in:paid,pending,failed'],
            'reason_code' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $windowDays = (int) ($validated['days'] ?? 30);
        $query = $this->renewalInvoiceBaseQuery($windowDays)
            ->with([
                'company:id,uuid,code,name',
                'subscription:id,uuid,status,billing_cycle,starts_at,ends_at,grace_started_at,grace_ends_at',
            ])
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            if ($validated['status'] === 'paid') {
                $query->where('is_paid', true);
            }

            if ($validated['status'] === 'pending') {
                $query->where('is_paid', false)->whereNotIn('renewal_reason_code', [
                    'MIDTRANS_INVOICE_EXPIRED',
                    'MIDTRANS_PAYMENT_FAILED',
                    'GATEWAY_DOWN',
                    'RENEWAL_GRACE_EXPIRED',
                ]);
            }

            if ($validated['status'] === 'failed') {
                $query->whereIn('renewal_reason_code', [
                    'MIDTRANS_INVOICE_EXPIRED',
                    'MIDTRANS_PAYMENT_FAILED',
                    'GATEWAY_DOWN',
                    'RENEWAL_GRACE_EXPIRED',
                ]);
            }
        }

        if (! empty($validated['reason_code'])) {
            $query->where('renewal_reason_code', $validated['reason_code']);
        }

        if (! empty($validated['company_id'])) {
            $query->where('company_id', (int) $validated['company_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $rows = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($rows->items())->map(function (Invoice $invoice): array {
                return [
                    'renewalPeriodKey' => $invoice->renewal_period_key,
                    'invoice' => [
                        'id' => $invoice->id,
                        'uuid' => $invoice->uuid,
                        'number' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date,
                        'dueDate' => $invoice->due_date,
                        'amountDue' => $invoice->amount_due,
                        'status' => $invoice->status,
                        'isPaid' => (bool) $invoice->is_paid,
                    ],
                    'company' => [
                        'id' => $invoice->company?->id,
                        'uuid' => $invoice->company?->uuid,
                        'code' => $invoice->company?->code,
                        'name' => $invoice->company?->name,
                    ],
                    'subscription' => [
                        'id' => $invoice->subscription?->id,
                        'uuid' => $invoice->subscription?->uuid,
                        'status' => $invoice->subscription?->status,
                        'billingCycle' => $invoice->subscription?->billing_cycle,
                        'graceStartedAt' => $invoice->subscription?->grace_started_at,
                        'graceEndsAt' => $invoice->subscription?->grace_ends_at,
                    ],
                    'reason' => [
                        'code' => $invoice->renewal_reason_code,
                        'message' => $invoice->renewal_reason_message,
                    ],
                ];
            })->values(),
            'pagination' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/renewal-monitoring/records/{renewalPeriodKey}
     */
    public function show(Request $request, string $renewalPeriodKey): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('renewal_period_key', $renewalPeriodKey)
            ->with(['company:id,uuid,code,name', 'subscription:id,uuid,status'])
            ->latest('id')
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RENEWAL_RECORD_NOT_FOUND',
                    'message' => 'Renewal monitoring record not found.',
                ],
            ], 404);
        }

        $timeline = SubscriptionEvent::query()
            ->where('renewal_period_key', $renewalPeriodKey)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'event_type',
                'reason_code',
                'reason_message',
                'occurred_at',
                'invoice_id',
                'payment_id',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'renewalPeriodKey' => $renewalPeriodKey,
                'company' => [
                    'id' => $invoice->company?->id,
                    'uuid' => $invoice->company?->uuid,
                    'code' => $invoice->company?->code,
                    'name' => $invoice->company?->name,
                ],
                'subscription' => [
                    'id' => $invoice->subscription?->id,
                    'uuid' => $invoice->subscription?->uuid,
                    'status' => $invoice->subscription?->status,
                ],
                'invoice' => [
                    'id' => $invoice->id,
                    'uuid' => $invoice->uuid,
                    'number' => $invoice->invoice_number,
                    'issueDate' => $invoice->issue_date,
                    'dueDate' => $invoice->due_date,
                    'amountDue' => $invoice->amount_due,
                    'status' => $invoice->status,
                    'isPaid' => (bool) $invoice->is_paid,
                ],
                'reason' => [
                    'code' => $invoice->renewal_reason_code,
                    'message' => $invoice->renewal_reason_message,
                ],
                'timeline' => $timeline,
            ],
        ]);
    }

    /**
     * GET /v1/saas/renewal-monitoring/anomalies
     */
    public function anomalies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $windowDays = (int) ($validated['days'] ?? 30);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $rows = $this->renewalInvoiceBaseQuery($windowDays)
            ->whereIn('renewal_reason_code', $this->anomalyReasonCodes())
            ->with(['company:id,uuid,code,name'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($rows->items())->map(function (Invoice $invoice): array {
                return [
                    'renewalPeriodKey' => $invoice->renewal_period_key,
                    'invoiceId' => $invoice->id,
                    'invoiceUuid' => $invoice->uuid,
                    'company' => [
                        'id' => $invoice->company?->id,
                        'uuid' => $invoice->company?->uuid,
                        'code' => $invoice->company?->code,
                        'name' => $invoice->company?->name,
                    ],
                    'reasonCode' => $invoice->renewal_reason_code,
                    'reasonMessage' => $invoice->renewal_reason_message,
                    'issueDate' => $invoice->issue_date,
                    'dueDate' => $invoice->due_date,
                    'isPaid' => (bool) $invoice->is_paid,
                ];
            })->values(),
            'pagination' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    private function renewalInvoiceBaseQuery(int $windowDays)
    {
        return Invoice::query()
            ->whereNotNull('renewal_period_key')
            ->whereDate('issue_date', '>=', now()->subDays($windowDays)->toDateString());
    }

    /**
     * @return array<int, string>
     */
    private function anomalyReasonCodes(): array
    {
        return [
            'MIDTRANS_DOWN',
            'GATEWAY_DOWN',
            'FEATURE_CRASH',
            'RENEWAL_WORKER_CRASHED',
            'RENEWAL_PROCESS_EXCEPTION',
            'STALE_INVOICE_DETECTED',
            'MIDTRANS_PAYMENT_FAILED',
            'MIDTRANS_INVOICE_EXPIRED',
        ];
    }
}
