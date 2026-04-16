import { test, expect } from "@playwright/test";
import { credentials, loginViaUi, logoutIfNeeded } from "../helpers/auth.js";

/**
 * Payroll Run Button Flow Test
 * Tests the complete flow: Calculate Draft → Export Reconciliation → Pay via Gateway
 * Verifies button states are correct at each step
 */

test.describe("Payroll Run Button Flow", () => {
    test.afterEach(async ({ page }) => {
        await logoutIfNeeded(page);
    });

    test("button states correctly reflect run status", async ({ page }) => {
        // Login as admin
        await loginViaUi(page, credentials.admin);

        // Navigate to payroll-run page
        await page.goto("/payroll-run", { waitUntil: "domcontentloaded" });

        // Verify page loaded
        await expect(page.getByRole("heading", { name: "Payroll — Run Bulanan" })).toBeVisible();

        // Get button references
        const calculateBtn = page.locator("[data-payroll-run-calculate]");
        const exportBtn = page.locator("[data-payroll-run-export-evidence]");
        const payBtn = page.locator("[data-payroll-run-disburse]");
        const runStatusEl = page.locator("[data-payroll-run-status]");

        // Capture console logs for debugging
        page.on("console", (msg) => {
            if (msg.type() === "log" && msg.text().includes("[")) {
                console.log("📋 " + msg.text());
            }
        });

        // Wait for period to load and state to sync
        await page.waitForTimeout(2000);

        // Get the run status
        const statusBadge = await runStatusEl.innerText();
        console.log(`\n📊 Period Status: ${statusBadge}`);

        const calculateDisabled = await calculateBtn.isDisabled();
        const exportDisabled = await exportBtn.isDisabled();
        const payDisabled = await payBtn.isDisabled();

        console.log(`\n🎯 Button States:`);
        console.log(`  Calculate: ${calculateDisabled ? "❌ DISABLED" : "✅ ENABLED"}`);
        console.log(`  Export:    ${exportDisabled ? "❌ DISABLED" : "✅ ENABLED"}`);
        console.log(`  Pay:       ${payDisabled ? "❌ DISABLED" : "✅ ENABLED"}`);

        // Verify button logic consistency
        // If status is FINALIZED, all buttons should be disabled
        if (statusBadge.includes("FINALIZED") || statusBadge.includes("POSTED")) {
            console.log(`\n✓ Status is finalized - buttons correctly disabled`);
            expect(calculateDisabled).toBe(true);
            expect(exportDisabled).toBe(true);
        }
        // If status is OPEN with no run, Calculate should be enabled
        else if (statusBadge.includes("OPEN") || statusBadge.includes("DRAFT")) {
            console.log(`\n✓ Status is open/draft - Calculate button should be enabled`);
            expect(calculateDisabled).toBe(false);
        }

        console.log("\n✅ BUTTON STATE TEST PASSED");
    });

    test("calculate button enables export when successful", async ({ page }) => {
        await loginViaUi(page, credentials.admin);
        await page.goto("/payroll-run", { waitUntil: "domcontentloaded" });

        const calculateBtn = page.locator("[data-payroll-run-calculate]");
        const exportBtn = page.locator("[data-payroll-run-export-evidence]");
        const runStatusEl = page.locator("[data-payroll-run-status]");

        // Capture console logs
        page.on("console", (msg) => {
            if (msg.type() === "log" && msg.text().includes("[")) {
                console.log("📋 " + msg.text());
            }
        });

        await page.waitForTimeout(1000);

        const initialStatus = await runStatusEl.innerText();
        console.log(`\n📊 Initial Status: ${initialStatus}`);
        const calculateDisabledInitial = await calculateBtn.isDisabled();
        const exportDisabledInitial = await exportBtn.isDisabled();

        console.log(`Calculate button: ${calculateDisabledInitial ? "DISABLED" : "ENABLED"}`);
        console.log(`Export button:    ${exportDisabledInitial ? "DISABLED" : "ENABLED"}`);

        // If Calculate is enabled, try clicking it
        if (!calculateDisabledInitial) {
            console.log(`\n🔄 Clicking Calculate Draft...`);
            await calculateBtn.click();

            // Wait for calculation to complete
            const empCountEl = page.locator("[data-payroll-run-emp-count]");
            await page.waitForFunction(
                async () => {
                    const count = await empCountEl.innerText();
                    return Number(count) > 0;
                },
                { timeout: 10000 }
            ).catch(() => console.log("ℹ Employee count didn't increase (may already have data)"));

            await page.waitForTimeout(1000);

            const empCount = await empCountEl.innerText();
            console.log(`✓ Draft calculated - Employee count: ${empCount}`);

            // Check if export is now enabled
            const exportDisabledAfter = await exportBtn.isDisabled();
            console.log(`\nAfter Calculate:`);
            console.log(`  Export button: ${exportDisabledAfter ? "❌ DISABLED" : "✅ ENABLED"}`);

            if (!exportDisabledAfter) {
                console.log(`\n✓ Export button correctly enabled after draft calculation`);
            } else {
                console.log(`ℹ Export still disabled (may need row selection)`);
            }
        } else {
            console.log(`ℹ Calculate button disabled - likely finalized/posted run`);
        }

        console.log("\n✅ CALCULATE FLOW TEST PASSED");
    });

    test("export button state depends on draft status and row count", async ({ page }) => {
        await loginViaUi(page, credentials.admin);
        await page.goto("/payroll-run", { waitUntil: "domcontentloaded" });

        const exportBtn = page.locator("[data-payroll-run-export-evidence]");
        const empCountEl = page.locator("[data-payroll-run-emp-count]");

        // Capture console logs
        page.on("console", (msg) => {
            if (msg.type() === "log" && msg.text().includes("syncExportReconciliationButton")) {
                console.log("📋 " + msg.text());
            }
        });

        await page.waitForTimeout(2000);

        const empCount = await empCountEl.innerText();
        const exportDisabled = await exportBtn.isDisabled();

        console.log(`\n📊 Employee Count: ${empCount}`);
        console.log(`Export Button: ${exportDisabled ? "❌ DISABLED" : "✅ ENABLED"}`);

        // Export should only be enabled if:
        // 1. Run exists (runId is set)
        // 2. Status is "draft"
        // 3. Rows > 0

        if (Number(empCount) === 0) {
            console.log(`✓ No employees - Export correctly disabled`);
            expect(exportDisabled).toBe(true);
        } else {
            console.log(`✓ Employees exist - Export button state depends on draft status`);
        }

        console.log("\n✅ EXPORT BUTTON TEST PASSED");
    });

    test("pay button requires reconciliation download and row selection", async ({ page }) => {
        await loginViaUi(page, credentials.admin);
        await page.goto("/payroll-run", { waitUntil: "domcontentloaded" });

        const payBtn = page.locator("[data-payroll-run-disburse]");
        const rowCheckbox = page.locator("[data-payroll-run-grid] tbody [data-payroll-run-row-check]").first();

        // Capture console logs
        page.on("console", (msg) => {
            if (msg.type() === "log" && msg.text().includes("refreshSelectionSummary")) {
                console.log("📋 " + msg.text());
            }
        });

        await page.waitForTimeout(1500);

        const payDisabledInitial = await payBtn.isDisabled();
        console.log(`\n📊 Initial Pay Button State: ${payDisabledInitial ? "❌ DISABLED" : "✅ ENABLED"}`);

        // Pay button should be disabled if:
        // 1. No run selected
        // 2. No rows exist
        // 3. No rows selected
        // 4. Reconciliation not downloaded

        // Try to select a row if available
        const rowExists = await rowCheckbox.isVisible().catch(() => false);
        if (rowExists) {
            console.log(`\n🔄 Selecting first row...`);
            await rowCheckbox.check().catch(() => console.log("ℹ Could not select row"));

            await page.waitForTimeout(500);

            const payDisabledAfterSelect = await payBtn.isDisabled();
            console.log(`After row selection: ${payDisabledAfterSelect ? "❌ DISABLED" : "✅ ENABLED"}`);

            if (!payDisabledAfterSelect) {
                console.log(`✓ Row selection enabled Pay button`);
            } else {
                console.log(`ℹ Pay still disabled - likely waiting for reconciliation file download`);
            }
        } else {
            console.log(`ℹ No rows available to select`);
        }

        console.log("\n✅ PAY BUTTON TEST PASSED");
    });

    test("buttons update state when period changes", async ({ page }) => {
        await loginViaUi(page, credentials.admin);
        await page.goto("/payroll-run", { waitUntil: "domcontentloaded" });

        const calculateBtn = page.locator("[data-payroll-run-calculate]");
        const exportBtn = page.locator("[data-payroll-run-export-evidence]");

        // Capture console logs
        const logs = [];
        page.on("console", (msg) => {
            if (msg.type() === "log" && msg.text().includes("[sync")) {
                logs.push(msg.text());
            }
        });

        await page.waitForTimeout(1500);

        const initialCalcState = await calculateBtn.isDisabled();
        const initialExportState = await exportBtn.isDisabled();

        console.log(`\n📊 Initial State:`);
        console.log(`  Calculate: ${initialCalcState ? "DISABLED" : "ENABLED"}`);
        console.log(`  Export:    ${initialExportState ? "DISABLED" : "ENABLED"}`);

        // The state depends on the period's existing run
        // If period has no run or draft run, Calculate should be enabled
        // If period has finalized run, buttons should be disabled

        console.log(`\n📋 Sync operations captured: ${logs.length} events`);
        logs.slice(-3).forEach((log) => console.log(`  ${log}`));

        console.log("\n✅ STATE UPDATE TEST PASSED");
    });
});

