@component('mail::message')
# Payment Reminder

Dear {{ $company->name }},

@if($isOverdue)
    We noticed that invoice **{{ $invoice->invoice_number }}** is now **{{ $daysOverdue }} days overdue**.

    Please arrange payment at your earliest convenience to avoid any service interruptions.
@else
    This is a friendly reminder that invoice **{{ $invoice->invoice_number }}** is due on **{{ $invoice->due_date->format('Y-m-d') }}**.

    Please ensure payment is received by the due date.
@endif

**Invoice Details:**
- Invoice Number: {{ $invoice->invoice_number }}
- Amount Due: {{ number_format($invoice->amount_due, 2) }}
- Due Date: {{ $invoice->due_date->format('Y-m-d') }}

@component('mail::button', ['url' => config('app.url') . '/saas/payments'])
Make Payment
@endcomponent

If you have already processed this payment, please disregard this reminder. If you have any questions, please contact us.

Thank you!

{{ config('app.name') }}
@endcomponent
