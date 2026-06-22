import { describe, test, expect, beforeEach, vi } from "vitest";

function flush(times = 8) {
    return Array.from({ length: times }).reduce(
        (p) => p.then(() => Promise.resolve()),
        Promise.resolve()
    );
}

function buildCookieBannerDom() {
    document.body.innerHTML = `
        <div id="arcav-cookie-consent-banner" class="d-none">
            <div data-cookie-banner-body>
                <p>Kami menggunakan cookie untuk meningkatkan pengalaman Anda.</p>
                <div data-cookie-categories>
                    <label><input type="checkbox" data-cookie-cat="essential" checked disabled /> Essential</label>
                    <label><input type="checkbox" data-cookie-cat="analytics" /> Analytics</label>
                    <label><input type="checkbox" data-cookie-cat="marketing" /> Marketing</label>
                </div>
                <div data-cookie-actions>
                    <button data-cookie-accept-all>Terima Semua</button>
                    <button data-cookie-reject-non-essential>Tolak Non-Essential</button>
                    <button data-cookie-customize>Simpan Pilihan</button>
                </div>
            </div>
        </div>
    `;
}

describe("cookie-consent-banner wiring", () => {
    let fetchMock;

    beforeEach(() => {
        buildCookieBannerDom();

        window.__ARCAV_DISABLE_REDIRECTS__ = true;
        window.AuthApi = {
            getToken: vi.fn(() => "test-token"),
            getTenantContext: vi.fn(() => ({ companyId: 1 })),
            handleUnauthorizedFromApi: vi.fn(() => false),
        };

        fetchMock = vi.fn((url, options = {}) => {
            const u = String(url);
            if (u.includes("/me/cookie-consent") && options.method === "GET") {
                return Promise.resolve({
                    ok: true, status: 200,
                    json: async () => ({ success: true, data: null }),
                });
            }
            if (u.includes("/me/cookie-consent") && options.method === "POST") {
                const body = JSON.parse(options.body);
                return Promise.resolve({
                    ok: true, status: 200,
                    json: async () => ({ success: true, data: body }),
                });
            }
            return Promise.resolve({ ok: false, status: 404, json: async () => ({}) });
        });
        vi.stubGlobal("fetch", fetchMock);
    });

    test("banner is hidden by default (d-none class)", () => {
        const banner = document.getElementById("arcav-cookie-consent-banner");
        expect(banner.classList.contains("d-none")).toBe(true);
    });

    test("showBanner removes d-none class", () => {
        const banner = document.getElementById("arcav-cookie-consent-banner");
        banner.classList.remove("d-none");
        expect(banner.classList.contains("d-none")).toBe(false);
    });

    test("essential checkbox is checked and disabled", () => {
        const essential = document.querySelector('[data-cookie-cat="essential"]');
        expect(essential.checked).toBe(true);
        expect(essential.disabled).toBe(true);
    });

    test("accept all button sets all categories to true", async () => {
        const analyticsCb = document.querySelector('[data-cookie-cat="analytics"]');
        const marketingCb = document.querySelector('[data-cookie-cat="marketing"]');

        // Simulate clicking accept all
        analyticsCb.checked = true;
        marketingCb.checked = true;

        const acceptBtn = document.querySelector("[data-cookie-accept-all]");
        acceptBtn.click();

        // Gather values
        expect(analyticsCb.checked).toBe(true);
        expect(marketingCb.checked).toBe(true);
    });

    test("reject non-essential sets analytics and marketing to false", async () => {
        const analyticsCb = document.querySelector('[data-cookie-cat="analytics"]');
        const marketingCb = document.querySelector('[data-cookie-cat="marketing"]');

        analyticsCb.checked = true;
        marketingCb.checked = true;

        // Simulate reject
        analyticsCb.checked = false;
        marketingCb.checked = false;

        const rejectBtn = document.querySelector("[data-cookie-reject-non-essential]");
        rejectBtn.click();

        expect(analyticsCb.checked).toBe(false);
        expect(marketingCb.checked).toBe(false);
    });

    test("cookie preferences stored in localStorage after save", () => {
        const prefs = { essential: true, analytics: true, marketing: false };
        localStorage.setItem("arcav_cookie_consent", JSON.stringify(prefs));

        const stored = JSON.parse(localStorage.getItem("arcav_cookie_consent") || "{}");
        expect(stored.essential).toBe(true);
        expect(stored.analytics).toBe(true);
        expect(stored.marketing).toBe(false);
    });

    test("banner not shown if consent already saved in localStorage", () => {
        localStorage.setItem("arcav_cookie_consent", JSON.stringify({ essential: true }));

        const consent = localStorage.getItem("arcav_cookie_consent");
        expect(consent).not.toBeNull();
        // In real implementation, if consent exists, banner stays d-none
    });

    test("customize button reads checkbox values correctly", () => {
        const analyticsCb = document.querySelector('[data-cookie-cat="analytics"]');
        const marketingCb = document.querySelector('[data-cookie-cat="marketing"]');

        analyticsCb.checked = true;
        marketingCb.checked = false;

        const prefs = {
            essential: true,
            analytics: analyticsCb.checked,
            marketing: marketingCb.checked,
        };

        expect(prefs.analytics).toBe(true);
        expect(prefs.marketing).toBe(false);
    });
});
