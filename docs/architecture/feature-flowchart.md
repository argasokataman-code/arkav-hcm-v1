# Feature Flowcharts — Per Modul

> Dibuat 2026-06-22 berdasarkan mapping 100% runtime (`backend/routes/api/`, controller, model, docs/features/).

---

## 1. Identity & Auth

```mermaid
flowchart TD
    A[Guest] -->|Buka /landing| B[Landing Page]
    A -->|Buka /register| C[Register / Trial]
    A --> Buka /login D[Login Page]
    D -->|POST /v1/identity/auth/login| E{Valid?}
    E -->|Ya| F[HttpOnly cookie + accessToken]
    F --> G[Dashboard / Halaman tujuan]
    E -->|Tidak| D
    C -->|POST /v1/identity/auth/register| H{Company + User}
    H -->|Success| F
    G -->|GET /v1/identity/auth/me| I[Validate session]
    I --> J[Redirect ke login if expired]
```

## 2. Employees & Organization

```mermaid
flowchart LR
    subgraph Master
        A[Departments] --> D[Employees]
        B[Designations] --> D
        C[Teams] --> D
    end
    D --> E[Employee Detail]
    D --> F[Employee Report]
    D --> G[Bulk Upload Excel]
    E --> H[Compensation History]
    E --> I[Contract History]
    E --> J[Bank Account]
    E --> K[Document Center]
```

## 3. Attendance

```mermaid
flowchart TD
    subgraph Employee
        A1[Buka /attendance-employee] --> A2[GET /me/today]
        A2 --> A3{Status?}
        A3 -->|None| A4[Punch In + GPS]
        A3 -->|In| A5[Break toggle]
        A5 --> A6[Punch Out + GPS]
        A6 --> A7{Net < 4 jam?}
        A7 -->|Ya| A8[Status: needs_review]
        A7 -->|Tidak| A9[Status: present]
        A8 --> A10[Request correction]
    end

    subgraph Admin
        B1[Buka /attendance-admin] --> B2[GET /admin]
        B2 --> B3[Filter: date/search/dept/status]
        B3 --> B4[Edit record]
        B4 --> B5[PUT /admin/record]
        B3 --> B6[Export xlsx/csv]
        B1 --> B7[Review corrections]
        B7 --> B8[Approve / Dismiss]
    end

    subgraph Settings
        C1[PUT /attendance/settings] --> C2[Set defaultCheckInTime]
        C1 --> C3[Set earlyPunchOutThreshold]
        C1 --> C4[Set maxBreakMinutes]
        C1 --> C5[Set correctionWindowDays]
    end
```

## 4. Selfie & Shift Schedule

```mermaid
flowchart LR
    subgraph Selfie
        S1[Punch In] --> S2[Capture via camera]
        S2 --> S3[POST /me/selfie]
        S3 --> S4[Storage private + SHA256 hash]
        S4 --> S5[Admin download /admin/records/.../selfie/download]
    end

    subgraph Shift Schedule
        T1[Shift Master CRUD] --> T2[Schedule Timing per user]
        T2 --> T3[Smart Planner generate]
        T3 --> T4[Preview diff]
        T4 --> T5[Publish roster]
        T5 --> T6[Schedule Rosters table]
    end
```

## 5. Leave & Holidays

```mermaid
flowchart TD
    subgraph Admin
        L1[Manage Holidays] --> L1a[POST /v1/hcm/holidays]
        L1 --> L1b[Set leave types]
        L1b --> L1c[balance: deduct_from_balance]
        L1 --> L1d[Approve/Reject requests]
    end

    subgraph Employee
        L2[Submit leave] --> L2a[POST /v1/hcm/leave-requests]
        L2a --> L2b{Approval needed?}
        L2b -->|Sequence| L2c[Approver L1 → L2 → ...]
        L2b -->|Simultaneous| L2d[Semua approver notified]
        L2c --> L2e[Approved]
        L2d --> L2e
        L2e --> L2f[Balance deducted]
        L2f --> L2g[Attendance marked on_leave]
    end
```

## 6. Overtime

```mermaid
flowchart LR
    O1[Overtime Types CRUD] --> O2[Employee submit request]
    O2 --> O3[POST /calculate - PP 35/2021]
    O3 --> O4[hourlyRate = baseSalary / 173]
    O4 --> O5[Draft payroll line]
    O5 --> O6[Conflict check: leave?]
    O6 --> O7[Approved → Payroll]
```

## 7. Payroll

```mermaid
flowchart TD
    subgraph Setup
        P1[Salary Components Master] --> P2[Payroll Items Catalog]
        P2 --> P3[Employee Assignments]
        P3 --> P4[Employee Salary]
    end

    subgraph Run Cycle
        R1[Active Period] --> R2[Calculate Draft]
        R2 --> R3[Pull lines: items + assignments]
        R3 --> R4[Review & Adjust]
        R4 --> R5[Export Reconciliation Gate]
        R5 -->|Export evidence| R6[Finalize]
        R6 --> R7[Disburse / Mark Paid]
        R7 --> R8[Void if unpaid]
    end

    subgraph Output
        S1[Payslip PDF] --> S2[Employee view /payslip]
        S1 --> S3[Admin report /payslip-report]
        P1 --> S1
        R7 --> S1
    end

    subgraph THR & PKWT
        T1[THR Settings] --> T2[Batch Generate]
        T2 --> T3[Disburse → Post-Payroll]
        K1[PKWT Preview] --> K2[Generate → Post-Payroll]
        T3 --> S1
        K2 --> S1
    end
```

## 8. Performance & Growth

```mermaid
flowchart LR
    subgraph Performance
        Perf1[Indicator Templates] --> Perf2[Appraisal Cycles]
        Perf2 --> Perf3[Reviews]
        Perf3 --> Perf4[Self Review → Manager → Admin Final]
        Perf4 --> Perf5[Score: 70% KPI + 30% Behavioral]
    end

    subgraph Training
        Tr1[Training Types] --> Tr2[Trainers]
        Tr2 --> Tr3[Training Events]
        Tr3 --> Tr4[Participants]
    end

    subgraph Goals
        G1[Goal Types] --> G2[Employee Goals]
        G2 --> G3[Manager Monitor]
    end
```

## 9. Employee Lifecycle

```mermaid
flowchart TD
    subgraph Promotion
        PRO1[Admin record promotion] --> PRO2[Department/Designation from→to]
        PRO2 --> PRO3[Snapshot preserved]
    end

    subgraph Resignation
        RES1[HR record resignation] --> RES2[Status: pending]
        RES2 --> RES3[Approved / Cancelled]
        RES3 --> RES4[resignationDate >= noticeDate]
    end

    subgraph Termination
        TER1[Draft_Review] --> TER2[Legal_Review]
        TER2 --> TER3[Approved_Internal]
        TER3 --> TER4[Finalized_Execution]
        TER4 --> TER5[Settlement Preview]
        TER5 --> TER6[Prorata + PKWT + Asset Clearance]
        TER3 -.-> TER7[Checklist blocks finalization]
    end
```

## 10. Asset & Tickets

```mermaid
flowchart LR
    subgraph Asset
        A1[Categories] --> A2[Asset Masters]
        A2 --> A3[Assign to Employee]
        A3 --> A4[Return]
        A2 --> A5[Issue Report]
    end

    subgraph Tickets
        T1[Create Ticket] --> T2[Open → In Progress]
        T2 --> T3[Resolved → Closed]
        A5 --> T1
    end

    subgraph Termination
        TERM[Clearance] --> A4
    end
```

## 11. SaaS / Billing

```mermaid
flowchart TD
    subgraph Composition
        PKG1[Packages CRUD] --> PKG2[Features + Add-ons]
        PKG2 --> PKG3[Feature Catalog]
    end

    subgraph Lifecycle
        SUB1[Trial] --> SUB2[Pending Payment]
        SUB2 -->|Invoice Paid| SUB3[Active]
        SUB3 -->|Renewal| SUB4[Invoice → Payment]
        SUB3 -->|Change Plan| SUB5[Preview → Approval]
        SUB5 --> SUB3
        SUB3 -->|Expired| SUB6[Suspended]
        SUB3 -->|Cancel| SUB7[Cancelled]
    end

    subgraph Platform Admin
        DASH[Super Admin Dashboard] --> REV[Revenue KPI]
        DASH --> COMP[Company List]
        DASH --> SUB_STATUS[Subscription Health]
        BILL[Billing Overview] --> INVOICE[Invoice Detail]
        DOMAIN[Domain Management] --> VERIFY[DNS Verification]
    end
```

## 12. Governance

```mermaid
flowchart LR
    subgraph Tax
        TX1[PPh 21 Policy] --> TX2[STATUTORY / TER rate]
        TX2 --> TX3[Compliance Snapshot]
        TX3 --> TX4[SPT Masa Report]
    end

    subgraph BPJS
        BP1[Rate Baselines] --> BP2[Employee Membership]
        BP2 --> BP3[Compliance Score]
    end

    subgraph Allowance
        AL1[Allowance Policies] --> AL2[Employee Assignments]
        AL2 --> AL3[Payroll Draft pull]
    end

    subgraph PDP
        PDP1[Consent Collection] --> PDP2[Encrypted Storage]
        PDP2 --> PDP3[Data Portal / Right to Erasure]
    end
```

## 13. Notifications & Reporting

```mermaid
flowchart LR
    subgraph Notifications
        N1[Business Event] --> N2[Notification Job]
        N2 --> N3{Channel}
        N3 -->|In-app| N4[Inbox]
        N3 -->|Email| N5[Email Service]
        N4 --> N6[Read / Preferences]
    end

    subgraph Reporting
        R1[Live Data] --> R3[Report Hub]
        R2[Snapshot Archive] --> R3
        R3 --> R4[Export CSV/XLSX/PDF]
    end
```

## 14. User Management & RBAC

```mermaid
flowchart TD
    subgraph Layers
        L1[Global Super Admin] --> L2[Bypass all gates]
        L1 --> L3[Platform SaaS surface]
        L4[Tenant Admin / Owner] --> L5[Tenant-scoped HCM]
        L4 --> L6[HCM features]
        L7[Employee] --> L8[Self-service only]
    end

    subgraph Gates
        G1[Middleware: hcm.web.global-admin] --> G2[Global-only Web]
        G3[Middleware: hcm.api.feature:xxx] --> G4[Feature-gated API]
        G5[Middleware: ensureHcmAdmin] --> G6[Admin-only API]
        G7[RBAC: hcm_role_permissions] --> G8[Granular permission]
    end
```

## 15. Inter-Module Data Flow

```mermaid
flowchart TD
    ID[Identity & Auth] --> ALL[All Modules]
    EMP[Employees & Org] --> ATD[Attendance]
    EMP --> LV[Leave]
    EMP --> OT[Overtime]
    EMP --> PR[Payroll]
    EMP --> PERF[Performance]
    EMP --> LIFE[Lifecycle]
    ATD --> PERF
    ATD --> LV
    LV --> ATD
    OT --> PR
    PR --> PAY[Payslip]
    PR --> TX[Tax Governance]
    PR --> BPJS[BPJS Governance]
    PR --> ALW[Allowance Governance]
    ASSET[Asset] --> TERM[Termination]
    TICKET[Tickets] --> ASSET
    APPROVE[Approval Settings] --> LV
    APPROVE --> OT
    APPROVE --> LIFE
    NOTIF[Notifications] --> ALL
    REPORT[Reporting] --> ALL
```

---

## 16. Attendance Selfie

```mermaid
flowchart TD
    A[Punch In] --> B{Attendance started?}
    B -->|Ya| C[Open camera modal]
    C --> D[Capture photo]
    D --> E[POST /attendance/me/selfie]
    E --> F{Valid image? JPEG/PNG/WEBP ≤5MB}
    F -->|Ya| G[Storage private disk]
    G --> H[SHA256 hash stored]
    H --> I[Admin can download]
    I --> J[Hash verified on download]
    F -->|Tidak| K[422 VALIDATION_ERROR]
```

## 17. Attendance Shift Schedule

```mermaid
flowchart TD
    subgraph Master Data
        A[Shift CRUD] --> A1[start_time, end_time, isOvernight]
    end
    subgraph Per-User
        B[Schedule Timing] --> B1[Override per user]
        B1 --> B2[Link to shift or manual time]
    end
    subgraph Smart Planner
        C[Generate] --> C1[Load employees + shifts + rules]
        C1 --> C2[SmartAttendanceShiftingService]
        C2 --> C3[Draft roster]
        C3 --> C4[Preview]
        C4 --> C5[Publish → HcmScheduleRoster]
    end
    subgraph Timesheet
        D[GET /timesheets] --> D1[Filter dateFrom/dateTo/project]
        D1 --> D2[Sort by employee/date/worked]
    end
```

## 18. Payroll Salary Components (Detail)

```mermaid
flowchart LR
    subgraph Types
        A[System-Locked] --> A1[Auto-registered by BPJS / Tax / Allowance]
        B[Tenant-Custom] --> B1[HR Admin-managed]
    end
    A1 --> C[Flags: taxTreatment, bpjsBase, thrBase]
    B1 --> C
    C --> D[Payroll engine reads flags]
```

## 19. Payroll Items

```mermaid
flowchart LR
    A[Payroll Items Catalog] --> B[Custom item / Linked to master]
    B --> C[Employee Assignments]
    C --> D[Payroll Draft pulls assignments]
    D --> E[Payroll lines per period]
```

## 20. Employee Salary

```mermaid
flowchart LR
    A[Employee List] --> B[Compensation table]
    B --> C[Base Salary]
    B --> D[Custom item assignments]
    C --> E[Overtime calculation base]
    D --> F[Payroll draft pull]
```

## 21. Payslip

```mermaid
flowchart TD
    A[Payroll finalized] --> B[Payslip generated]
    B --> C[Employee opens /payslip]
    C --> D{Apakah ada slip bulan ini?}
    D -->|Ya| E[Tampilkan slip terkini]
    D -->|Tidak| F[Fallback ke periode final terbaru]
    E --> G[Download PDF]
    F --> G
    B --> H[Admin view /payslip-report]
```

## 22. Payroll THR

```mermaid
flowchart TD
    A[Admin set THR settings] --> B[Holiday date + cut-off]
    B --> C[Batch generate eligible employees]
    C --> D[Pro-rata calculation]
    D --> E[Disburse / Mark Paid]
    E --> F[Post-Payroll → purpose: thr]
    F --> G[Employee sees in payslip]
```

## 23. Payroll PKWT Compensation

```mermaid
flowchart LR
    A[Select month] --> B[Preview PKWT contracts ending]
    B --> C[Tenure + compensation estimate]
    C --> D[Generate draft payroll → purpose: pkwt_compensation]
    D --> E[Mark Paid]
    E --> F[Appears in payslip]
```

## 24. Goal Tracking

```mermaid
flowchart LR
    A[Admin: Goal Types] --> B[Employee: Create Goals]
    B --> C[Scope: me / team / all]
    C --> D[Manager monitor progress]
    C --> E[Admin view all]
```

## 25. Training

```mermaid
flowchart LR
    A[Training Types] --> B[Trainers]
    B --> C[Training Events]
    C --> D[Assign participants]
    D --> E[Employee self-history view]
```

## 26. Promotion

```mermaid
flowchart TD
    A[Admin creates promotion] --> B[Select employee]
    B --> C[Department from→to]
    C --> D[Designation from→to]
    D --> E[Date effective]
    E --> F[Snapshot preserved in history]
```

## 27. Resignation

```mermaid
flowchart TD
    A[HR records resignation] --> B[noticeDate, resignationDate, reason]
    B --> C[Status: pending]
    C --> D{Admin action}
    D -->|Approve| E[Approved]
    D -->|Cancel| F[Cancelled]
    E --> G[resignationDate must >= noticeDate]
```

## 28. Termination

```mermaid
flowchart TD
    A[Create Termination] --> B[Draft Review]
    B --> C[Legal Review]
    C --> D[Approved Internal]
    D --> E[Checklist items mandatory]
    E --> F{All checklist done?}
    F -->|Ya| G[Finalized Execution]
    F -->|Tidak| H[Blocked]
    G --> I[Settlement Preview]
    I --> J[Prorata salary + PKWT comp]
    J --> K[Asset Clearance]
    G --> L[Update employee status]
```

## 29. Asset Management

```mermaid
flowchart TD
    A[Categories] --> B[Asset Masters]
    B --> C[Assign to employee]
    C --> D[Assignment history]
    D --> E[Return]
    B --> F[Issue report]
    F --> G[Creates Ticket]
    B --> H[Attach files]
    B --> I[Clearance via Termination]
```

## 30. Tickets

```mermaid
flowchart TD
    A[Create Ticket] --> B[Open]
    B --> C[In Progress]
    C --> D[Resolved]
    D --> E[Closed]
    A --> F[Set assignee + SLA]
    A --> G[Attachments]
    A --> H[Comments]
    E --> I[Locked for employee edits]
```

## 31. Policies

```mermaid
flowchart LR
    A[Policy CRUD] --> B[Optional department relation]
    A --> C[Attachments]
    A --> D[Gate: policy.manage]
```

## 32. Document Center

```mermaid
flowchart TD
    A[Categories] --> B[Upload Document]
    B --> C[Visibility: hr_only / employee_visible]
    C --> D[Employee sees own docs]
    C --> E[HR sees all]
    B --> F[Feature gate: employee_document_center]
```

## 33. Calendar

```mermaid
flowchart LR
    A[FullCalendar] --> B[Custom Events CRUD]
    A --> C[Holiday overlays (read-only)]
    A --> D[Leave overlays (read-only)]
    B --> E[Create / Edit / Delete / Drag-drop / Resize]
```

## 34. Notes

```mermaid
flowchart LR
    A[Create Note] --> B[Tag: Personal/Social/Work/Others]
    B --> C[Priority: Low/Medium/High]
    B --> D[Important flag]
    A --> E[Trash → Restore / Permanent Delete]
    E --> F[Per-user per-company scope]
```

## 35. FAQ

```mermaid
flowchart LR
    A[FAQ CRUD] --> B[Search / Filter / Sort]
    A --> C[Export CSV/JSON]
    A --> D[Bulk delete]
    A --> E[Audit trail]
```

## 36. Knowledgebase

```mermaid
flowchart LR
    A[config/hcm_knowledgebase.php] --> B[Categories]
    B --> C[Article per category]
    C --> D[Slug-based routes]
    D --> E[Legacy redirects]
```

## 37. Global Search

```mermaid
flowchart LR
    A[Ctrl+/ shortcut] --> B[Debounced query]
    B --> C[GET /v1/hcm/search]
    C --> D[Quick results dropdown]
    D --> E[Enter → Full results page]
    E --> F[RBAC-filtered results]
```

## 38. Locations / Wilayah Sync

```mermaid
flowchart TD
    A[Scheduler monthly] --> B[Sync from wilayah.id API]
    A --> C[Manual trigger via cronjob]
    B --> D[Local DB: provinces/regencies/districts/villages]
    C --> D
    D --> E[Server-side pagination + search]
    E --> F[Used by employee address + company locale]
```

## 39. Cronjob Scheduler

```mermaid
flowchart LR
    A[Settings per job] --> B[Time, timezone, enabled, dayOfMonth]
    B --> C[Kernel reads from settings table]
    C --> D[Scheduled tasks run]
    D --> E[Payment reminders]
    D --> F[Wilayah sync]
    D --> G[Payroll refresh]
    D --> H[Leave accrual]
    D --> I[SaaS billing automation]
```

## 40. Landing Pages

```mermaid
flowchart TD
    A[Guest lands] --> B[Hero / Features / Pricing]
    B --> C[Choose plan]
    C --> D[React onboarding modal]
    D --> E[Fill company + owner data]
    E --> F[Create subscription: trial / pending_payment]
    F --> G[Redirect to login / checkout]
    G --> H[Workspace or payment]
```

## 41. Approval Settings (Detail)

```mermaid
flowchart TD
    A[Admin opens /approval-settings] --> B[Load 6 modules]
    B --> C[leave, overtime, resignation, termination, expense, offer]
    C --> D[Pick module]
    D --> E[Set mode: sequence / simultaneous]
    E --> F[Pick approvers from company members]
    F --> G[Save → PUT /v1/hcm/approval-settings/{module}]
    C --> H[Integration with module lifecycle]
    H --> I[Leave request → populate approver chain]
    H --> J[Overtime → notify configured approvers]
    H --> K[Resignation / Termination → notify approvers]
```

## 42. Auto-Renewal (Subscription)

```mermaid
flowchart TD
    A[Scheduler scans subscriptions] --> B{renewal_due_at near}
    B -->|Ya| C[DB lock]
    C --> D[Create pending invoice]
    D --> E[Snapshot: plan + pricing + tax]
    E --> F[Attempt payment via gateway]
    F --> G{Success?}
    G -->|Ya| H[Mark paid + extend period]
    G -->|Tidak| I[Wait for webhook retry]
    H --> J[Send lifecycle notification]
```

## 43. Export Reconciliation

```mermaid
flowchart TD
    A[Financial action triggered] --> B{Export evidence exists?}
    B -->|Ya| C[Allow action: finalize / disburse / post-payroll]
    B -->|Tidak| D[Block action]
    D --> E[POST /v1/reconciliation/exports]
    E --> B
    A --> F[Payroll finalize]
    A --> G[THR disburse]
    A --> H[PKWT post-payroll]
    A --> I[Mark paid]
    A --> J[Invoice verify]
```

## 44. Export Governance

```mermaid
flowchart LR
    A[Standardize all exports] --> B[Default format: xlsx]
    B --> C[Server-side auth + tenant scope]
    C --> D[Consistent filename convention]
    D --> E[Audit trail per export]
    E --> F[Gradual migration legacy → standard]
```

## 45. Recovery Vault

```mermaid
flowchart LR
    A[CRUD event fire] --> B[Capture before/after payload]
    B --> C[Store immutable: actor, company, metadata]
    C --> D[Scheduled snapshots]
    D --> E[90-day hot retention]
    E --> F[Super admin investigate / restore]
```

## 46. AI Assistant

```mermaid
flowchart TD
    A[User asks NL question] --> B[Intent classifier]
    B --> C{RBAC gate}
    C -->|Allowed| D[Internal API call]
    D --> E[AI compose answer with provenance]
    C -->|Denied| F[Deny-by-default response]
    E --> G[Audit trail per query]
    F --> G
```

## 47. SPT Masa PPh 21

```mermaid
flowchart TD
    A[Payroll finalized] --> B[Generate SPT snapshot]
    B --> C[Review bruto + PPh21 totals]
    C --> D[Mark ready]
    D --> E[Submit]
    E --> F[Export CSV DJP format]
    C --> G[Status: draft → ready → submitted]
```

## 48. Employee Allowance Governance

```mermaid
flowchart TD
    A[Create allowance policy] --> B[Transport / Meal / Communication]
    B --> C[Assign to employee or org scope]
    C --> D[Versioned assignment history]
    D --> E[Payroll draft pulls active allowances]
    D --> F[Compliance engine detects gaps]
    F --> G[Missing required / expired / overlap]
```

## 49. Email Settings

```mermaid
flowchart LR
    A[Global admin configures] --> B[SMTP / Mailtrap]
    B --> C[Test connection endpoint]
    C --> D[Settings saved encrypted]
    D --> E[Laravel mailer reads active profile]
    E --> F[Inbound via webhook]
```

## 50. Super Admin Dashboard

```mermaid
flowchart TD
    A[/dashboard /saas-dashboard] --> B[Platform KPI cards]
    A --> C[Company list + status]
    A --> D[Revenue monthly chart]
    A --> E[Subscription health]
    A --> F[Audit logs]
    B --> G[Middlware: hcm.web.global-admin]
```

## 51. Trial & Billing Dashboard

```mermaid
flowchart LR
    A[Two tabs] --> B[Trial companies]
    A --> C[Subscribed companies]
    B --> D[Status: active / pending_payment / expired]
    C --> E[Invoice summary + due date]
    E --> F[Email delivery status]
    D --> G[Drill-down to invoice detail]
```

## 52. Packages

```mermaid
flowchart TD
    A[Super admin CRUD] --> B[Code, name, pricing]
    B --> C[Features assignment]
    C --> D[Add-ons catalog]
    B --> E[is_global_admin_only flag]
    E --> F[Internal vs public packages]
    C --> G[Landing pricing display]
    C --> H[Onboarding selection]
```

## 53. Subscriptions

```mermaid
flowchart TD
    A[Create subscription] --> B[Link company → package]
    B --> C[Lifecycle states]
    C --> D[Trial → Active]
    C --> E[Pending_Payment → Active on invoice paid]
    C --> F[Active → Suspended / Expired / Cancelled]
    C --> G[Change Plan: preview → approval]
    G --> H[Recurring renewal invoice]
    C --> I[Employee limit enforcement]
```

## 54. Purchase Transactions

```mermaid
flowchart LR
    A[Transaction ledger] --> B[Filter by invoice / company / status]
    A --> C[Create with company + subscription]
    A --> D[Export CSV]
    B --> E[Legacy + bearer purchase coexist]
```

## 55. Domain Management

```mermaid
flowchart LR
    A[Register domain] --> B[DNS verification instructions]
    B --> C[Admin triggers manual verify]
    C --> D[Status: verified / pending / failed]
    A --> E[UUID-based route binding]
```

## 56. Mock Payment

```mermaid
flowchart LR
    A[Tenant invoice] --> B[POST /mock-pay]
    B --> C[Create payment record]
    C --> D[Mark invoice paid]
    D --> E[Activate subscription from pending_payment]
    A --> F[Dev: /mock-payment-tester.html]
    A --> G[Dev: /mock-hosted-payment.html]
```

## 57. PDP Compliance (UU PDP)

```mermaid
flowchart TD
    A[Onboarding] --> B[Consent: biometric, AI, cookie]
    B --> C[Encrypted storage: NIK, NPWP, bank, payslip]
    C --> D[Data Saya portal: view / export]
    D --> E[Right to erasure workflow]
    D --> F[Breach notification system]
    C --> G[Data retention auto-purge]
    C --> H[Session timeout]
```

## 58. UUID Migration

```mermaid
flowchart LR
    A[Legacy integer IDs] --> B[Add UUID columns to tables]
    B --> C[Dual-write: int + uuid during migration]
    C --> D[API supports both identifiers]
    D --> E[Full PK cutover pending]
    D --> F[Some domains: uuid + numeric fallback]
```

## 59. 7-Table Integration Closure

```mermaid
flowchart LR
    A[tickets.category_id → ticket_categories] --> B[Added FK constraint]
    C[hcm_trainings.trainer_id → hcm_trainers] --> D[Added FK constraint]
    B --> E[Dual payload: legacy string + new FK]
    D --> E
    E --> F[Models + controllers + JS updated]
```

## 60. Security Check

```mermaid
flowchart TD
    A[Tier 1: Pre-push] --> B[Local verify: lint, gitleaks]
    B --> C[Tier 2: Pre-merge CI]
    C --> D[gitleaks, Semgrep ERROR]
    C --> E[composer audit, npm audit]
    E --> F[Tier 3: Pre-release DAST]
    F --> G[Penetration test surface]
```

## 61. Team Management

```mermaid
flowchart LR
    A[Create team] --> B[Cross-departmental workgroup]
    B --> C[Team lead assigned]
    C --> D[Members via employee form / bulk]
    D --> E[Shift/schedule planner supports team]
    D --> F[Delete blocked if members exist]
```

## 62. BPJS Governance

```mermaid
flowchart TD
    A[Rate Baselines] --> B[Kesehatan, JHT, JP, JKK, JKM]
    B --> C[Field-level lock on structural data]
    D[Employee Membership] --> E[BPJS Kesehatan number]
    D --> F[BPJS Ketenagakerjaan number]
    B --> G[Compliance score calculation]
    D --> G
```

## 63. Tax Governance

```mermaid
flowchart TD
    A[Admin creates PPh 21 policy] --> B[STATUTORY_PPH21 / TER]
    B --> C[Rate schedules]
    C --> D[Workflow: draft → submit → approve → publish → superseded]
    D --> E[Compliance snapshot]
    E --> F[Policy coverage / NPWP / PTKP quality]
    A --> G[Platform-level: SPT PPN / PPh23 reports]
```

## 64. BPJS Governance (Rate Detail)

```mermaid
flowchart LR
    A[Program: Kesehatan] --> B[Company rate % + Employee rate %]
    C[Program: JHT] --> D[Company rate % + Employee rate %]
    E[Program: JP] --> F[Company rate % + Employee rate %]
    G[Program: JKK] --> H[Risk-based rate]
    I[Program: JKM] --> J[Fixed rate]
    A --> K[Per-tenant with national fallback]
    C --> K
    E --> K
    G --> K
    I --> K
```

## 65. Inter-Module Data Flow (Lengkap)

```mermaid
flowchart LR
    ID[Identity Auth] --> ALL[All Modules]
    EMP[Employees] --> ATD[Attendance]
    EMP --> LV[Leave]
    EMP --> OT[Overtime]
    EMP --> PAY[Payroll]
    EMP --> PERF[Performance]
    EMP --> LIFE[Lifecycle]
    EMP --> TRAIN[Training]
    EMP --> GOAL[Goals]
    ATD --> PERF
    ATD --> LV
    LV --> ATD
    OT --> PAY
    PAY --> PAYSLIP[Payslip]
    PAY --> TX[TaxGovernance]
    PAY --> BPJS[BPJS]
    PAY --> ALW[Allowance]
    PAY --> SPT[SPTMasa]
    ASSET[Asset] --> TERM[Termination]
    TICKET[Tickets] --> ASSET
    APPROV[ApprovalSettings] --> LV
    APPROV --> OT
    APPROV --> LIFE
    NOTIF[Notifications] --> ALL
    REPORT[Reporting] --> ALL
    EXPORT[ExportReconciliation] --> PAY
    EXPORT --> THR[PayrollTHR]
    EXPORT --> PKWT[PayrollPKWT]
    EXPORT --> BILL[Billing]
    CRON[Cronjob] --> LOC[Locations]
    CRON --> SUB[Subscriptions]
    CRON --> SPT
    SUB --> BILL
    SUB --> DOMAIN[Domain]
    SUB --> AUTO_RENEW[AutoRenewal]
    PKG[Packages] --> SUB
    PKG --> LAND[LandingPages]
    LAND --> TRIAL[TrialBilling]
    TRIAL --> SUB
    PDP[PDPCompliance] --> ID
    PDP --> ATD
    PDP --> AI[AI Assistant]
    GUARD[SecurityCheck] --> ALL
    KNW[Knowledgebase] --> ALL
    SEARCH[GlobalSearch] --> ALL
```

---

## Catatan

- Flowchart di atas berdasarkan **runtime aktual** (`backend/routes/api/`, controller, model).
- Mermaid render di GitHub, VS Code, atau `https://mermaid.live/`.
- Update flowcharts ini jika route atau alur bisnis berubah secara substantif.
