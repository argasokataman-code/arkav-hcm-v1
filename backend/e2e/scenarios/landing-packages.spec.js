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

async function openLanding(page) {
  await page.goto("/landing", { waitUntil: "domcontentloaded" });
  await expect(page.locator("[data-packages-grid]")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Pilih paket" })).toBeVisible();
}

test.describe("Landing packages flow", () => {
  test.beforeAll(() => {
    seedLandingPackages();
  });

  test("shows starter and higher-tier packages on landing page", async ({ page }) => {
    await openLanding(page);

    const packagesGrid = page.locator("[data-packages-grid]");
    const packagesData = await packagesGrid.getAttribute("data-packages");
    expect(packagesData).toBeTruthy();

    const packages = JSON.parse(packagesData || "[]");
    const codes = packages.map((pkg) => pkg.code);

    expect(codes).toEqual(expect.arrayContaining(["trial", "starter", "growth", "business", "enterprise"]));

    const starterPackage = packages.find((pkg) => pkg.code === "starter");
    expect(starterPackage).toBeTruthy();

    const starterCard = page.locator(".landing-card", { hasText: "Starter" }).first();
    await expect(starterCard).toBeVisible();
    await expect(starterCard).toContainText("Rp 199.000");
    await expect(starterCard.getByRole("link", { name: "Pilih plan" })).toHaveAttribute(
      "href",
      new RegExp(`/trial\\?packageId=${starterPackage.uuid}$`),
    );

    await expect(page.locator(".landing-card", { hasText: "Growth" }).first()).toBeVisible();
    await expect(page.locator(".landing-card", { hasText: "Business" }).first()).toBeVisible();
    await expect(page.locator(".landing-card", { hasText: "Enterprise" }).first()).toBeVisible();

    await page.goto(`/trial?packageId=${starterPackage.uuid}`, { waitUntil: "domcontentloaded" });
    await expect(page.getByText("Coba Trial Gratis", { exact: true })).toBeVisible();
    await expect(page.locator("[data-onboarding-form]")).toBeVisible();

    const packageSelect = page.locator("[data-onboarding-package]");
    await expect(packageSelect).toBeVisible();
    await expect(packageSelect).toHaveValue(starterPackage.uuid);
    await expect(packageSelect.locator("option")).toContainText(["Trial (30 Hari) (trial)", "Starter (starter)"]);
  });
});