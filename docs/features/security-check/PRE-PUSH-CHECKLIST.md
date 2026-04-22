# Pre-Push Checklist (Manual Gate - Local Verification)

**Objective:** Verify security readiness before pushing changes

---

## Before Every Push

- [ ] Run Semgrep locally for ERROR severity findings
- [ ] Run `npm audit` in backend/ (should show 0 HIGH + moderate)
- [ ] Run `composer audit --locked` (should show 0 vulnerabilities)
- [ ] Run unit tests: `php artisan test` (all PASS)
- [ ] Run Vitest: `npm run test` (all PASS)
- [ ] Build frontend: `npm run build` (0 errors)
- [ ] Verify no sensitive files staged: `git status` (no .env, key.pem, secrets)

---

## Local Command Reference

```bash
# Backend validation
cd backend

# Security scans
semgrep --config=auto --severity=ERROR app/ routes/ --exclude=vendor
npm audit --audit-level=high
composer audit --locked

# Tests
php artisan test
npm run test

# Build
npm run build

# Pre-commit check
cd ..
bash scripts/check-deploy-runtime-guard.sh
bash scripts/check-api-docs-sync.sh --staged
bash scripts/check-tests-on-change.sh --staged
```

---

## What NOT to Push

- [ ] ❌ `.env` files (config/secrets)
- [ ] ❌ `key.pem` or private key material
- [ ] ❌ API_KEY values (use environment variables)
- [ ] ❌ Failed tests (red flags in test output)
- [ ] ❌ npm/composer audit failures
- [ ] ❌ Semgrep ERROR findings

---

## If Guard Fails

1. **API guard failing:** Update `docs/api/openapi.yaml` or `docs/api/<feature>-api.md`
2. **Test guard failing:** Ensure modified controllers have test coverage
3. **Deploy guard failing:** Run `bash scripts/check-deploy-runtime-guard.sh` to diagnose
4. **Semgrep ERROR:** Review finding, add documented suppression if false positive

---

## Contact

Questions on gate failures? See: [AUDIT-REPORT-2026-04-22.md](./AUDIT-REPORT-2026-04-22.md) for remediation patterns.
