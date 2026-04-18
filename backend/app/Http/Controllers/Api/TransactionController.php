<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseTransaction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * GET /v1/saas/transactions
     * List all transactions (admin only, with filtering and search)
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($this->usesPurchaseTransactionContract($request)) {
            return $this->purchaseIndex($request);
        }

        $query = Transaction::with(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }]);

        // Filter by status
        if ($request->has('status') && $request->get('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->get('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Filter by date (from)
        if ($request->has('date_from') && $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        // Filter by date (to)
        if ($request->has('date_to') && $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Search by invoice number
        if ($request->has('invoice_number') && $request->get('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->get('invoice_number') . '%');
        }

        // Search by company name (join via subscription->company)
        if ($request->has('company_search') && $request->get('company_search')) {
            $query->whereHas('subscription.company', function ($q) {
                $q->where('name', 'like', '%' . $request->get('company_search') . '%');
            });
        }

        $transactions = $query
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $items = collect($transactions->items())
            ->map(fn(Transaction $txn) => $this->formatTransaction($txn))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    private function purchaseIndex(Request $request): JsonResponse
    {
        $query = PurchaseTransaction::query()->with(['company', 'subscription', 'packageAddon']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->string('transaction_type'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->string('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->string('to_date'));
        }

        $transactions = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => collect($transactions->items())
                ->map(fn (PurchaseTransaction $transaction) => $this->formatPurchaseTransaction($transaction))
                ->values(),
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
    public function show(Request $request, string $transaction): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($this->usesPurchaseTransactionContract($request)) {
            $model = PurchaseTransaction::query()->with(['company', 'subscription', 'packageAddon'])->findOrFail((int) $transaction);

            return response()->json([
                'success' => true,
                'data' => $this->formatPurchaseTransaction($model),
            ]);
        }

        $model = Transaction::query()->with(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }])->findOrFail((int) $transaction);

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($model),
        ]);
    }

    /**
     * POST /v1/saas/transactions
     * Create transaction (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($this->usesPurchaseTransactionContract($request)) {
            return $this->storePurchaseTransaction($request);
        }

        $validated = $request->validate([
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'invoice_number' => 'required|string|unique:transactions|max:50',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:pending,completed,failed,refunded',
            'payment_method' => 'required|in:credit_card,bank_transfer,e_wallet,other',
            'payment_gateway' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $transaction = Transaction::create($validated);
        $transaction->load(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ], 201);
    }

    private function storePurchaseTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'package_addon_id' => 'nullable|integer|exists:package_addons,id',
            'transaction_type' => ['required', Rule::in(['subscription', 'addon', 'refund', 'credit', 'manual'])],
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => ['required', Rule::in(['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled'])],
        ]);

        if (($validated['transaction_type'] ?? null) === 'addon' && empty($validated['package_addon_id'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_ADDON_REQUIRED',
                    'message' => 'package_addon_id is required when transaction_type is addon.',
                ],
            ], 422);
        }

        $validated['transaction_code'] = PurchaseTransaction::generateCode();
        $validated['total_amount'] = (float) $validated['amount']
            + (float) ($validated['tax_amount'] ?? 0)
            - (float) ($validated['discount_amount'] ?? 0);

        $transaction = PurchaseTransaction::query()->create($validated);
        $transaction->load(['company', 'subscription', 'packageAddon']);

        return response()->json([
            'success' => true,
            'data' => $this->formatPurchaseTransaction($transaction),
        ], 201);
    }

    /**
     * PUT /v1/saas/transactions/{id}
     * Update transaction (admin only — typically status updates)
     */
    public function update(Request $request, string $transaction): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($this->usesPurchaseTransactionContract($request)) {
            return $this->updatePurchaseTransaction($request, (int) $transaction);
        }

        return $this->updateLegacyTransaction($request, (int) $transaction);
    }

    private function updateLegacyTransaction(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::query()->with(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }])->findOrFail($transactionId);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,completed,failed,refunded',
            'payment_method' => 'sometimes|in:credit_card,bank_transfer,e_wallet,other',
            'payment_gateway' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $transaction->update($validated);
        $transaction->load(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }]);

        return response()->json([
            'success' => true,
            'data' => $this->formatTransaction($transaction),
        ]);
    }

    private function updatePurchaseTransaction(Request $request, int $transactionId): JsonResponse
    {
        $transaction = PurchaseTransaction::query()->with(['company', 'subscription', 'packageAddon'])->findOrFail($transactionId);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled'])],
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|in:bank_transfer,credit_card,e_wallet,cash',
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $transaction->update($validated);
        $transaction->load(['company', 'subscription', 'packageAddon']);

        return response()->json([
            'success' => true,
            'data' => $this->formatPurchaseTransaction($transaction),
        ]);
    }

    /**
     * GET /v1/saas/transactions/export
     * Export transactions to CSV (admin only)
     */
    public function export(Request $request)
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $transactions = Transaction::with(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Build CSV content
        $csv = "Invoice Number,Company,Subscription,Amount,Status,Payment Method,Date\n";
        foreach ($transactions as $txn) {
            $companyName = $txn->subscription?->company?->name ?? 'N/A';
            $packageName = $txn->subscription?->package?->name ?? 'N/A';
            $csv .= "\"{$txn->invoice_number}\",\"{$companyName}\",\"{$packageName}\"," .
                    "{$txn->amount},{$txn->status},{$txn->payment_method}," .
                    "{$txn->created_at->format('Y-m-d H:i:s')}\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions-export-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Format transaction for API response
     */
    private function formatTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'invoiceNumber' => $transaction->invoice_number,
            'subscriptionId' => $transaction->subscription_id,
            'companyName' => $transaction->subscription?->company?->name ?? '-',
            'packageName' => $transaction->subscription?->package?->name ?? '-',
            'amount' => (float)$transaction->amount,
            'status' => $transaction->status,
            'paymentMethod' => $transaction->payment_method,
            'paymentGateway' => $transaction->payment_gateway,
            'transactionId' => $transaction->transaction_id,
            'notes' => $transaction->notes,
            'createdAt' => $transaction->created_at->toIso8601String(),
            'updatedAt' => $transaction->updated_at->toIso8601String(),
        ];
    }

    private function formatPurchaseTransaction(PurchaseTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'transactionCode' => $transaction->transaction_code,
            'companyId' => $transaction->company_id,
            'company' => [
                'id' => $transaction->company?->id,
                'code' => $transaction->company?->code,
                'name' => $transaction->company?->name,
            ],
            'subscriptionId' => $transaction->subscription_id,
            'subscription' => $transaction->subscription ? [
                'id' => $transaction->subscription->id,
                'planCode' => $transaction->subscription->plan_code,
                'status' => $transaction->subscription->status,
            ] : null,
            'packageAddonId' => $transaction->package_addon_id,
            'packageAddon' => $transaction->packageAddon ? [
                'id' => $transaction->packageAddon->id,
                'code' => $transaction->packageAddon->code,
                'name' => $transaction->packageAddon->name,
            ] : null,
            'transactionType' => $transaction->transaction_type,
            'description' => $transaction->description,
            'amount' => (float) $transaction->amount,
            'taxAmount' => (float) $transaction->tax_amount,
            'discountAmount' => (float) $transaction->discount_amount,
            'totalAmount' => (float) $transaction->total_amount,
            'billingPeriodStart' => $transaction->billing_period_start?->toDateString(),
            'billingPeriodEnd' => $transaction->billing_period_end?->toDateString(),
            'dueDate' => $transaction->due_date?->toIso8601String(),
            'paidAt' => $transaction->paid_at?->toIso8601String(),
            'paymentMethod' => $transaction->payment_method,
            'paymentReference' => $transaction->payment_reference,
            'status' => $transaction->status,
            'isPaid' => $transaction->isPaid(),
            'isOverdue' => $transaction->isOverdue(),
            'notes' => $transaction->notes,
            'createdAt' => $transaction->created_at->toIso8601String(),
            'updatedAt' => $transaction->updated_at->toIso8601String(),
        ];
    }

    /**
     * Helper: Check if user is HCM Admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }

    private function usesPurchaseTransactionContract(Request $request): bool
    {
        return trim((string) $request->bearerToken()) !== '';
    }
}
