import { beforeEach, describe, expect, it, vi } from 'vitest';

// Simulate the inline JS from _js.blade.php
function bootInlineJs() {
    var pkgCards = document.getElementById('checkout-package-cards');
    var pkgSelect = document.getElementById('checkout_package_select');
    var confirmModal = document.getElementById('checkout-confirm-modal');
    var confirmBtn = document.getElementById('checkout-confirm-btn');
    var rootEl = document.querySelector('[data-subscription-checkout-page]');
    var isUpsellMode = rootEl && rootEl.getAttribute('data-checkout-active-only') === '1';
    if (!pkgCards) return;

    var selectedPkgId = null;
    window.__selectedPkgId = function () { return selectedPkgId; };

    function getBillingCycle() {
        var checked = document.querySelector("input[name='billing_cycle']:checked");
        return checked ? checked.value : 'monthly';
    }

    pkgCards.addEventListener('click', function (e) {
        var card = e.target.closest('.co-pkg-card');
        if (!card) return;
        if (isUpsellMode) {
            window.location.href = '/upgrade?selected=' + encodeURIComponent(card.dataset.packageId);
            return;
        }
        selectedPkgId = card.dataset.packageId;
        var body = document.getElementById('checkout-confirm-body');
        if (body) body.innerHTML = 'test';
        var modal = document.getElementById('checkout-confirm-modal');
        if (modal) modal.classList.remove('d-none');
    });

    if (confirmBtn && pkgSelect) {
        confirmBtn.addEventListener('click', function () {
            if (selectedPkgId && pkgSelect.querySelector('option[value="' + selectedPkgId + '"]')) {
                pkgSelect.value = selectedPkgId;
                pkgSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    var cycleRadios = document.querySelectorAll("input[name='billing_cycle']");
    if (cycleRadios.length) {
        cycleRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                pkgCards.querySelectorAll('.co-pkg-card').forEach(function (card) {
                    var priceEl = card.querySelector('.co-pkg-price-num');
                    if (!priceEl) return;
                    var monthly = parseFloat(priceEl.dataset.priceMonthly) || 0;
                    var yearly = parseFloat(priceEl.dataset.priceYearly) || 0;
                    var billingCycle = getBillingCycle();
                    var price = billingCycle === 'yearly' ? yearly : monthly;
                    var unit = billingCycle === 'yearly' ? '/ tahun' : '/ bulan';
                    priceEl.textContent = 'Rp ' + price.toLocaleString('id-ID');
                    var unitEl = card.querySelector('.co-pkg-price-unit');
                    if (unitEl) unitEl.textContent = unit;
                    var badge = card.querySelector('.co-pkg-yearly-badge');
                    if (badge) {
                        var savings = parseFloat(badge.dataset.yearlySavings) || 0;
                        badge.style.display = (billingCycle === 'yearly' && savings > 0) ? '' : 'none';
                    }
                });
            });
        });
    }
}

function baseDom(overrides) {
    document.body.innerHTML = `
    <div data-subscription-checkout-page data-checkout-active-only="${overrides.activeOnly || '0'}">
      <div id="checkout-package-cards">
        <div class="co-pkg-card" data-package-id="pkg-a" data-package-code="starter">
          <div class="co-pkg-name">Starter</div>
          <div class="co-pkg-card-body">
            <div class="co-pkg-price">
              <span class="co-pkg-price-num" data-price-monthly="199000" data-price-yearly="1990000">Rp 199.000</span>
              <span class="co-pkg-price-unit">/ bulan</span>
              <span class="co-pkg-yearly-badge" data-yearly-savings="17">Hemat 17%</span>
            </div>
          </div>
        </div>
        <div class="co-pkg-card" data-package-id="pkg-b" data-package-code="business">
          <div class="co-pkg-name">Business</div>
          <div class="co-pkg-card-body">
            <div class="co-pkg-price">
              <span class="co-pkg-price-num" data-price-monthly="699000" data-price-yearly="6990000">Rp 699.000</span>
              <span class="co-pkg-price-unit">/ bulan</span>
              <span class="co-pkg-yearly-badge" data-yearly-savings="17">Hemat 17%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <input type="radio" name="billing_cycle" value="monthly" checked>
    <input type="radio" name="billing_cycle" value="yearly">
    <div id="checkout-confirm-modal" class="d-none">
      <div id="checkout-confirm-body"></div>
      <button id="checkout-confirm-btn">Ya, Lanjutkan</button>
    </div>
  `;
}

describe('package cards interaction', () => {
    beforeEach(() => {
        vi.resetModules();
        delete window.location;
        window.location = { href: '/subscription' };
    });

    it('clicking card shows confirm modal (checkout mode)', () => {
        baseDom({ activeOnly: '0' });
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        select.innerHTML = '<option value="">Pilih</option><option value="pkg-b">Business</option>';
        document.body.appendChild(select);

        bootInlineJs();
        document.querySelector('[data-package-id="pkg-b"]').click();

        var modal = document.getElementById('checkout-confirm-modal');
        expect(modal.classList.contains('d-none')).toBe(false);
    });

    it('confirm button sets select value', () => {
        baseDom({ activeOnly: '0' });
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        select.innerHTML = '<option value="">Pilih</option><option value="pkg-b">Business</option>';
        document.body.appendChild(select);

        bootInlineJs();
        document.querySelector('[data-package-id="pkg-b"]').click();

        var confirmBtn = document.getElementById('checkout-confirm-btn');
        confirmBtn.click();

        expect(select.value).toBe('pkg-b');
    });

    it('does not redirect in checkout mode', () => {
        baseDom({ activeOnly: '0' });
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        select.innerHTML = '<option value="">Pilih</option><option value="pkg-a">Starter</option>';
        document.body.appendChild(select);

        bootInlineJs();
        document.querySelector('[data-package-id="pkg-a"]').click();

        expect(window.location.href).not.toContain('/upgrade');
    });

    it('redirects in upsell mode', () => {
        baseDom({ activeOnly: '1' });
        bootInlineJs();
        document.querySelector('[data-package-id="pkg-b"]').click();

        expect(window.location.href).toBe('/upgrade?selected=pkg-b');
    });

    it('shows yearly price and savings badge when yearly selected', () => {
        baseDom({});
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        document.body.appendChild(select);
        bootInlineJs();

        var yearlyRadio = document.querySelector('input[name="billing_cycle"][value="yearly"]');
        yearlyRadio.checked = true;
        yearlyRadio.dispatchEvent(new Event('change', { bubbles: true }));

        expect(document.querySelector('.co-pkg-price-num').textContent).toContain('1.990.000');
        var badges = document.querySelectorAll('.co-pkg-yearly-badge');
        expect(badges[0].style.display).not.toBe('none');
    });

    it('hides yearly savings badge when monthly selected', () => {
        baseDom({});
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        document.body.appendChild(select);

        // Start with yearly
        document.querySelector('input[value="yearly"]').checked = true;
        bootInlineJs();

        // Switch to monthly
        var monthlyRadio = document.querySelector('input[name="billing_cycle"][value="monthly"]');
        monthlyRadio.checked = true;
        monthlyRadio.dispatchEvent(new Event('change', { bubbles: true }));

        var badges = document.querySelectorAll('.co-pkg-yearly-badge');
        badges.forEach(function (b) { expect(b.style.display).toBe('none'); });
    });

    it('updates price unit label on cycle change', () => {
        baseDom({});
        var select = document.createElement('select');
        select.id = 'checkout_package_select';
        document.body.appendChild(select);
        bootInlineJs();

        var yearlyRadio = document.querySelector('input[name="billing_cycle"][value="yearly"]');
        yearlyRadio.checked = true;
        yearlyRadio.dispatchEvent(new Event('change', { bubbles: true }));

        var units = document.querySelectorAll('.co-pkg-price-unit');
        units.forEach(function (u) { expect(u.textContent).toBe('/ tahun'); });
    });
});
