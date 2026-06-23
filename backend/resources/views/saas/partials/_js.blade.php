@if(config('services.midtrans.client_key'))
@php $midtransSnapUrl = config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js'; @endphp
<script src="{{ $midtransSnapUrl }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
@endif
<script src="{{ asset('build/js/saas/subscription-checkout.js') }}?v={{ file_exists(public_path('build/js/saas/subscription-checkout.js')) ? filemtime(public_path('build/js/saas/subscription-checkout.js')) : time() }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pkgCards = document.getElementById('checkout-package-cards');
    var pkgSelect = document.getElementById('checkout_package_select');
    var checkoutModal = document.getElementById('checkout-checkout-modal');
    var rootEl = document.querySelector('[data-subscription-checkout-page]');
    var isUpsellMode = rootEl && rootEl.getAttribute('data-checkout-active-only') === '1';
    if (!pkgCards) return;

    // Click card → show checkout modal
    pkgCards.addEventListener('click', function (e) {
        var card = e.target.closest('.co-pkg-card');
        if (!card) return;
        if (isUpsellMode) {
            window.location.href = '/upgrade?selected=' + encodeURIComponent(card.dataset.packageId);
            return;
        }

        // Set package select
        var id = card.dataset.packageId;
        if (pkgSelect && pkgSelect.querySelector('option[value="' + id + '"]')) {
            pkgSelect.value = id;
        }

        // Show package name in modal
        var nameEl = document.getElementById('checkout-modal-pkg-name');
        if (nameEl) {
            nameEl.textContent = (card.querySelector('.co-pkg-name') || {}).textContent || 'Paket';
        }

        // Populate email display (readonly) from hidden input set by main JS
        var emailDisplay = document.getElementById('modal_billing_email_display');
        var emailHidden = document.getElementById('modal_billing_email_hidden');
        if (emailDisplay && emailHidden) {
            var email = emailHidden.value || '';
            if (!email && window.AuthApi) {
                try { email = window.AuthApi.getUser().email || ''; } catch(e) {}
            }
            if (!email && window.AuthUser) {
                email = window.AuthUser.email || '';
            }
            emailDisplay.textContent = email || '—';
            emailHidden.value = email;
        }

        // Open checkout modal
        if (checkoutModal) {
            try {
                var modal = new bootstrap.Modal(checkoutModal);
                modal.show();
            } catch(e) {}
        }
    });

    // Close checkout modal when form submits (before success modal appears)
    var checkoutForm = checkoutModal ? checkoutModal.querySelector('form[data-checkout-form]') : null;
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function () {
            try {
                var m = bootstrap.Modal.getInstance(checkoutModal);
                if (m) m.hide();
            } catch(e) {}
        });
    }

    // Billing cycle in modal → update card prices on page
    var modalCycleRadios = checkoutModal ? checkoutModal.querySelectorAll("input[name='billing_cycle']") : [];
    if (modalCycleRadios.length) {
        modalCycleRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                pkgCards.querySelectorAll('.co-pkg-card').forEach(function (card) {
                    var priceEl = card.querySelector('.co-pkg-price-num');
                    if (!priceEl) return;
                    var monthly = parseFloat(priceEl.dataset.priceMonthly) || 0;
                    var yearly = parseFloat(priceEl.dataset.priceYearly) || 0;
                    var checked = checkoutModal.querySelector("input[name='billing_cycle']:checked");
                    var cycle = checked ? checked.value : 'monthly';
                    var price = cycle === 'yearly' ? yearly : monthly;
                    var unit = cycle === 'yearly' ? '/ tahun' : '/ bulan';
                    priceEl.textContent = 'Rp ' + price.toLocaleString('id-ID');
                    var unitEl = card.querySelector('.co-pkg-price-unit');
                    if (unitEl) unitEl.textContent = unit;
                    var badge = card.querySelector('.co-pkg-yearly-badge');
                    if (badge) {
                        var savings = parseFloat(badge.dataset.yearlySavings) || 0;
                        badge.style.display = (cycle === 'yearly' && savings > 0) ? '' : 'none';
                    }
                });
            });
        });
    }
});
</script>
