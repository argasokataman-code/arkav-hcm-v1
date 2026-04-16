## Summary
- What changed:
- Why:
- Scope (backend/frontend/docs/db):

## Related
- Issue/Ticket:
- Docs/Spec references:

## Change Type
- [ ] Feature
- [ ] Bugfix
- [ ] Refactor
- [ ] Docs only
- [ ] Security hardening
- [ ] Migration/schema change

## Validation
- [ ] Local tests passed (list suites/commands below)
- [ ] Manual QA done for impacted flows
- [ ] No breaking API change, or breaking change documented

### Test Evidence
```bash
# put executed commands + short result
```

## API Contract and Documentation
- [ ] `docs/api/openapi.yaml` updated (if API request/response/status changes)
- [ ] Feature API doc updated in `docs/api/`
- [ ] `docs/planning/implementation-status.md` updated (if substantive)
- [ ] `docs/planning/active-hcm-templates-and-permissions.md` updated (if role/menu/permission changed)

## Agent rules (Cursor + GitHub Copilot)
- [ ] Jika PR mengubah **kebijakan proses / guardrail agen** (isi `.cursor/rules/*.mdc` atau navigasi `AGENTS.md`): ringkasan di [`.github/instructions/`](.github/instructions/) dan indeks [`.github/instructions/README.md`](.github/instructions/README.md) ikut diperbarui agar tidak divergen dari Cursor.

## Security Checklist
- [ ] AuthN/AuthZ checks enforced on server side
- [ ] Sensitive endpoints covered with forbidden/unauthorized tests
- [ ] No secret/token/key exposed in code or logs
- [ ] Input validation/sanitization added for new inputs
- [ ] Web route guard rules respected for GET/HEAD pages

## Database and Migration Checklist
- [ ] Migration is idempotent and has safe `down()` behavior
- [ ] Index/constraint changes verified on target DB engine
- [ ] Data backfill impact considered
- [ ] Rollback impact considered

## Risk and Rollout
- Risk level: `Low | Medium | High`
- Rollout plan:
- Rollback plan:

## Post-merge
- [ ] Need environment variable updates
- [ ] Need one-time command/backfill
- [ ] Need release note entry
