import { execSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

import { loginViaUi, logoutIfNeeded } from '../helpers/auth.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const backendRoot = path.resolve(__dirname, '..', '..');

function seedAssetUiScenario() {
  const runId = Date.now().toString(36);
  const result = execSync('php e2e/helpers/seed-asset-ui-flow.php', {
    cwd: backendRoot,
    env: {
      ...process.env,
      PW_ASSET_UI_RUN_ID: runId,
    },
  });

  return JSON.parse(result.toString());
}

async function tenantApi(page, method, pathName) {
  return page.evaluate(async ({ requestMethod, requestPath }) => {
    const token = window.localStorage.getItem('arcav_access_token');
    const tenantRaw = window.localStorage.getItem('arcav_active_tenant');
    let tenant = {};

    try {
      tenant = tenantRaw ? JSON.parse(tenantRaw) : {};
    } catch (_error) {
      tenant = {};
    }

    const headers = {
      Accept: 'application/json',
    };

    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
    if (tenant.companyCode) {
      headers['X-Company-Code'] = String(tenant.companyCode);
    }
    if (tenant.companyId) {
      headers['X-Company-Id'] = String(tenant.companyId);
    }
    if (tenant.companyUuid) {
      headers['X-Company-UUID'] = String(tenant.companyUuid);
    }

    const response = await fetch(requestPath, {
      method: requestMethod,
      headers,
      credentials: 'same-origin',
    });

    const data = await response.json().catch(() => null);
    return {
      ok: response.ok,
      status: response.status,
      data,
    };
  }, {
    requestMethod: method,
    requestPath: pathName,
  });
}

test.describe.serial('Asset management UI flow', () => {
  let scenario;

  test.beforeAll(() => {
    scenario = seedAssetUiScenario();
  });

  test.afterEach(async ({ page }) => {
    await logoutIfNeeded(page);
  });

  test('admin can report issue and upload attachment from assets page', async ({ page }) => {
    await loginViaUi(page, {
      email: scenario.ownerEmail,
      password: scenario.ownerPassword,
    }, {
      companyMode: true,
      companyCode: scenario.companyCode,
    });

    await page.goto('/assets', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-hcm-assets-body]')).toContainText(scenario.assetCode);

    const assetRow = page.locator('[data-hcm-assets-body] tr', { hasText: scenario.assetCode }).first();
    await expect(assetRow).toBeVisible();

    const issueDescription = `Playwright issue report ${scenario.runId}`;
    const issueResponsePromise = page.waitForResponse((response) => {
      return response.url().includes(`/v1/hcm/assets/${scenario.assetId}/issue-report`) && response.request().method() === 'POST';
    });

    await assetRow.locator(`[data-hcm-asset-issue="${scenario.assetId}"]`).click();
    await expect(page.locator('#asset_issue_modal')).toBeVisible();
    await page.locator('#asset_issue_modal [data-hcm-field="issue_type"]').selectOption('maintenance');
    await page.locator('#asset_issue_modal [data-hcm-field="priority"]').selectOption('high');
    await page.locator('#asset_issue_modal [data-hcm-field="description"]').fill(issueDescription);
    await page.locator('#asset_issue_modal [data-hcm-submit-btn]').click();

    const issueResponse = await issueResponsePromise;
    const issueBody = await issueResponse.json();
    expect(issueResponse.ok(), JSON.stringify(issueBody, null, 2)).toBeTruthy();
    expect(issueBody.success).toBe(true);
    expect(issueBody.data.ticketId).toBeTruthy();

    const ticketId = issueBody.data.ticketId;
    await expect(page.locator('#asset_issue_modal')).toBeHidden();
    await expect(page.locator('[data-hcm-assets-body] tr', { hasText: scenario.assetCode }).first()).toContainText('Maintenance');

    const ticketDetail = await tenantApi(page, 'GET', `/v1/hcm/tickets/${ticketId}`);
    expect(ticketDetail.ok, JSON.stringify(ticketDetail.data, null, 2)).toBe(true);
    expect(ticketDetail.data.data.category).toBe('asset_issue');
    expect(ticketDetail.data.data.subject).toContain(scenario.assetCode);
    expect(ticketDetail.data.data.description).toContain(issueDescription);

    const attachmentResponsePromise = page.waitForResponse((response) => {
      return response.url().includes(`/v1/hcm/assets/${scenario.assetId}/attachments`) && response.request().method() === 'POST';
    });

    await page.locator('[data-hcm-assets-body] tr', { hasText: scenario.assetCode }).first().locator(`[data-hcm-asset-attach="${scenario.assetId}"]`).click();
    await expect(page.locator('#asset_attachment_modal')).toBeVisible();
    await page.locator('#asset_attachment_modal [data-hcm-field="file"]').setInputFiles({
      name: 'asset-ui-note.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from('asset attachment from playwright'),
    });
    await page.locator('#asset_attachment_modal [data-hcm-submit-btn]').click();

    const attachmentResponse = await attachmentResponsePromise;
    const attachmentBody = await attachmentResponse.json();
    expect(attachmentResponse.ok(), JSON.stringify(attachmentBody, null, 2)).toBeTruthy();
    expect(attachmentBody.success).toBe(true);
    expect(attachmentBody.data.originalName).toBe('asset-ui-note.txt');

    await expect(page.locator('[data-hcm-asset-attachment-list]')).toContainText('asset-ui-note.txt');

    const assetDetail = await tenantApi(page, 'GET', `/v1/hcm/assets/${scenario.assetId}`);
    expect(assetDetail.ok, JSON.stringify(assetDetail.data, null, 2)).toBe(true);
    expect(assetDetail.data.data.attachmentsCount).toBeGreaterThan(0);
    expect((assetDetail.data.data.attachments || []).some((attachment) => attachment.originalName === 'asset-ui-note.txt')).toBe(true);
  });
});
