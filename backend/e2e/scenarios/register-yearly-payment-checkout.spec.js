import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { expect, test } from "@playwright/test";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const backendRoot = path.resolve(__dirname, "..", "..");

function seedLandingPackages() {
    execSync("php artisan db:seed --class=LandingPackagesSeeder --force", {
        cwd: backendRoot,
        stdio: "inherit",
        env: process.env,
    });
}

test.describe.serial("Yearly registration payment checkout", () => {
    test.beforeAll(() => {
        seedLandingPackages();
    });

    test("requires payment after yearly registration and activates subscription from checkout", async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            window.__ARCAV_E2E_TURNSTILE_TOKEN = "test-turnstile-token";
            window.turnstile = {
                render(container, options) {
                    if (container && typeof container.setAttribute === "function") {
                        container.setAttribute("data-turnstile-widget-id", "e2e-widget");
                    }
                    if (options && typeof options.callback === "function") {
                        options.callback("test-turnstile-token");
                    }
                    return "e2e-widget";
                },
                getResponse() {
                    return "test-turnstile-token";
                },
                reset() {},
                remove() {},
            };
        });

        const runId = [
            testInfo.project.name.replace(/[^a-z0-9]+/gi, "-").toLowerCase(),
            Date.now().toString(36),
            Math.random().toString(36).slice(2, 8),
        ].join("-");
        const ownerEmail = `pending.owner.${runId}@example.com`;
        const ownerPassword = "StrongPass1";
        const companyName = `Pending Pay ${runId}`;
        let companyCode = "";

        await test.step("1. Submit yearly registration from /register", async () => {
            // /register redirects to /landing?openOnboarding=1&startMode=pending_payment,
            // which auto-opens the unified React onboarding modal on the landing page.
            await page.goto("/register", { waitUntil: "domcontentloaded" });
            await page.waitForURL(/\/landing\?.*openOnboarding=1.*startMode=pending_payment/i, { timeout: 15000 });

            const onboardingModal = page.locator('[id="pricing-showroom-column"], [class*="OnboardingModal"], [data-onboarding-modal]').first();
            await expect(onboardingModal).toBeVisible({ timeout: 15000 });

            await onboardingModal.locator('select[name="packageUuid"]').selectOption({ label: 'Starter (starter)' });
            await onboardingModal.locator('select[name="billingCycle"]').selectOption('yearly');
            await expect(onboardingModal.locator('select[name="startMode"]')).toHaveValue('pending_payment');

            await onboardingModal.locator('input[name="companyName"]').fill(companyName);
            await onboardingModal.locator('textarea[name="companyAddress"]').fill('Jl. Jenderal Sudirman No. 10, Jakarta Selatan');
            await onboardingModal.locator('input[name="companyCity"]').fill('Jakarta Selatan');
            await onboardingModal.locator('input[name="companyPostalCode"]').fill('12190');
            await onboardingModal.locator('input[name="ownerName"]').fill('Nadia Pratama');
            await onboardingModal.locator('input[name="ownerEmail"]').fill(ownerEmail);
            await onboardingModal.locator('input[name="ownerPhone"]').fill('+6281234567890');
            await onboardingModal.locator('input[name="ownerPassword"]').fill(ownerPassword);
            await onboardingModal.locator('input[name="ownerConfirmPassword"]').fill(ownerPassword);

            page.on('dialog', async (dialog) => { await dialog.accept(); });

            const onboardingResponsePromise = page.waitForResponse((response) => response.url().includes('/v1/public/onboarding'));
            await onboardingModal.getByRole('button', { name: /Proses onboarding/i }).click();
            const onboardingResponse = await onboardingResponsePromise;
            expect(onboardingResponse.ok()).toBe(true);
            expect(onboardingResponse.status()).toBe(201);

            await page.waitForURL(/\/login\?mode=company.*next=%2Fsubscription/i, { timeout: 15000 });
            companyCode = String(new URL(page.url()).searchParams.get('companyCode') || '');
            expect(companyCode).not.toBe('');
            await expect(page.locator('#login_mode_company')).toBeChecked();
            await expect(page.locator('#login-company-code')).toHaveValue(companyCode);
        });

        await test.step("2. Login as company and land on checkout", async () => {
            await expect(page.locator('#login-company-code')).not.toHaveValue('');
            await page.locator('#login-email').fill(ownerEmail);
            await page.locator('#login-password').fill(ownerPassword);
            await page.locator('#login-submit').click();
            await page.waitForURL(/\/subscription(\?.*)?$/, { timeout: 20000 });

            await expect(page.locator('[data-subscription-checkout-page]')).toBeVisible();
            await expect(page.locator('[data-checkout-invoice-box]')).toBeVisible();
            await expect(page.locator('[data-checkout-invoice-title]')).toContainText(/invoice pending ditemukan/i);
            await expect(page.locator('[data-checkout-pay-now]')).toBeVisible();
        });

        await test.step("3. Open hosted payment gateway from checkout", async () => {
            await page.locator('[data-checkout-pay-now]').click();
            await page.waitForURL(/\/mock-hosted-payment\.html\?/);
            await expect(page.locator('#paymentUuidValue')).not.toHaveText('-');
            await expect(page.locator('#invoiceNumberValue')).not.toHaveText('-');

            await page.locator('#settleSuccessBtn').click();

            await page.waitForURL(/\/subscription\?mock_payment_status=completed.*invoice_id=/);
            await expect(page.locator('[data-checkout-feedback]')).toContainText(/hosted payment gateway mock/i);
            await expect(page.locator('[data-checkout-invoice-title]')).toContainText(/invoice sudah dibayar/i);
            await expect(page.locator('[data-checkout-go-dashboard]')).toBeVisible();
        });

        await test.step("4. Subscription is active after payment", async () => {
            await page.locator('[data-checkout-go-dashboard]').click();
            await page.waitForURL(/\/(index|dashboard)(\?.*)?$/);
            await expect(page.locator('.main-wrapper')).toHaveAttribute('data-subscription-status', 'active');
        });
    });
});