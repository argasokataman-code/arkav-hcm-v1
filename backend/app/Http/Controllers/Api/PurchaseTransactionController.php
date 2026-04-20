<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseTransactionController extends Controller
{
    /**
     * GET /v1/saas/transactions
     * List transactions with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseTransaction::with(['company', 'subscription', 'packageAddon']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->get('transaction_type'));
        }
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->get('to_date'));
        }

        $transactions = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/transactions/{id}
     * Get transaction details
     */
    public function show(PurchaseTransaction $transaction): JsonResponse
    {
        $transaction->load('company', 'subscription', 'packageAddon');

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ]);
    }

    /**
     * POST /v1/saas/transactions
     * Create transaction (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,uuid',
            'subscription_id' => 'nullable|uuid|exists:subscriptions,uuid',
            'package_addon_id' => 'nullable|uuid|exists:package_addons,uuid',
            'transaction_type' => 'required|in:subscription,addon,refund,credit,manual',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,issued,sent,paid,overdue,cancelled',
        ]);

        if (! empty($validated['package_addon_id'])) {
            $validated['package_addon_id'] = (int) (PackageAddon::query()
                ->where('uuid', (string) $validated['package_addon_id'])
                ->value('id') ?? 0);
        }

        if ($validated['transaction_type'] === 'addon' && empty($validated['package_addon_id'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_ADDON_REQUIRED',
                    'message' => 'package_addon_id is required when transaction_type is addon.',
                ],
            ], 422);
        }

        // Generate transaction code
        $validated['transaction_code'] = PurchaseTransaction::generateCode();

        // Calculate total
        $tax = $validated['tax_amount'] ?? 0;
        $discount = $validated['discount_amount'] ?? 0;
        $validated['total_amount'] = $validated['amount'] + $tax - $discount;

        $transaction = PurchaseTransaction::create($validated);
        $transaction->load('company', 'subscription', 'packageAddon');

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ], 201);
    }

    /**
     * PUT /v1/saas/transactions/{id}
     * Update transaction (admin only)
     */
    public function update(Request $request, PurchaseTransaction $transaction): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:draft,issued,sent,paid,overdue,cancelled',
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|in:bank_transfer,credit_card,e_wallet,cash',
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $transaction->update($validated);
        $transaction->load('company', 'subscription', 'packageAddon');

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ]);
    }

    /**
     * Format transaction response
     */
    private function formatTransaction(PurchaseTransaction $t): array
    {
        return [
            'id' => $t->id,
            'transactionCode' => $t->transaction_code,
            'companyId' => $t->company_id,
            'company' => [
                'id' => $t->company->id,
                'code' => $t->company->code,
                'name' => $t->company->name,
            ],
            'subscriptionId' => $t->subscription_id,
            'subscription' => $t->subscription ? [
                'id' => $t->subscription->id,
                'planCode' => $t->subscription->plan_code,
                'status' => $t->subscription->status,
            ] : null,
            'packageAddonId' => $t->package_addon_id,
            'packageAddon' => $t->packageAddon ? [
                'id' => $t->packageAddon->id,
                'code' => $t->packageAddon->code,
                'name' => $t->packageAddon->name,
            ] : null,
            'transactionType' => $t->transaction_type,
            'description' => $t->description,
            'amount' => (float)$t->amount,
            'taxAmount' => (float)$t->tax_amount,
            'discountAmount' => (float)$t->discount_amount,
            'totalAmount' => (float)$t->total_amount,
            'billingPeriodStart' => $t->billing_period_start?->toDateString(),
            'billingPeriodEnd' => $t->billing_period_end?->toDateString(),
            'dueDate' => $t->due_date?->toIso8601String(),
            'paidAt' => $t->paid_at?->toIso8601String(),
            'paymentMethod' => $t->payment_method,
            'paymentReference' => $t->payment_reference,
            'status' => $t->status,
            'isPaid' => $t->isPaid(),
            'isOverdue' => $t->isOverdue(),
            'notes' => $t->notes,
            'createdAt' => $t->created_at->toIso8601String(),
            'updatedAt' => $t->updated_at->toIso8601String(),
        ];
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }
}
