<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $query = Subscription::query()
            ->with([
                'company',
                'package',
                'latestInvoice.latestEmailLog',
            ]);

        if ($tab === 'trial') {
            $query->where('status', 'trial');
        } else {
            $query->whereIn('status', ['active', 'pending_payment']);
        }

        if ($search !== '') {
            $query->whereHas('company', function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $subscriptions = $query->latest('created_at')->paginate($perPage);

        $rows = collect($subscriptions->items())
            ->map(function (Subscription $subscription): array {
                $company = $subscription->company;
                $package = $subscription->package;
                $invoice = $subscription->latestInvoice;
                $emailLog = $invoice?->latestEmailLog;

                $emailStatus = 'not_sent';
                if ($emailLog) {
                    $emailStatus = $emailLog->status === 'sent' ? 'sent' : 'failed';
                }

                return [
                    'company' => [
                        'id' => $company?->id,
                        'code' => (string) ($company?->code ?? ''),
                        'name' => (string) ($company?->name ?? ''),
                    ],
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => (string) $subscription->status,
                        'billingCycle' => (string) $subscription->billing_cycle,
                        'startsAt' => $subscription->starts_at,
                        'endsAt' => $subscription->ends_at,
                        'trialEndsAt' => $subscription->trial_ends_at,
                        'planCode' => (string) $subscription->plan_code,
                        'packageId' => $package?->id,
                        'packageName' => (string) ($package?->name ?? ''),
                        'amount' => $subscription->amount,
                    ],
                    'latestInvoice' => $invoice ? [
                        'id' => $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date,
                        'dueDate' => $invoice->due_date,
                        'amountDue' => $invoice->amount_due,
                        'isPaid' => (bool) $invoice->is_paid,
                        'status' => (string) $invoice->status,
                    ] : null,
                    'email' => [
                        'status' => $emailStatus,
                        'sentAt' => $emailLog?->created_at,
                        'lastError' => $emailLog?->error_message,
                    ],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }

    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}

