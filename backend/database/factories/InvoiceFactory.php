<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Company;
use App\Models\PurchaseTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issueDate = now()->startOfMonth();
        $dueDate = $issueDate->copy()->addDays(30);

        return [
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'company_id' => Company::factory(),
            'purchase_transaction_id' => PurchaseTransaction::factory(),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'amount_due' => $this->faker->randomFloat(2, 100000, 5000000),
            'is_paid' => $this->faker->boolean(),
            'paid_date' => $this->faker->optional()->dateTime(),
            'pdf_path' => $this->faker->optional()->url(),
            'status' => $this->faker->randomElement(['draft', 'sent', 'viewed', 'paid', 'expired']),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
