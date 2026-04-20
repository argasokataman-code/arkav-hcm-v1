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

    const trialStartLink = trialCard.getByRole("link", { name: "Pilih plan" });
    await expect(trialStartLink).toBeVisible();
    const trialHref = await trialStartLink.getAttribute("href");
    const trialPackageId = trialHref ? new URL(trialHref).searchParams.get("packageId") : null;
    await trialStartLink.click();

    await expect(page).toHaveURL(/\/trial\?packageId=/);
    await expect(page.locator("[data-onboarding-form]")).toBeVisible();
    if (trialPackageId) {
        await expect(page.locator("[data-onboarding-package]")).toHaveValue(trialPackageId);
    }
}

async function authApiCall(page, method, path, payload = {}) {
    const execute = async () => {
        return page.evaluate(
            async ({ method: requestMethod, requestPath, requestPayload }) => {
                try {
                    const response = await window.AuthApi.request(requestMethod, requestPath, requestPayload);
                    return {
                        ok: true,
                        data: response.data,
                    };
                } catch (error) {
                    return {
                        ok: false,
                        status: error && error.response ? error.response.status : 0,
                        data: error && error.response ? error.response.data : null,
                    };
                }
            },
            {
                method,
                requestPath: path,
                requestPayload: payload,
            },
        );
    };

    try {
        return await execute();
    } catch (error) {
        const message = String(error?.message || "");
        if (message.includes("Execution context was destroyed")) {
            await page.waitForLoadState("domcontentloaded");
            return execute();
        }
        throw error;
    }
}

async function rawAuthApiCall(page, method, path, payload = {}) {
    const execute = async () => {
        return page.evaluate(
            async ({ method: requestMethod, requestPath, requestPayload }) => {
                try {
                    const token = window.localStorage.getItem("arcav_access_token");
                    const tenantRaw = window.localStorage.getItem("arcav_active_tenant");
                    let tenant = {};
                    try {
                        tenant = tenantRaw ? JSON.parse(tenantRaw) : {};
                    } catch (_e) {
                        tenant = {};
                    }

                    const headers = {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    };

                    if (token) {
                        headers.Authorization = `Bearer ${token}`;
                    }
                    if (tenant && tenant.companyCode) {
                        headers["X-Company-Code"] = String(tenant.companyCode);
                    }
                    if (tenant && tenant.companyId) {
                        headers["X-Company-Id"] = String(tenant.companyId);
                    }
                    if (tenant && tenant.companyUuid) {
                        headers["X-Company-UUID"] = String(tenant.companyUuid);
                    }

                    const m = String(requestMethod || "get").toUpperCase();
                    const response = await fetch(`/v1${requestPath}`, {
                        method: m,
                        headers,
                        credentials: "same-origin",
                        body: m === "GET" || m === "HEAD" ? undefined : JSON.stringify(requestPayload || {}),
                    });

                    let data = null;
                    try {
                        data = await response.json();
                    } catch (_e) {
                        data = null;
                    }

                    return {
                        ok: response.ok,
                        status: response.status,
                        data,
                    };
                } catch (error) {
                    return {
                        ok: false,
                        status: 0,
                        data: { message: String(error?.message || "Unknown error") },
                    };
                }
            },
            {
                method,
                requestPath: path,
                requestPayload: payload,
            },
        );
    };

    try {
        return await execute();
    } catch (error) {
        const message = String(error?.message || "");
        if (message.includes("Execution context was destroyed")) {
            await page.waitForLoadState("domcontentloaded");
            return execute();
        }
        throw error;
    }
}

test.describe.serial("Landing to paid member flow", () => {
    test.beforeAll(() => {
        seedLandingPackages();
    });

    test("creates a company from landing pages, upgrades to starter, and pays the invoice", async ({ page }) => {
        const runId = Date.now().toString(36);
        const ownerEmail = `company.owner.${runId}@example.com`;
        const ownerPassword = "StrongPass1";
        const companyName = `QA Company ${runId}`;
        const companyAddress = "Jl. Sudirman No. 10, Jakarta";
        const companyCity = "Jakarta Selatan";
        const ownerName = "Nadia Pratama";
        let companyCode, invoiceId, invoiceNumber;

        await test.step("1. Navigate to trial package from landing", async () => {
            await goToTrialPackageFromLanding(page);
        });

        await test.step("2. Fill and submit onboarding form", async () => {
            await page.evaluate(() => {
                window.ArcavUi = null;
            });
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
            page.on("dialog", async (dialog) => {
                await dialog.accept();
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
            expect(onboardingBody.success, JSON.stringify(onboardingBody, null, 2)).toBe(true);
            expect(onboardingBody.data.company.name).toBe(companyName);
            expect(onboardingBody.data.owner.email).toBe(ownerEmail);
            expect(onboardingBody.data.subscription.status).toBe("trial");
            expect(onboardingBody.data.subscription.packageCode).toBe("trial");

            companyCode = onboardingBody.data.company.code;
            await expect(page).toHaveURL(/\/login$/);
        });

        await test.step("3. Login to company", async () => {
            await loginViaUi(
                page,
                {
                    email: ownerEmail,
                    password: ownerPassword,
                },
                {
                    companyMode: true,
                    companyCode,
                },
            );

            await expect(page.locator(".main-wrapper")).toHaveAttribute("data-company-code", companyCode);
            await expect(page.locator(".main-wrapper")).toHaveAttribute("data-subscription-status", "trial");
            await expect(page.locator(".main-wrapper")).toHaveAttribute("data-subscription-plan", "trial");
        });

        await test.step("4. Navigate to subscription and upgrade to starter", async () => {
            await page.goto("/subscription", { waitUntil: "domcontentloaded" });
            await expect(page.locator("[data-subscription-checkout-page]")).toBeVisible();
            await expect(page.locator("[data-checkout-company-code]")).toHaveValue(companyCode);
            await expect(page.locator('[data-checkout-package-select] option[data-code="starter"]')).toHaveCount(1);

            await page.locator("[data-checkout-package-select]").selectOption({ label: "Starter" });

            const checkoutResponsePromise = page.waitForResponse((response) => {
                return response.url().includes("/v1/hcm/billing/checkout") && response.request().method() === "POST";
            });

            await page.locator("[data-checkout-submit]").click();

            const checkoutResponse = await checkoutResponsePromise;
            const checkoutBody = await checkoutResponse.json();
            expect(checkoutResponse.ok(), JSON.stringify(checkoutBody, null, 2)).toBeTruthy();
            expect(checkoutBody.success).toBe(true);
            expect(checkoutBody.data.subscription.packageCode).toBe("starter");
            expect(checkoutBody.data.subscription.status).toBe("pending_payment");
            expect(checkoutBody.data.invoice.status).toBe("draft");
            expect(checkoutBody.data.invoice.id).toBeTruthy();
            expect(checkoutBody.data.invoice.invoiceNumber).toBeTruthy();
            expect(Number(checkoutBody.data.invoice.amountDue)).toBeGreaterThan(0);

            invoiceId = checkoutBody.data.invoice.id;
            invoiceNumber = checkoutBody.data.invoice.invoiceNumber;
            
            console.log("✅ Checkout response:");
            console.log("   Subscription ID:", checkoutBody.data.subscription.id);
            console.log("   Subscription Status:", checkoutBody.data.subscription.status);
            console.log("   Invoice ID:", invoiceId);
            console.log("   Invoice Number:", invoiceNumber);

            await expect(page.locator("[data-checkout-feedback]")).toContainText("Invoice berhasil dibuat");
            await expect(page.locator("[data-checkout-invoice-box]")).toBeVisible();
            await expect(page.locator("[data-checkout-invoice-subtitle]")).toContainText(invoiceNumber);
        });

        await test.step("5. Verify invoice before payment", async () => {
            await page.goto("/company/invoices", { waitUntil: "domcontentloaded" });
            await expect(page.locator("[data-company-invoices-page]")).toBeVisible();

            const invoiceListBeforePay = await authApiCall(page, "get", "/hcm/billing/invoices?perPage=50");
            expect(invoiceListBeforePay.ok, JSON.stringify(invoiceListBeforePay, null, 2)).toBe(true);

            const invoiceEntryBeforePay = (Array.isArray(invoiceListBeforePay.data?.data) ? invoiceListBeforePay.data.data : []).find((invoice) => String(invoice.invoiceNumber || "") === invoiceNumber);
            expect(invoiceEntryBeforePay).toBeTruthy();
            expect(invoiceEntryBeforePay.isPaid).toBe(false);
        });

        await test.step("6. MOCK PAYMENT - Xendit payment gateway simulation", async () => {
            console.log("Step 6: Mock payment - checking SUBSCRIPTION status BEFORE payment");
            
            // Check subscription status BEFORE payment
            const subBeforePayment = await authApiCall(page, "get", "/hcm/profile");
            console.log("   Subscription Status BEFORE:", subBeforePayment.data?.data?.subscription?.status);
            console.log("   Package Code BEFORE:", subBeforePayment.data?.data?.subscription?.packageCode);
            
            console.log("\nStep 6: Mock payment - checking invoice status BEFORE payment");
            await page.goto("/company/invoices", { waitUntil: "domcontentloaded" });
            await page.waitForTimeout(2000);
            
            // Get invoice status BEFORE payment
            const invoiceBeforeList = await authApiCall(page, "get", `/hcm/billing/invoices?perPage=50`);
            const invoiceBeforePay = (Array.isArray(invoiceBeforeList.data?.data) ? invoiceBeforeList.data.data : []).find((inv) => inv.invoiceNumber === invoiceNumber);
            console.log("📋 Invoice STATUS BEFORE PAYMENT:");
            console.log(`   Invoice Number: ${invoiceBeforePay?.invoiceNumber}`);
            console.log(`   Status: ${invoiceBeforePay?.status}`);
            console.log(`   Is Paid: ${invoiceBeforePay?.isPaid}`);
            console.log(`   Amount: Rp ${invoiceBeforePay?.amountDue}`);
            
            const beforePath = `/tmp/payment-before-${Date.now()}.png`;
            await page.screenshot({ path: beforePath });
            console.log(`📸 Screenshot BEFORE: ${beforePath}`);
            
            console.log("\n🔄 EXECUTING MOCK PAYMENT...");
            const mockPayResult = await authApiCall(page, "post", `/hcm/billing/invoices/${invoiceId}/mock-pay`, {});
            console.log("✅ Mock payment API response:");
            console.log(JSON.stringify(mockPayResult.data?.payment, null, 2));
            
            expect(mockPayResult.ok, JSON.stringify(mockPayResult, null, 2)).toBe(true);
            expect(mockPayResult.data?.success).toBe(true);
            expect(mockPayResult.data?.payment?.gateway).toBe("xendit_mock");
            expect(mockPayResult.data?.payment?.status).toBe("completed");
            
            console.log("\n⏳ Waiting 2 seconds and refreshing...");
            await page.waitForTimeout(2000);
            await page.reload({ waitUntil: "domcontentloaded" });
            await page.waitForTimeout(2000);
            
            // Get invoice status AFTER payment
            const invoiceAfterList = await authApiCall(page, "get", `/hcm/billing/invoices?perPage=50`);
            const invoiceAfterPay = (Array.isArray(invoiceAfterList.data?.data) ? invoiceAfterList.data.data : []).find((inv) => inv.invoiceNumber === invoiceNumber);
            console.log("\n📋 Invoice STATUS AFTER PAYMENT:");
            console.log(`   Invoice Number: ${invoiceAfterPay?.invoiceNumber}`);
            console.log(`   Status: ${invoiceAfterPay?.status}`);
            console.log(`   Is Paid: ${invoiceAfterPay?.isPaid}`);
            console.log(`   Amount: Rp ${invoiceAfterPay?.amountDue}`);
            
            const afterPath = `/tmp/payment-after-${Date.now()}.png`;
            await page.screenshot({ path: afterPath });
            console.log(`📸 Screenshot AFTER: ${afterPath}`);
            
            console.log("\n✅ PAYMENT STATUS CHANGED:", {
                before: invoiceBeforePay?.isPaid,
                after: invoiceAfterPay?.isPaid
            });
            
            expect(invoiceAfterPay?.isPaid).toBe(true);
            
            // Check subscription status immediately after payment
            console.log("\n🔍 Checking subscription status after payment...");
            const subAfterPayment = await authApiCall(page, "get", "/hcm/profile");
            console.log("   Subscription Status:", subAfterPayment.data?.data?.subscription?.status);
            console.log("   Package Code:", subAfterPayment.data?.data?.subscription?.packageCode);
        });

        await test.step("7. Verify invoice after payment", async () => {
            const invoiceListAfterPay = await authApiCall(page, "get", "/hcm/billing/invoices?perPage=50");
            expect(invoiceListAfterPay.ok, JSON.stringify(invoiceListAfterPay, null, 2)).toBe(true);

            const invoiceEntryAfterPay = (Array.isArray(invoiceListAfterPay.data?.data) ? invoiceListAfterPay.data.data : []).find((invoice) => String(invoice.invoiceNumber || "") === invoiceNumber);
            expect(invoiceEntryAfterPay).toBeTruthy();
            expect(invoiceEntryAfterPay.isPaid).toBe(true);
        });

        await test.step("8. Verify subscription activated", async () => {
            console.log("Step 8: Verify subscription activated - navigating to /index");
            await page.goto("/index", { waitUntil: "domcontentloaded" });
            await expect(page.locator(".main-wrapper")).toHaveAttribute("data-subscription-status", "active");
            await expect(page.locator(".main-wrapper")).toHaveAttribute("data-subscription-plan", "starter");
            console.log("✅ Step 8: Subscription activated verified");
        });

        await test.step("9. Final invoice verification", async () => {
            console.log("Step 9: Final invoice verification started");
            await page.goto("/company/invoices", { waitUntil: "domcontentloaded" });
            await expect(page.locator("[data-company-invoices-page]")).toBeVisible();
            const invoiceListFinal = await authApiCall(page, "get", "/hcm/billing/invoices?perPage=50");
            expect(invoiceListFinal.ok, JSON.stringify(invoiceListFinal, null, 2)).toBe(true);
            const invoiceEntryFinal = (Array.isArray(invoiceListFinal.data?.data) ? invoiceListFinal.data.data : []).find((invoice) => String(invoice.invoiceNumber || "") === invoiceNumber);
            expect(invoiceEntryFinal).toBeTruthy();
            expect(invoiceEntryFinal.isPaid).toBe(true);
            console.log("✅ Step 9: Final invoice verification completed");
        });

        await test.step("10. Tenant owner can manage manual activity after paid upgrade", async () => {
            console.log("Step 10: Tenant owner manual activity check started");
            const createManualByOwner = await rawAuthApiCall(page, "post", "/hcm/activity-manual", {
                title: `Owner manual activity ${runId}`,
                activityKind: "task",
                statusType: "planned",
                dueDate: null,
            });
            console.log("Step 10: Create manual activity response - Status:", createManualByOwner.status);
            expect(createManualByOwner.ok, JSON.stringify(createManualByOwner, null, 2)).toBe(true);
            expect(createManualByOwner.status).toBe(201);
            const createdManualId = Number(createManualByOwner.data?.data?.id || 0);
            expect(createdManualId).toBeGreaterThan(0);

            // No permission modal should appear for tenant owner/admin manual mutation.
            await expect(page.locator("#arcav_upgrade_required")).toBeHidden();

            const manualFeedAsOwner = await rawAuthApiCall(page, "get", "/hcm/activity-feed?type=manual&perPage=20");
            expect(manualFeedAsOwner.ok, JSON.stringify(manualFeedAsOwner, null, 2)).toBe(true);
            const manualRows = Array.isArray(manualFeedAsOwner.data?.data) ? manualFeedAsOwner.data.data : [];
            console.log("Step 10: Manual rows count:", manualRows.length);
            const createdRow = manualRows.find((row) => Number(row.manualActivityId || 0) === createdManualId);
            expect(createdRow, JSON.stringify(manualRows, null, 2)).toBeTruthy();
            expect(Boolean(createdRow?.canEdit)).toBe(true);
            expect(Boolean(createdRow?.canDelete)).toBe(true);
            console.log("✅ Step 10: Tenant owner manual activity check completed");
        });
    });
});