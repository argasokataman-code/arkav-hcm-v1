<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Http\Controllers\Controller;
use App\Mail\RegisterSuccessMailable;
use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use App\Models\HcmPermission;
use App\Modelsser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesAuthPermissions
{    private function getAllPermissionsForGlobalAdmin(): array
    {
        $permissions = $this->legacyGlobalAdminFallbackPermissions();

        if (Schema::hasTable('hcm_permissions')) {
            HcmPermission::query()
                ->where('is_active', true)
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->each(function (string $code) use (&$permissions): void {
                    $permissions[$code] = true;
                });
        }

        foreach ($this->globalAdminPermissionAliases() as $source => $aliases) {
            if (empty($permissions[$source])) {
                continue;
            }

            foreach ($aliases as $alias) {
                $permissions[$alias] = true;
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    private function legacyGlobalAdminFallbackPermissions(): array
    {
        return [
            // ============ EMPLOYEE MANAGEMENT (8) ============
            'employee.view' => true,
            'employee.create' => true,
            'employee.edit' => true,
            'employee.delete' => true,
            'employee.export' => true,
            'employee.import' => true,
            'employee.admin' => true,
            'employee.lifecycle.view' => true,
            
            // ============ HR & RECRUITMENT (15) ============
            'hr.view' => true,
            'hr.admin' => true,
            'recruitment.view' => true,
            'recruitment.create' => true,
            'recruitment.edit' => true,
            'recruitment.delete' => true,
            'candidate.view' => true,
            'candidate.create' => true,
            'candidate.edit' => true,
            'candidate.delete' => true,
            'job.view' => true,
            'job.create' => true,
            'job.edit' => true,
            'referral.view' => true,
            'offer.view' => true,
            
            // ============ ATTENDANCE & TIME TRACKING (20) ============
            'attendance.view' => true,
            'attendance.create' => true,
            'attendance.edit' => true,
            'attendance.delete' => true,
            'attendance.admin' => true,
            'timesheet.view' => true,
            'timesheet.create' => true,
            'timesheet.edit' => true,
            'timesheet.delete' => true,
            'timesheet.admin' => true,
            'schedule.view' => true,
            'schedule.create' => true,
            'schedule.edit' => true,
            'schedule.admin' => true,
            'shift.view' => true,
            'shift.create' => true,
            'shift.edit' => true,
            'shift.delete' => true,
            'shift.admin' => true,
            'overtime.view' => true,
            
            // ============ LEAVE MANAGEMENT (6) ============
            'leave.view' => true,
            'leave.create' => true,
            'leave.edit' => true,
            'leave.delete' => true,
            'leave.approve' => true,
            'leave.admin' => true,
            
            // ============ FINANCE & PAYROLL (20) ============
            'finance.view' => true,
            'finance.admin' => true,
            'payroll.view' => true,
            'payroll.create' => true,
            'payroll.edit' => true,
            'payroll.delete' => true,
            'payroll.disburse' => true,
            'payroll.admin' => true,
            'salary.view' => true,
            'salary.create' => true,
            'salary.edit' => true,
            'salary.admin' => true,
            'salary.template' => true,
            'salary.delete' => true,
            'overtime.approve' => true,
            'overtime.admin' => true,
            'deduction.view' => true,
            'deduction.manage' => true,
            'thr.manage' => true,
            'provident.manage' => true,
            
            // ============ PERFORMANCE MANAGEMENT (6) ============
            'performance.view' => true,
            'performance.create' => true,
            'performance.edit' => true,
            'performance.delete' => true,
            'performance.admin' => true,
            'goal.manage' => true,
            
            // ============ TRAINING & DEVELOPMENT (5) ============
            'training.view' => true,
            'training.create' => true,
            'training.edit' => true,
            'training.delete' => true,
            'training.admin' => true,
            
            // ============ EMPLOYEE LIFECYCLE (5) ============
            'employee.lifecycle.create' => true,
            'employee.lifecycle.approve' => true,
            'promotion.admin' => true,
            'resignation.admin' => true,
            'termination.admin' => true,
            
            // ============ ASSET MANAGEMENT (5) ============
            'asset.view' => true,
            'asset.create' => true,
            'asset.edit' => true,
            'asset.delete' => true,
            'asset.admin' => true,
            
            // ============ USER & ROLE MANAGEMENT (13) ============
            'user.view' => true,
            'user.create' => true,
            'user.edit' => true,
            'user.delete' => true,
            'user.admin' => true,
            'role.view' => true,
            'role.create' => true,
            'role.edit' => true,
            'role.delete' => true,
            'role.admin' => true,
            'permission.view' => true,
            'permission.manage' => true,
            'permission.assign' => true,
            
            // ============ COMPANY MANAGEMENT (5) ============
            'company.view' => true,
            'company.create' => true,
            'company.edit' => true,
            'company.delete' => true,
            'company.admin' => true,
            
            // ============ SETTINGS & CONFIGURATION (28) ============
            'setting.view' => true,
            'setting.edit' => true,
            'setting.admin' => true,
            'email.view' => true,
            'email.send' => true,
            'email.template' => true,
            'email.admin' => true,
            'sms.view' => true,
            'sms.send' => true,
            'sms.template' => true,
            'sms.admin' => true,
            'otp.manage' => true,
            'approval.view' => true,
            'approval.admin' => true,
            'language.view' => true,
            'language.create' => true,
            'language.edit' => true,
            'language.admin' => true,
            'appearance.view' => true,
            'appearance.edit' => true,
            'appearance.admin' => true,
            'storage.admin' => true,
            'security.ban-ip' => true,
            'cache.manage' => true,
            'cronjob.view' => true,
            'seo.admin' => true,
            'auth.admin' => true,
            
            // ============ REPORTING (6) ============
            'report.view' => true,
            'report.create' => true,
            'report.export' => true,
            'report.schedule' => true,
            'analytics.view' => true,
            'analytics.export' => true,
            
            // ============ SYSTEM MANAGEMENT (8) ============
            'system.admin' => true,
            'backup.create' => true,
            'backup.restore' => true,
            'log.view' => true,
            'audit.view' => true,
            'ai.admin' => true,
            'ai.settings' => true,
            'gdpr.manage' => true,
            
            // ============ PREFIX & CUSTOM FIELDS (7) ============
            'prefix.view' => true,
            'prefix.edit' => true,
            'prefix.admin' => true,
            'customfield.view' => true,
            'customfield.create' => true,
            'customfield.edit' => true,
            'customfield.admin' => true,

            // ============ TICKETS & SUPPORT (6) ============
            'tickets.manage' => true,
            'tickets.admin' => true,
            'ticket.admin' => true,
            'ticket.assign' => true,
            'ticket.update' => true,
            'ticket.category.manage' => true,
            
            // ============ CRM - CLIENTS (5) ============
            'crm.view' => true,
            'crm.admin' => true,
            'client.view' => true,
            'client.create' => true,
            'client.edit' => true,
            
            // ============ CRM - CONTACTS (5) ============
            'contact.view' => true,
            'contact.create' => true,
            'contact.edit' => true,
            'contact.delete' => true,
            'contact.admin' => true,
            
            // ============ CRM - DEALS (5) ============
            'deal.view' => true,
            'deal.create' => true,
            'deal.edit' => true,
            'deal.delete' => true,
            'deal.admin' => true,
            
            // ============ CRM - LEADS (5) ============
            'lead.view' => true,
            'lead.create' => true,
            'lead.edit' => true,
            'lead.delete' => true,
            'lead.admin' => true,
            
            // ============ PROJECTS & TASKS (9) ============
            'project.view' => true,
            'project.create' => true,
            'project.edit' => true,
            'project.delete' => true,
            'project.admin' => true,
            'task.view' => true,
            'task.create' => true,
            'task.edit' => true,
            'task.delete' => true,
            
            // ============ ACCOUNTING - INVOICES (5) ============
            'invoice.view' => true,
            'invoice.create' => true,
            'invoice.edit' => true,
            'invoice.delete' => true,
            'invoice.admin' => true,
            
            // ============ ACCOUNTING - PAYMENTS (5) ============
            'payment.view' => true,
            'payment.create' => true,
            'payment.edit' => true,
            'payment.delete' => true,
            'payment.admin' => true,
            
            // ============ ACCOUNTING - EXPENSES (6) ============
            'expense.view' => true,
            'expense.create' => true,
            'expense.edit' => true,
            'expense.delete' => true,
            'expense.approve' => true,
            'expense.admin' => true,
            
            // ============ ACCOUNTING - ESTIMATES (4) ============
            'estimate.view' => true,
            'estimate.create' => true,
            'estimate.edit' => true,
            'estimate.delete' => true,
            
            // ============ ACCOUNTING - BUDGETS (5) ============
            'budget.view' => true,
            'budget.create' => true,
            'budget.edit' => true,
            'budget.delete' => true,
            'budget.admin' => true,
            
            // ============ ACCOUNTING - TAXES & CATEGORIES (5) ============
            'tax.view' => true,
            'tax.edit' => true,
            'tax.admin' => true,
            'category.view' => true,
            'category.admin' => true,
            
            // ============ COMMUNICATION (5) ============
            'chat.view' => true,
            'chat.send' => true,
            'call.view' => true,
            'call.make' => true,
            'communication.admin' => true,
            
            // ============ PRODUCTIVITY (16) ============
            'calendar.view' => true,
            'calendar.create' => true,
            'calendar.edit' => true,
            'todo.view' => true,
            'todo.create' => true,
            'todo.edit' => true,
            'todo.delete' => true,
            'note.view' => true,
            'note.create' => true,
            'note.edit' => true,
            'note.delete' => true,
            'social.view' => true,
            'social.post' => true,
            'file.view' => true,
            'file.upload' => true,
            'file.delete' => true,
            
            // ============ SAAS MANAGEMENT (8) ============
            'saas.view' => true,
            'saas.admin' => true,
            'saas.package.view' => true,
            'saas.package.create' => true,
            'saas.subscription.view' => true,
            'saas.subscription.approve' => true,
            'saas.billing.view' => true,
            'saas.report.view' => true,
            
            // ============ CONTENT MANAGEMENT (13) ============
            'content.view' => true,
            'content.create' => true,
            'content.edit' => true,
            'content.delete' => true,
            'content.publish' => true,
            'blog.view' => true,
            'blog.create' => true,
            'blog.edit' => true,
            'blog.delete' => true,
            'blog.admin' => true,
            'page.view' => true,
            'page.create' => true,
            'page.edit' => true,
            'page.delete' => true,
            'knowledgebase.view' => true,
            'knowledgebase.create' => true,
            'knowledgebase.edit' => true,
            'knowledgebase.admin' => true,
            
            // ============ ADDITIONAL ADMIN PERMISSIONS ============
            'admin' => true,
            'superadmin' => true,
            'client.delete' => true,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function globalAdminPermissionAliases(): array
    {
        return [
            'attendance.edit' => ['attendance.update'],
            'employee.edit' => ['employee.update'],
            'leave.edit' => ['leave.update'],
            'overtime.admin' => ['overtime.type.manage'],
            'payroll.edit' => ['payroll.update'],
            'promotion.admin' => ['promotion.manage'],
            'resignation.admin' => ['resignation.manage'],
            'permission.manage' => ['role.sync_permission', 'user_management.manage'],
            'permission.assign' => ['role.sync_permission'],
            'permission.view' => ['role.sync_permission'],
            'role.admin' => ['user_management.manage'],
            'role.edit' => ['role.update'],
            'setting.edit' => ['settings.manage'],
            'setting.admin' => ['settings.manage'],
            'setting.view' => ['settings.view'],
            'termination.admin' => ['termination.manage'],
            'training.admin' => ['training.manage'],
            'user.admin' => ['user.assign_role', 'user_management.manage'],
            'user.edit' => ['user.update'],
        ];
    }

}
