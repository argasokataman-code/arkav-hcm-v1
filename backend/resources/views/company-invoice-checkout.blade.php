@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content">
            <!-- Header -->
            <div class="mb-5">
                <h4 class="text-muted">Billing / Checkout</h4>
                <h1 class="display-5 fw-bold">Konfirmasi Pembayaran</h1>
            </div>

            <!-- Invoice Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-5">
                    <!-- Invoice Header -->
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="text-uppercase text-danger fw-bold mb-3">Invoice Pending</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Invoice Number</small>
                                <h5 class="fw-bold">{{ $invoice->invoice_number }}</h5>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Due Date</small>
                                <h5 class="fw-bold">{{ $invoice->due_date->format('d M Y') }}</h5>
                            </div>
                        </div>
                    </div>

                    <!-- Company Info -->
                    <div class="mb-4 pb-4 border-bottom">
                        <small class="text-muted text-uppercase d-block mb-2">Company</small>
                        <h6 class="fw-bold">{{ $company->name }}</h6>
                        <small class="text-muted">Code: {{ $company->code }}</small>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="mb-4 pb-4 border-bottom">
                        <h6 class="fw-bold text-uppercase mb-3">Price Breakdown</h6>
                        
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <div>
                                <small class="text-muted">Subscription / Invoice</small>
                                <p class="fw-bold mb-0">Base Amount</p>
                            </div>
                            <div class="text-end">
                                <p class="fw-bold mb-0">Rp {{ number_format($baseAmount, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        @if($taxRate > 0)
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <div>
                                <small class="text-muted">Tax / Pajak</small>
                                <p class="fw-bold mb-0">{{ $taxRate }}%</p>
                            </div>
                            <div class="text-end">
                                <p class="fw-bold mb-0">Rp {{ number_format($taxAmount, 0, ',', '.') }}</p>
                            </div>
                        </div>
                        @endif

                        <!-- Total -->
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase">Total Amount Due</small>
                                <h5 class="fw-bold mb-0">Total Pembayaran</h5>
                            </div>
                            <div class="text-end">
                                <h4 class="fw-bold mb-0 text-danger">Rp {{ number_format($totalAmount, 0, ',', '.') }}</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods Info -->
                    <div class="alert alert-info" role="alert">
                        <small>
                            <strong>Metode Pembayaran Tersedia:</strong><br>
                            Bank Transfer • E-Wallet • Kartu Kredit • QR Code (QRIS) • Direct Debit
                        </small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-between mt-4">
                        <a href="{{ route('subscription') }}" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                        <button type="button" class="btn btn-danger btn-lg" id="proceedBtn" data-invoice-id="{{ $invoice->id }}">
                            <i class="fas fa-credit-card me-2"></i> Lanjut ke Pembayaran
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div id="loadingState" class="text-center mt-4" style="display: none;">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memproses permintaan pembayaran...</p>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="alert alert-secondary" role="alert">
                <small>
                    <i class="fas fa-lock me-2"></i>
                    <strong>Keamanan:</strong> Transaksi Anda dienkripsi dan diproses oleh Xendit.
                    Kami tidak menyimpan data kartu kredit Anda.
                </small>
            </div>
        </div>
    </div>
</div>

<style>
    .display-5 {
        font-size: 2.5rem;
    }
    
    .card {
        border-radius: 12px;
    }
    
    .btn-lg {
        padding: 12px 32px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
    }
    
    .btn-danger {
        background-color: #FF6B35;
        border-color: #FF6B35;
    }
    
    .btn-danger:hover {
        background-color: #E55A2B;
        border-color: #E55A2B;
    }
</style>

<script>
document.getElementById('proceedBtn').addEventListener('click', async function() {
    const invoiceId = this.getAttribute('data-invoice-id');
    const loadingState = document.getElementById('loadingState');
    const proceedBtn = document.getElementById('proceedBtn');
    
    proceedBtn.disabled = true;
    loadingState.style.display = 'block';
    
    try {
        // Call checkout API to get Xendit hosted URL
        const response = await fetch(`/api/v1/hcm/billing/invoices/${invoiceId}/mock-hosted-checkout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                paymentMethod: 'bank_transfer',
            })
        });
        
        if (!response.ok) {
            throw new Error('Gagal membuat sesi pembayaran');
        }
        
        const data = await response.json();
        
        if (data.success && data.flow?.hostedCheckoutUrl) {
            // Redirect to Xendit hosted checkout
            window.location.href = data.flow.hostedCheckoutUrl;
        } else {
            throw new Error(data.error?.message || 'Gagal memproses pembayaran');
        }
    } catch (error) {
        loadingState.style.display = 'none';
        proceedBtn.disabled = false;
        alert('Error: ' + error.message);
    }
});
</script>
@endsection
