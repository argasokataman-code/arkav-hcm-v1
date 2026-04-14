<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * GET /v1/saas/transactions
     * List all transactions (admin only, with filtering and search)
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
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

    /**
     * GET /v1/saas/transactions/{id}
     * Get transaction details
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $transaction->load(['subscription' => function ($q) {
            $q->with(['company', 'package']);
        }]);

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

    /**
     * PUT /v1/saas/transactions/{id}
     * Update transaction (admin only — typically status updates)
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

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

    /**
     * Helper: Check if user is HCM Admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        return $user->isHcmAdmin() || ($user->is_super_admin ?? false);
    }
}
