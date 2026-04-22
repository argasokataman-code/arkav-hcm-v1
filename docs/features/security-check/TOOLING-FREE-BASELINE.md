# Tooling-Free Baseline Controls

**Objective:** Security controls that require no external tools (grepping, review patterns)

---

## 1. Git History Secret Scanning (Manual)

**Pattern:** Look for exposed credentials in commits

```bash
# Search for common patterns in last 100 commits
git log -p -S 'password\|api_key\|secret' --all --oneline | head -50

# Or check specific risky files
git log --all -- "*.pem" "*.key" ".env*" | head -20
```

**Remediation:** If found, untrack + .gitignore

---

## 2. Validation Rule Pattern Audit (Code Review)

**What to look for:** String-concatenated unique rules

```php
// ❌ Risky pattern
'email' => 'unique:users,email,' . $user->id,

// ✅ Safe pattern
'email' => [..., Rule::unique('users', 'email')->ignore($user->id)],
```

**How to find:**
```bash
grep -rn "unique:" backend/app/Http/Controllers/Api --include="*.php" | grep "'" | head -20
```

**Owners:** API controller authors

---

## 3. Dependency Review (Manual)

**npm packages:**
```bash
cd backend
npm list | grep "[XXXX]"  # flags deprecated/problematic
npm outdated                # shows outdated packages
```

**Composer packages:**
```bash
cd backend
composer outdated           # shows outdated PHP packages
composer show --outdated    # verbose output
```

**Review Schedule:** Monthly or post-patch releases

---

## 4. Environment Variable Secrets (File Review)

**Files to audit:**
- `.env.example` (template - should NOT contain real values)
- `.env` (local - should never be committed)
- `.env.production` (production - should not exist in repo)

```bash
# Verify .env not tracked
git ls-files | grep "\.env$"  # should be empty

# Verify .gitignore has .env rules
grep "\.env" .gitignore
```

---

## 5. Sensitive File Containment (File Review)

**Files to protect:**
- `*.pem` (private keys)
- `*.key` (API keys, private material)
- `*secret*` (anything with secret in name)
- `.vscode/mcp.env` (MCP secrets)

```bash
# Find risky files currently tracked
git ls-files | grep -i "pem\|key\|secret"  # should be empty

# Verify .gitignore protects them
grep -E "pem|key|secret" .gitignore
```

---

## 6. API Response Data Validation (Manual Testing)

**Pattern:** Verify no sensitive data leaked in responses

```bash
# Auth endpoint - check password NOT in response
curl -X POST http://localhost:8007/v1/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"secret123"}' | jq .

# Should NOT show: password, secret, token (except auth token)
```

---

## 7. Form Method Audit (Code Review)

**Risky pattern:** GET form with sensitive params

```html
<!-- ❌ Risky -->
<form method="get" ...>
  <input name="password" ...>

<!-- ✅ Safe -->
<form method="post" ...>
  <input name="password" ...>
```

**How to find:**
```bash
grep -rn "method.*get" backend/resources/views --include="*.blade.php" | head -20
```

---

## 8. URL Query Parameter Audit (Manual Testing)

**Pattern:** Sensitive data appearing in URLs

```bash
# Check onboarding flow doesn't expose params
curl http://localhost:5179/onboarding?email=user@test.com&password=secret

# Browser address bar should NOT show email/password after load
```

---

## 9. Dependency License Review (Manual)

**Pattern:** Verify no GPL/copyleft in production deps

```bash
cd backend

# npm licenses
npm ls --depth=0 | grep -i "gpl\|agpl\|copyleft"

# composer licenses
composer licenses | grep -i "gpl\|agpl"
```

---

## 10. Code Comment Audit (Manual)

**Pattern:** Look for accidentally committed secrets in comments

```bash
# Find TODO/FIXME with potential secrets
grep -rn "TODO\|FIXME" backend/app --include="*.php" | grep -i "password\|key\|secret\|token" | head -10
```

---

## Monthly Checklist

- [ ] Run npm audit & composer audit (verify still 0 HIGH)
- [ ] Check git log for risky patterns (grep -p -S patterns)
- [ ] Review API response samples (no sensitive data leakage)
- [ ] Audit `.gitignore` rules still present
- [ ] Verify `.env*` not tracked (`git ls-files`)
- [ ] Check form methods (no GET with sensitive input)
- [ ] Review latest dependency updates
- [ ] Spot-check validator patterns in new controllers

---

## Quarterly Deep Dive

- [ ] Full code review of validation rules
- [ ] Audit all forms for method + param sensitivity
- [ ] Review all API endpoints for data leakage
- [ ] Dependency supply chain analysis
- [ ] Documentation accuracy check (README, API docs)

---

## Reference

- [AUDIT-REPORT-2026-04-22.md](./AUDIT-REPORT-2026-04-22.md) - Detailed findings
- [PRE-PUSH-CHECKLIST.md](./PRE-PUSH-CHECKLIST.md) - Local verification
- `.github/workflows/security-gates.yml` - Automated gates
