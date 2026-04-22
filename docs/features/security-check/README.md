# Security Check Feature (SOP)

**Status:** Active (Cycles 0-4 complete: secret containment, validation hardening, dependency remediation, SAST gate enforcement)

## Overview

Security check is an internal SOP (not a user-facing product feature) that establishes a mandatory three-tier security gate system:

1. **Pre-push:** Manual local verification (git hooks, linter output)
2. **Pre-merge:** Automated CI gates (GitHub Actions) - gitleaks, Semgrep ERROR, composer audit, npm audit
3. **Pre-release:** DAST baseline (OWASP ZAP) + authenticated per-role testing

## Purpose

- **Prevent secret exposure** in version control (gitleaks)
- **Block SAST findings** at ERROR severity (Semgrep OSS)
- **Validate dependency health** (npm/composer audit)
- **Enable rapid mitigation** when issues surface
- **Document remediation evidence** for audit trail

## Scope

**Coverage:**
- Backend PHP/Laravel API controllers, routes, validation rules
- Frontend JS/TS assets, build config
- Sensitive file containment (.env, key material, credentials)
- Dependency supply chain (npm, composer)

**Out of Scope:**
- User-facing UI/UX testing (separate feature)
- Runtime infrastructure hardening (DevOps scope)
- Third-party SaaS credential management (external action)

## Cycles Completed

| Cycle | Focus | Status | Evidence |
|-------|-------|--------|----------|
| **0** | Secret triage + containment | ✅ Complete | `backend/fileconfig/key.pem` untracked, `.gitignore` protected |
| **1A** | Auth hardening + onboarding URL leak | ✅ Complete | POST form method, query param scrubbing, unique rule validation |
| **1B** | Additional unique rule hardening | ✅ Complete | 5 controllers updated to fluent Rule::unique() form |
| **2** | NPM dependency security | ✅ Complete | npm audit → 0 vulnerabilities (axios, vite, vitest, tar upgraded) |
| **3/4** | SAST re-scan + CI gate enforcement | ✅ Complete | Semgrep ERROR → 0 findings, `.github/workflows/security-gates.yml` created |

## Next Steps

- **Cycle 5 (Planned):** DAST authenticated scan per role (admin, employee, super_admin)
- **Cycle 6 (Planned):** Runtime hardening headers (CSP, SRI, X-Powered-By removal)
- **External Action (Pending):** Rotate CONTEXT7_API_KEY + backend/fileconfig/key.pem equivalent if used in production

## Documentation

- [README (this file)](./README.md) - Overview + status snapshot
- [TRACKER.md](./TRACKER.md) - Real-time evidence log per cycle + test validation
- [FIX-PLAN-CYCLES.md](./FIX-PLAN-CYCLES.md) - Detailed cycle definitions + exit criteria
- [IMPLEMENTATION.md](./IMPLEMENTATION.md) - Code changes + test results
- [AUDIT-REPORT-2026-04-22.md](./AUDIT-REPORT-2026-04-22.md) - Full findings triage + remediation
- [PRE-PUSH-CHECKLIST.md](./PRE-PUSH-CHECKLIST.md) - Manual gate verification steps
- [TOOLING-FREE-BASELINE.md](./TOOLING-FREE-BASELINE.md) - Baseline controls without external tools

## Contact

Security-check SOP maintained by: Engineering Team  
Issues / escalations: Use main GitHub issue tracker with label `security`
