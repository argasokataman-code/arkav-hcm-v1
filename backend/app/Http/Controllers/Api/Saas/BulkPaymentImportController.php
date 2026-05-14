<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Csv\Reader;

class BulkPaymentImportController extends Controller
{
    /**
     * POST /v1/saas/payments/bulk-upload
     * Import payments from CSV file
     */
    public function upload(Request $request): JsonResponse
    {
        // Validate admin access
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_REQUIRED',
                    'message' => 'Admin access required.',
                ],
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:csv,txt|max:5120',
        ]);

        try {
            $file = $request->file('file');
            $csv = Reader::createFromPath($file->path(), 'r');
            $csv->setHeaderOffset(0); // First row is headers

            $results = [
                'imported' => 0,
                'failed' => 0,
                'errors' => [],
                'warnings' => [],
            ];

            // Expected headers: invoice_id, company_id, amount, currency, payment_method, gateway, gateway_reference
            foreach ($csv->getRecords() as $row) {
                $result = $this->importRow($row);

                if ($result['success']) {
                    $results['imported']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = $result['error'];
                }

                if (isset($result['warning'])) {
                    $results['warnings'][] = $result['warning'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to process file: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import a single row from CSV
     */
    private function importRow(array $row): array
    {
        try {
            // Validate required fields
            $required = ['invoice_id', 'amount', 'payment_method'];
            foreach ($required as $field) {
                if (empty($row[$field] ?? null)) {
                    return ['success' => false, 'error' => "Missing required field: $field"];
                }
            }

            $invoiceId = $row['invoice_id'];
            $invoice = Invoice::find($invoiceId);

            if (!$invoice) {
                return ['success' => false, 'error' => "Invoice $invoiceId not found"];
            }

            if ($invoice->is_paid) {
                return [
                    'success' => false,
                    'error' => "Invoice $invoiceId already marked as paid",
                ];
            }

            // Create payment record
            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'purchase_transaction_id' => $invoice->purchase_transaction_id,
                'invoice_id' => $invoiceId,
                'amount' => (float) $row['amount'],
                'currency' => $row['currency'] ?? 'IDR',
                'status' => 'completed',
                'payment_method' => $row['payment_method'],
                'gateway' => $row['gateway'] ?? 'manual',
                'gateway_reference' => $row['gateway_reference'] ?? null,
                'paid_at' => now(),
                'verified_at' => now(),
                'metadata' => [
                    'bulk_import' => true,
                    'import_date' => now()->toDateString(),
                    'notes' => $row['notes'] ?? null,
                ],
            ]);

            // Check if invoice should be marked as paid
            $totalPaid = Payment::where('invoice_id', $invoiceId)
                ->sum('amount');

            if ($totalPaid >= $invoice->amount_due) {
                $invoice->markAsPaid();
            }

            return ['success' => true, 'payment_id' => $payment->id];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->isGlobalHcmAdmin();
    }
}
