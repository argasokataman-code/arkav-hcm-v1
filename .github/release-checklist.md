# Release Checklist

## 1. Branch and Scope
- [ ] Base branch is correct (`main` unless specified)
- [ ] PR is focused and does not include unrelated files
- [ ] Changelog/release summary prepared

## 2. Quality Gates
- [ ] Critical feature tests pass
- [ ] Regression tests for bugfixes added/passing
- [ ] Migration commands tested in staging or local equivalent
- [ ] No lint/compile/type errors in changed modules

## 3. API and Docs Sync
- [ ] `docs/api/openapi.yaml` matches implementation
- [ ] Affected API docs in `docs/api/` updated
- [ ] Planning docs updated when feature scope changed
- [ ] Role/permission matrix docs updated if access changed

## 4. Security and Access
- [ ] Auth middleware and role checks enforced on new/changed endpoints
- [ ] Forbidden/unauthorized scenarios tested
- [ ] Sensitive headers/cookies/session behavior validated
- [ ] No secret files committed (`.env`, keys, tokens)

## 5. Database Safety
- [ ] New constraints/indexes validated on target engine
- [ ] Duplicate/conflicting indexes checked
- [ ] Migration order and rollback path verified
- [ ] Data integrity impact assessed (especially payroll/leave)

## 6. Deployment Readiness
- [ ] Environment variables documented
- [ ] One-time commands documented (if any)
- [ ] Backward compatibility/consumer impact communicated
- [ ] Monitoring/logging points identified for post-deploy

## 7. Post Deploy
- [ ] Smoke test on key HCM flows completed
- [ ] Error logs/alerts monitored after deploy
- [ ] Follow-up tasks recorded
