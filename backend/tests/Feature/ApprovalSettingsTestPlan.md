# Approval Settings — Test Plan

## Current State Audit

| Area | Status | Gap |
|------|--------|-----|
| **API Controller** | `HcmApprovalSettingsController` 151 line ✅ | 3 endpoints exist |
| **Service** | `ApprovalConfigService` 251 line ✅ | Full logic: CRUD + populate + process + resolve |
| **Models** | `HcmApprovalConfig`, `HcmApprovalConfigApprover` ✅ | Migrations + relationships exist |
| **Blade View** | `settings/approval-settings.blade.php` 309 line ✅ | UI rendered, AJAX wired |
| **API Docs** | `docs/features/approval-settings/API.md` ✅ | Contract documented |
| **Feature Doc** | `docs/features/approval-settings/README.md` 496 line ✅ | Business flow documented |
| **Tests** | ❌ **0 files** | No coverage at all |
| **Leave Integration** | ⚠️ `populateLeaveApprovals()` exists | But NOT called from LeaveRequest lifecycle |
| **Frontend JS** | ⚠️ Uses `arcav_access_token` ✅ | But doc says old code used `hcm_token` — already fixed? |

## Test Plan (TTD)

### 1. ApprovalConfigServiceTest — Unit Tests

**1.1 — CRUD Config**
| # | Test | Expected |
|---|------|----------|
| 1 | `getConfigForModule` returns null when no config | Null |
| 2 | `getConfigForModule` returns config when exists | Config + approvers loaded |
| 3 | `getConfigForModule` ignores inactive config | Null |
| 4 | `getConfigForModule` scoped by company_id | Company B gets null |
| 5 | `upsertConfig` creates new config + approvers | Config created, 2 approvers |
| 6 | `upsertConfig` updates existing config + replaces approvers | Old approvers deleted, new ones inserted |
| 7 | `upsertConfig` skips non-existent user IDs | Only valid users inserted |
| 8 | `upsertConfig` with duplicate module for same company | Upsert (no duplicate) |

**1.2 — populateLeaveApprovals**
| # | Test | Expected |
|---|------|----------|
| 9 | Populates LeaveApproval rows for sequence mode | Rows created with levels 1,2,3 |
| 10 | Populates LeaveApproval rows for simultaneous mode | Rows created with levels 1,1,1 |
| 11 | Returns level-1 approvers for sequence mode | Only first level |
| 12 | Returns all approvers for simultaneous mode | All approvers |
| 13 | Empty collection if no config exists | Empty |
| 14 | Stale leave_approvals deleted before re-insert | Clean slate |

**1.3 — processApprovalDecision**
| # | Test | Expected |
|---|------|----------|
| 15 | Approve in sequence mode — advance chain | Status stays pending, next approver returned |
| 16 | Approve last level in sequence mode — full approval | Status = approved |
| 17 | Approve in simultaneous mode — still waiting | Status stays pending |
| 18 | Approve last pending in simultaneous mode — full approval | Status = approved |
| 19 | Reject — immediate declined regardless of mode | Status = declined |
| 20 | No config — fallback to simple decision | approved/declined based on input |
| 21 | Double approve same level — idempotent | No error, status unchanged |

**1.4 — resolveApproversToNotify**
| # | Test | Expected |
|---|------|----------|
| 22 | Sequence mode returns only level 1 | 1 user |
| 23 | Simultaneous mode returns all approvers | All users |
| 24 | No config returns empty | Empty |
| 25 | Empty approvers list returns empty | Empty |

**1.5 — getEligibleApprovers**
| # | Test | Expected |
|---|------|----------|
| 26 | Returns active company members | Users in company_users with status=active |
| 27 | Excludes inactive users | Not returned |
| 28 | Search by name | Filtered results |
| 29 | Search by email | Filtered results |
| 30 | Search by designation | Filtered results |
| 31 | Returns max 20 results | ≤20 |
| 32 | Scoped by company_id | Other company users not included |

**1.6 — Query Count Regression**
| # | Test | Expected |
|---|------|----------|
| 33 | `getConfigForModule` ≤ 2 queries | With eager load |
| 34 | `upsertConfig` ≤ 8 queries | Transaction + delete + inserts |
| 35 | `populateLeaveApprovals` ≤ 5 queries | Check + delete + insert |
| 36 | `processApprovalDecision` ≤ 6 queries | Lock + update + count |

### 2. HcmApprovalSettingsApiTest — API Feature Tests

**2.1 — GET /v1/hcm/approval-settings**
| # | Test | Expected |
|---|------|----------|
| 37 | Returns 401 without token | 401 |
| 38 | Returns 400 without company context | 400 |
| 39 | Returns empty configs for new company | All modules with defaults |
| 40 | Returns saved config after upsert | Config with approvers |
| 41 | Only returns configs for modules where hasFeature() = true | Filtered |
| 42 | 403 for non-admin user | 403 |

**2.2 — PUT /v1/hcm/approval-settings/{module}**
| # | Test | Expected |
|---|------|----------|
| 43 | Creates new config | 200, config returned |
| 44 | Updates existing config | 200, approvers replaced |
| 45 | 422 for invalid module | 422 |
| 46 | 422 for empty approvers | 422 |
| 47 | 422 for more than 10 approvers | 422 |
| 48 | 422 for non-existent user IDs | 422 |
| 49 | 422 for approver not in company (tenant isolation) | 422 |
| 50 | 403 for non-admin user | 403 |
| 51 | 422 for invalid approvalMode | 422 |
| 52 | Valid sequence mode saved | Config.approval_mode = sequence |
| 53 | Valid simultaneous mode saved | Config.approval_mode = simultaneous |

**2.3 — GET /v1/hcm/approval-settings/eligible-approvers**
| # | Test | Expected |
|---|------|----------|
| 54 | Returns active company members | Paginated list |
| 55 | Search by name works | Matched results |
| 56 | 403 for non-admin | 403 |

### 3. Frontend / Blade — Manual Verification Checklist

| # | Check | Expected |
|---|-------|----------|
| 57 | Page loads without JS error | Console clean |
| 58 | Select2 dropdown loads eligible approvers | AJAX search works |
| 59 | Radio button reflects saved mode | Sequence/Simultaneous checked |
| 60 | Save button sends correct PUT payload | Network tab |
| 61 | Success alert shown after save | Green alert |
| 62 | Error alert shown on failure | Red alert |
| 63 | Only active modules rendered | Respects hasFeature() |
| 64 | localStorage token fallback works | `arcav_access_token` key |

---

## Implementation Order

```
Phase 1 — Service Unit Tests (ApprovalConfigServiceTest)
  → 36 test methods, query count assertions included

Phase 2 — API Feature Tests (HcmApprovalSettingsApiTest)
  → 20 test methods covering RBAC, validation, tenant isolation

Phase 3 — Verify Frontend JS (manual)
  → Check token key, Select2, alert feedback

Phase 4 — Leave Integration (if needed)
  → Wire populateLeaveApprovals into LeaveRequest lifecycle
```

## Estimated Coverage

| Layer | Before | After |
|-------|--------|-------|
| Service | 0% | 90%+ (36 tests) |
| API | 0% | 90%+ (20 tests) |
| Frontend | Manual only | Manual checklist |
| **Total** | **0%** | **~85%** |
