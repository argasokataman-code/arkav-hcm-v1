import { execSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { expect, test } from "@playwright/test";
import { loginViaUi } from "../helpers/auth.js";

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

async function goToTrialPackageFromLanding(page) {
    await page.goto("/landing", { waitUntil: "domcontentloaded" });
    await expect(page.locator("[data-packages-grid]")).toBeVisible();

    const trialCard = page.locator(".landing-card", { hasText: /Trial/ }).first();
    await expect(trialCard).toBeVisible();
    await trialCard.getByRole("link", { name: "Pilih plan" }).click();

    await expect(page).toHaveURL(/\/trial\?packageId=/);
    await expect(page.locator("[data-onboarding-form]")).toBeVisible();
}

async function onboardTrialTenant(page, suffix) {
    const ownerEmail = `mock.tester.${suffix}@example.com`;
    const ownerPassword = "StrongPass1";
    const companyName = `Mock Tester Co ${suffix}`;
    const companyAddress = "Jl. Gatot Subroto No. 1, Jakarta";
    const companyCity = "Jakarta Selatan";
    const ownerName = "Bima Prasetya";

    await goToTrialPackageFromLanding(page);

    await page.evaluate(() => {
        const form = document.querySelector("#onboardingForm");
        if (!form) return;

        let tokenInput = form.querySelector('[name="cf-turnstile-response"]');
        if (!tokenInput) {
            tokenInput = document.createElement("input");
            tokenInput.type = "hidden";
            tokenInput.name = "cf-turnstile-response";
            form.appendChild(tokenInput);
        }

        tokenInput.value = "test-turnstile-token";
    });

    await page.locator('[name="company_name"]').fill(companyName);
    await page.locator('[name="company_address"]').fill(companyAddress);
    await page.locator('[name="company_city"]').fill(companyCity);
    await page.locator('[name="owner_name"]').fill(ownerName);
    await page.locator('[name="owner_email"]').fill(ownerEmail);
    await page.locator('[name="owner_password"]').fill(ownerPassword);
    await page.locator('[name="owner_confirm_password"]').fill(ownerPassword);

    const onboardingResponsePromise = page.waitForResponse((response) => {
        return response.url().includes("/v1/public/onboarding") && response.request().method() === "POST";
    });

    await page.getByRole("button", { name: "Daftarkan company" }).click();

    const onboardingResponse = await onboardingResponsePromise;
    const onboardingBody = await onboardingResponse.json();
    expect(onboardingResponse.ok(), JSON.stringify(onboardingBody, null, 2)).toBeTruthy();

    return {
        ownerEmail,
        ownerPassword,
        companyCode: onboardingBody.data.company.code,
    };
}

test.describe.serial("Mock payment tester HTML", () => {
    test.beforeAll(() => {
        seedLandingPackages();
    });

    test("quick pay failure can feed Pay Invoice UUID flow and complete payment", async ({ page }) => {
        const runId = `${Date.now().toString(36)}-instant`;
        let auth;

        await test.step("Onboard trial company and login", async () => {
            auth = await onboardTrialTenant(page, runId);
            await loginViaUi(
                page,
                {
                    email: auth.ownerEmail,
                    password: auth.ownerPassword,
                },
                {
                    companyMode: true,
                    companyCode: auth.companyCode,
                },
            );
        });

        await test.step("Open helper page with auth token and tenant context", async () => {
            await page.evaluate(() => {
                const accessToken = localStorage.getItem("arcav_access_token");
                if (accessToken) {
                    localStorage.setItem("auth_token", accessToken);
                }
            });

            await page.goto("/mock-payment-tester.html", { waitUntil: "domcontentloaded" });
            await expect(page.locator("#quickPayForm")).toBeVisible();
            await expect(page.locator("#payInvoiceForm")).toHaveCount(1);
        });

        await test.step("Create failed invoice via Quick Pay so invoice stays unpaid", async () => {
            await page.locator("#amount").fill("275000");
            await page.locator("#description").fill("Mock tester failure-first flow");
            await page.locator("#simulateFailure").check();
            await page.locator("#quickPayForm button[type='submit']").click();

            await expect(page.locator("#quickResult")).toBeVisible();
            const quickPayload = JSON.parse(await page.locator("#quickResult pre").textContent());

            expect(quickPayload.success).toBe(true);
            expect(quickPayload.data.invoice.uuid).toBeTruthy();
            expect(quickPayload.data.payment.status).toBe("failed");
            expect(quickPayload.data.invoice.status).not.toBe("paid");

            await expect(page.locator("#invoiceId")).toHaveValue(quickPayload.data.invoice.uuid);
            await expect(page.locator("#payAmount")).toHaveValue("275000.00");
        });

        await test.step("Pay the existing invoice by UUID using Pay Invoice tab", async () => {
            await page.locator('a[href="#tab-invoice"]').click();
            await page.locator('#payInvoiceForm button[type="submit"]').click();

            await expect(page.locator("#invoiceResult")).toBeVisible();
            const invoicePayload = JSON.parse(await page.locator("#invoiceResult pre").textContent());

            expect(invoicePayload.success).toBe(true);
            expect(invoicePayload.data.invoice.uuid).toBeTruthy();
            expect(invoicePayload.data.invoice.status).toBe("paid");
            expect(invoicePayload.data.payment.status).toBe("completed");
            expect(invoicePayload.data.payment.uuid).toBeTruthy();
        });
    });

    test("hosted flow can generate token, open hosted checkout, and redirect back completed", async ({ page }) => {
        const runId = `${Date.now().toString(36)}-hosted`;
        const auth = await onboardTrialTenant(page, runId);

        await test.step("Generate token from helper page", async () => {
            await page.goto("/mock-payment-tester.html", { waitUntil: "domcontentloaded" });

            await page.locator("#tokenEmail").fill(auth.ownerEmail);
            await page.locator("#tokenPassword").fill(auth.ownerPassword);
            await page.locator("#tokenCompanyCode").fill(auth.companyCode);
            await page.locator("#generateTokenBtn").click();

            await expect(page.locator("#tokenGeneratorResult")).toBeVisible();
            const tokenPayload = JSON.parse(await page.locator("#tokenGeneratorResult pre").textContent());

            expect(tokenPayload.success).toBe(true);
            expect(tokenPayload.data.accessToken).toBeTruthy();
            await expect(page.locator("#generatedToken")).not.toHaveValue("");
        });

        await test.step("Create hosted mock payment", async () => {
            await page.locator("#amount").fill("325000");
            await page.locator("#description").fill("Hosted mock flow");
            await page.locator("#quickFlowMode").selectOption("hosted");
            await page.locator("#quickPayForm button[type='submit']").click();

            await expect(page.locator("#quickResult")).toBeVisible();
            const quickPayload = JSON.parse(await page.locator("#quickResult pre").textContent());

            expect(quickPayload.success).toBe(true);
            expect(quickPayload.data.flow.mode).toBe("hosted");
            expect(quickPayload.data.payment.status).toBe("pending");
            expect(quickPayload.data.flow.hosted_checkout_url).toContain("mock-hosted-payment.html");
            expect(quickPayload.data.flow.callback_token).toBeTruthy();

            await expect(page.locator("#quickActions")).toHaveClass(/show/);
        });

        await test.step("Settle hosted payment and return to helper", async () => {
            await page.locator("#openHostedCheckout").click();
            await expect(page).toHaveURL(/mock-hosted-payment\.html/);
            await expect(page.locator("#paymentUuidValue")).not.toHaveText("-");

            await page.locator("#settleSuccessBtn").click();

            await expect(page).toHaveURL(/mock-payment-tester\.html\?mock_payment_status=completed/);
            await expect(page.locator("#redirectStatus")).toBeVisible();
            await expect(page.locator("#redirectStatus")).toContainText("Hosted payment selesai");
        });
    });
});