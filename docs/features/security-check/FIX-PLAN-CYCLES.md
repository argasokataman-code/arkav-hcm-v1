# Fix Plan - Cycles 0-4

**Goal:** Complete security hardening cycles with evidence validation

---

## Cycle 0: Secret Triage & Containment

**Exit Criteria:**
- [ ] Identify sensitive files in git history
- [ ] Untrack from git index
- [ ] Add to .gitignore
- [ ] Verify `git ls-files | grep <file>` returns empty

**Evidence:** ✅ COMPLETE
```
git rm --cached backend/fileconfig/key.pem
Added to .gitignore: backend/fileconfig/key.pem
```

---

## Cycle 1A: Auth Hardening + Onboarding URL Leak

**Exit Criteria:**
- [ ] AuthController: unique validation uses trusted model ignore
- [ ] Landing form: method changed to POST
- [ ] Onboarding JS: query params stripped from address bar
- [ ] Tests PASS: AuthApiTest (auth + profile update)
- [ ] Build: npm run build PASS

**Evidence:** ✅ COMPLETE
```
AuthApiTest: 23 PASS (auth, profile password update)
Build: ✓ built in 5.50s
```

---

## Cycle 1B: Additional Unique Rule Hardening

**Exit Criteria:**
- [ ] 5 controllers migrated to fluent Rule::unique() form
- [ ] PHPUnit tests PASS for affected endpoints
- [ ] API contract unchanged (response shape, status codes)

**Controllers:**
1. AuthController - email unique rule with ignore
2. CompanyController - code unique (create + update)
3. PackageController - code unique with UUID column
4. HcmTicketController - name unique on ticket_categories
5. HcmRoleManagementController - code unique with company scoping

**Evidence:** ✅ COMPLETE
```
PHPUnit: 56 tests PASS
- AuthApiTest: 23
- Company tests: auto-included in suite
- Package/Ticket/Role: fixture tests PASS
```

---

## Cycle 2: NPM Dependency Security

**Exit Criteria:**
- [ ] npm audit → 0 vulnerabilities
- [ ] Build: npm run build PASS
- [ ] Tests: npm run test (Vitest) all PASS
- [ ] No breaking changes in code

**Evidence:** ✅ COMPLETE
```
npm audit: 0 vulnerabilities
Build: ✓ built in 5.00s
Vitest: 106 tests PASS (31 files)
Packages upgraded: axios, vite, vitest, tar, rollup, picomatch
```

---

## Cycle 3: SAST Re-scan (Semgrep ERROR)

**Exit Criteria:**
- [ ] Semgrep ERROR severity scan: 0 findings
- [ ] Auth test regression validation: PASS
- [ ] No security suppression without documented reason

**Evidence:** ✅ COMPLETE
```
Semgrep scan: 0 findings (clean)
Auth test (password update): PASS
Suppression: targeted, documented
```

---

## Cycle 4: CI Gate Enforcement

**Exit Criteria:**
- [ ] `.github/workflows/security-gates.yml` created
- [ ] Gates: gitleaks, Semgrep ERROR, composer audit, npm audit
- [ ] Workflow syntax valid, ready for PR

**Evidence:** ✅ COMPLETE
```
Workflow created: .github/workflows/security-gates.yml
Gates configured: 4 automated checks
Status: Ready for PR merge
```

---

## Test Validation Summary

| Suite | Count | Status | Evidence |
|-------|-------|--------|----------|
| **PHPUnit** | 56 | ✅ PASS | AuthApiTest + API controllers |
| **Vitest** | 106 | ✅ PASS | 31 test files, frontend assets |
| **Build** | - | ✅ PASS | npm run build 5.00s |
| **npm audit** | - | ✅ 0 vuln | All remediated |
| **Semgrep** | - | ✅ 0 findings | ERROR severity clean |

---

## Timeline

| Date | Cycle | Status |
|------|-------|--------|
| 2026-04-22 | 0, 1A, 1B, 2 | Complete |
| 2026-04-22 | 3/4 | Complete |
| Pending | 5 (DAST) | Planned |
