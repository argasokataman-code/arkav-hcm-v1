INVOICE {{ $invoice->invoice_number }}

Dear {{ $company->name }},

We have issued an invoice for you. Please find the details below:

Invoice Number: {{ $invoice->invoice_number }}
Issue Date: {{ $invoice->issue_date->format('Y-m-d') }}
Due Date: {{ $invoice->due_date->format('Y-m-d') }}
Amount Due: {{ number_format($invoice->amount_due, 2) }}
Status: {{ ucfirst($invoice->status) }}
@if($invoice->notes)
Notes: {{ $invoice->notes }}
@endif

View Invoice: {{ config('app.url') }}/saas/invoices/{{ $invoice->id }}

If you have any questions about this invoice, please don't hesitate to contact us.

Thank you!

{{ $issuerName ?? config('app.name') }}
