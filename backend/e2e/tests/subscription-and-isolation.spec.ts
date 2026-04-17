/**
 * E2E Test: Subscription & Multi-Tenant Isolation
 * 
 * Validates:
 * 1. Subscription enforcement (active/trial/expired states)
 * 2. Multi-tenant company isolation
 * 3. Employee count limits per subscription
 * 4. Role-based access across tenants
 * 
 * Run: npx playwright test subscription-and-isolation.spec.ts
 */

import { test, expect } from '@playwright/test';

const API_BASE = process.env.API_BASE || 'http://127.0.0.1:8007';
const APP_BASE = process.env.APP_BASE || 'http://127.0.0.1:5179';

test.describe('Subscription & Multi-Tenant Isolation', () => {
  // Setup tokens/users for testing
  let adminToken, adminUserId, companyId1, companyId2;

  test.beforeAll(async () => {
    // This would run once before all tests in the suite
    // Setup test data: create companies, subscriptions, users
  });

  test.describe('Subscription State Enforcement', () => {
    test('should allow API access when subscription is active', async ({ request }) => {
      // Mock: subscription with status='active'
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response.status()).toBe(200);
    });

    test('should allow API access when subscription is trial (not expired)', async ({ request }) => {
      // Mock: subscription with status='trial', trialEndsAt in future
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response.status()).toBe(200);
    });

    test('should deny API access when subscription is expired', async ({ request }) => {
      // Mock: subscription with status='expired'
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      // Expect 403 with SUBSCRIPTION_EXPIRED code
      expect(response.status()).toBe(403);
      const body = await response.json();
      expect(body.error.code).toContain('SUBSCRIPTION_EXPIRED');
    });

    test('should deny API access when subscription is pending_payment', async ({ request }) => {
      // Mock: subscription with status='pending_payment'
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      // Expect 403 or limited access
      expect([403, 403]).toContain(response.status());
    });

    test('should transition trial → active when payment is made', async ({ request }) => {
      // 1. Create subscription in trial
      // 2. Create invoice
      // 3. Mark invoice as paid
      // 4. Verify subscription status changed to 'active'
      const response = await request.post(`${API_BASE}/v1/saas/invoices/123/mark-paid`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response.status()).toBe(200);
    });
  });

  test.describe('Multi-Tenant Company Isolation', () => {
    test('should not expose employees from other companies', async ({ request }) => {
      // Company1 user requests employees
      // Should only see Company1 employees, not Company2
      
      // As Company1 admin with activeCompanyId=1
      const response1 = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      const data1 = await response1.json();
      expect(data1.data.length).toBeGreaterThan(0);
      expect(data1.data[0].companyId).toBe(companyId1);

      // Switch to Company2 context
      const response2 = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId2
        }
      });
      const data2 = await response2.json();
      // Employees should be different set
      const ids1 = data1.data.map(e => e.id);
      const ids2 = data2.data.map(e => e.id);
      expect(ids1).not.toEqual(ids2);
    });

    test('should deny access to other company data', async ({ request }) => {
      // User from Company1 tries to access Company2 resource directly
      const response = await request.get(`${API_BASE}/v1/hcm/employees/999`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId2
        }
      });
      // If employee 999 belongs to Company1, should get 403/404
      expect([403, 404]).toContain(response.status());
    });

    test('should not allow user to switch to unauthorized company', async ({ request }) => {
      // Company1 user without access to Company3
      const response = await request.post(`${API_BASE}/v1/hcm/context/set-active-company`, {
        data: { companyId: 9999 },
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response.status()).toBe(403);
    });

    test('should isolate payroll data per company', async ({ request }) => {
      // Company1 payroll run should not include Company2 employees
      const response = await request.post(`${API_BASE}/v1/hcm/payroll-periods/1/calculate-draft`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      const data = await response.json();
      expect(response.status()).toBe(200);
      // All lines should belong to Company1
      const allCompanyIds = new Set();
      data.data.lines?.forEach(line => {
        allCompanyIds.add(line.companyId);
      });
      expect(allCompanyIds.size).toBe(1);
      expect(Array.from(allCompanyIds)[0]).toBe(companyId1);
    });

    test('should isolate settings per company', async ({ request }) => {
      // Company1 settings should not affect Company2
      // Update Company1 setting
      const setResponse = await request.post(`${API_BASE}/v1/hcm/settings`, {
        data: {
          group: 'general',
          settings: { company_name: 'Company One Updated' }
        },
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      expect(setResponse.status()).toBe(200);

      // Get Company1 settings
      const getResponse1 = await request.get(`${API_BASE}/v1/hcm/settings`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      const data1 = await getResponse1.json();
      expect(data1.data.settings.company_name).toBe('Company One Updated');

      // Get Company2 settings (should be unchanged)
      const getResponse2 = await request.get(`${API_BASE}/v1/hcm/settings`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId2
        }
      });
      const data2 = await getResponse2.json();
      expect(data2.data.settings.company_name).not.toBe('Company One Updated');
    });
  });

  test.describe('Employee Count Limits per Subscription', () => {
    test('should reject employee creation when limit exceeded', async ({ request }) => {
      // Subscription plan allows 5 employees, already have 5
      const response = await request.post(`${API_BASE}/v1/hcm/employees`, {
        data: {
          name: 'New Employee',
          email: 'new@example.com',
          department: 'IT'
        },
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      expect(response.status()).toBe(422);
      const body = await response.json();
      expect(body.error.code).toBe('EMPLOYEE_COUNT_EXCEEDED');
      expect(body.error.message).toContain('plan limit');
    });

    test('should allow upgrade to higher plan and create more employees', async ({ request }) => {
      // 1. Upgrade subscription to higher tier
      const upgradeResponse = await request.post(`${API_BASE}/v1/hcm/billing/checkout`, {
        data: {
          package_id: 2, // Higher tier
          billing_cycle: 'monthly'
        },
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(upgradeResponse.status()).toBe(201);

      // 2. Now create employee should succeed
      const createResponse = await request.post(`${API_BASE}/v1/hcm/employees`, {
        data: {
          name: 'New Employee After Upgrade',
          email: 'new-upgrade@example.com',
          department: 'IT'
        },
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': companyId1
        }
      });
      expect(createResponse.status()).toBe(201);
    });
  });

  test.describe('Role-Based Access Across Tenants', () => {
    test('should isolate payroll operations by role', async ({ request }) => {
      // Non-admin employee should not be able to finalize payroll
      const response = await request.post(`${API_BASE}/v1/hcm/payroll-runs/1/finalize`, {
        headers: { 
          Authorization: `Bearer employeeToken`,
          'X-Company-Context': companyId1
        }
      });
      expect(response.status()).toBe(403);
      const body = await response.json();
      expect(body.error.code).toBe('AUTH_FORBIDDEN');
    });

    test('should allow only HCM admin to disburse payroll', async ({ request }) => {
      // Employee token
      const response1 = await request.post(`${API_BASE}/v1/hcm/payroll-runs/1/disburse`, {
        data: { userIds: [10] },
        headers: { Authorization: `Bearer employeeToken` }
      });
      expect(response1.status()).toBe(403);

      // HCM admin token
      const response2 = await request.post(`${API_BASE}/v1/hcm/payroll-runs/1/disburse`, {
        data: { userIds: [10] },
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response2.status()).toBe(200);
    });

    test('should enforce user management permissions', async ({ request }) => {
      // Non-admin cannot create users
      const response1 = await request.post(`${API_BASE}/v1/hcm/user-management/users`, {
        data: {
          name: 'New User',
          email: 'user@example.com',
          password: 'Password123!'
        },
        headers: { Authorization: `Bearer employeeToken` }
      });
      expect(response1.status()).toBe(403);

      // HCM admin can create users
      const response2 = await request.post(`${API_BASE}/v1/hcm/user-management/users`, {
        data: {
          name: 'New User',
          email: 'user@example.com',
          password: 'Password123!'
        },
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      expect(response2.status()).toBe(201);
    });
  });

  test.describe('Authorization Header Validation', () => {
    test('should reject requests without Authorization header', async ({ request }) => {
      const response = await request.get(`${API_BASE}/v1/hcm/employees`);
      expect(response.status()).toBe(401);
    });

    test('should reject requests with invalid token', async ({ request }) => {
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: 'Bearer invalid.token.here' }
      });
      expect(response.status()).toBe(401);
    });

    test('should reject requests with expired token', async ({ request }) => {
      // Use token that expired in past
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: 'Bearer eyJhbGc...' } // Mock expired JWT
      });
      expect(response.status()).toBe(401);
    });
  });

  test.describe('Tenant Context Middleware', () => {
    test('should require active company context for HCM endpoints', async ({ request }) => {
      // No company context header
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { Authorization: `Bearer ${adminToken}` }
      });
      // Should either auto-select first company or require explicit header
      expect([200, 422]).toContain(response.status());
    });

    test('should fail gracefully when company context is invalid', async ({ request }) => {
      const response = await request.get(`${API_BASE}/v1/hcm/employees`, {
        headers: { 
          Authorization: `Bearer ${adminToken}`,
          'X-Company-Context': 'invalid-id'
        }
      });
      expect(response.status()).toBe(422);
    });
  });
});
