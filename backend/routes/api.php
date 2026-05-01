<?php

/*
|--------------------------------------------------------------------------
| API Routes (Modular)
|--------------------------------------------------------------------------
|
| This file registers all API routes by requiring modular files from
| the backend/routes/api/ directory. Each module file contains routes
| for a specific domain (auth, employee, payroll, etc.).
|
| New modules should be added to backend/routes/api/ and then required here.
| Middleware (api.token, tenant.context, etc.) is typically scoped at the
| module level or within each route group.
|
*/

// Core Authentication & Identity
require __DIR__ . '/api/auth.php';

// Public (Guest) Routes
require __DIR__ . '/api/onboarding.php';

// Company Management
require __DIR__ . '/api/company.php';

// Dashboard & Notifications
require __DIR__ . '/api/dashboard.php';
require __DIR__ . '/api/notification-preferences.php';

// Employee Management
require __DIR__ . '/api/employee.php';

// Attendance & Shifts
require __DIR__ . '/api/attendance.php';

// Payroll (Feature-Gated)
require __DIR__ . '/api/payroll.php';

// Overtime
require __DIR__ . '/api/overtime.php';

// Leave & Holidays
require __DIR__ . '/api/leave.php';

// Salary Components
require __DIR__ . '/api/salary-component.php';

// Tax Governance
require __DIR__ . '/api/tax-governance.php';

// Asset Management
require __DIR__ . '/api/asset.php';

// Performance Management (Feature-Gated)
require __DIR__ . '/api/performance.php';

// Training (Feature-Gated)
require __DIR__ . '/api/training.php';

// Helpdesk / Tickets
require __DIR__ . '/api/ticket.php';

// Notes
require __DIR__ . '/api/notes.php';

// Employee Lifecycle: Promotions
require __DIR__ . '/api/promotion.php';

// Employee Lifecycle: Resignations
require __DIR__ . '/api/resignation.php';

// Employee Lifecycle: Terminations
require __DIR__ . '/api/termination.php';

// User & Role Management
require __DIR__ . '/api/user-management.php';

// Settings
require __DIR__ . '/api/settings.php';

// Email Settings & Compose (Global Admin)
require __DIR__ . '/api/email-settings.php';

// Billing & Subscriptions
require __DIR__ . '/api/billing.php';

// Reports & Snapshots
require __DIR__ . '/api/reports.php';

// Reconciliation
require __DIR__ . '/api/reconciliation.php';

// SaaS Platform Management
require __DIR__ . '/api/saas.php';

// Webhooks (External, Outside Auth)
require __DIR__ . '/api/webhooks.php';

// Health Check
require __DIR__ . '/api/health.php';

// Mock Payments (Development Only)
require __DIR__ . '/api/mock-payments.php';
