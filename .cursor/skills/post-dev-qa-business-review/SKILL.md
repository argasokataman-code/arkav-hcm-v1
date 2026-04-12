---
name: post-dev-qa-business-review
description: Cross-checks code quality, business flows, and cross-system integration after substantive development or when a task is marked complete. Applies senior QA and senior business analyst lenses—happy paths, edge cases, RBAC, API/UI contracts, and integration boundaries. Use when development finishes, the user asks for QA or BA review, sign-off, integration check, or "sudah selesai" on a feature.
---

# Post-Development QA + Business Analyst Review

## When to run

Run **without waiting to be asked** when:

- A substantive coding task (feature, API, UI wiring, migration, auth/RBAC) is **finished or claimed done**
- The user explicitly asks for QA review, BA validation, integration check, or release readiness

**Skip or shorten** for typos-only, comment-only, or purely cosmetic changes unless the user wants a full pass.

## Roles (two hats)

1. **Senior QA** — correctness, regressions, edge cases, security basics, testability, observable behavior vs acceptance intent.
2. **Senior Business Analyst** — end-to-end flow coherence, actor responsibilities, data ownership, handoffs between UI/API/DB/external systems, and **integration** (contracts, enums, idempotency, error semantics).

## Workflow

1. **Restate scope** in one sentence: what changed and for whom (roles/personas).
2. **Trace the flow** from entry (UI route, API, job, webhook) to persistence and back; note every handoff.
3. **Run the checklists** below; mark each item **Pass / Gap / N/A** with evidence (file, endpoint, or behavior).
4. **Prioritize findings**: blocking vs follow-up; map each gap to owner (FE/BE/DB/docs).
5. **Output** using the template in "Report format".

## Code quality (QA)

- **Correctness**: logic matches stated rules; null/empty/race basics handled.
- **Regression**: existing callers still valid; feature flags or defaults safe for old data.
- **Security**: authn/authz on new surfaces; IDOR; input validation server-side; no secrets in repo.
- **Errors**: HTTP status and API envelope consistent; messages safe for users.
- **Tests**: happy path + at least one negative (401/403/422) where risk exists; migrations verified if schema changed.
- **Performance**: obvious N+1, unbounded lists, missing indexes called out.

## Business + integration (BA)

- **Actors & permissions**: who can start, approve, view, or mutate; aligns with product intent and enforced on server.
- **State machine**: valid transitions only; invalid states rejected with clear domain errors.
- **Data contract**: field names and types consistent across UI ↔ API ↔ DB; enums documented and aligned.
- **Cross-system**: external services, webhooks, exports, imports—timeouts, retries, idempotency, partial failure behavior.
- **Operational story**: audit trail, supportability, empty states, and "what if upstream is down?"

## Alignment with this repository

If the workspace is **arcav_new_v2**, also verify against active project rules (do not replace them—**compose** with this skill):

- `.cursor/rules/quality-anomaly-pass.mdc` — anomaly dimensions
- `.cursor/rules/development-closure-checklist.mdc` — security, docs, OpenAPI
- `.cursor/rules/backend-template-lock.mdc` — HCM UI patterns and `public/build/js` sync when JS changes
- `.cursor/rules/migration-discipline.mdc` — schema changes include migration + verify
- `docs/planning/active-hcm-templates-and-permissions.md` — role vs page/API when HCM paths change

For deeper checklists and examples, see [reference.md](reference.md).

## Report format

Use this structure (adjust depth to change size):

```markdown
## Scope
[One sentence]

## Flow trace
1. [Entry] → … → [Exit/persistence]

## QA summary
| Area        | Result        | Notes |
|------------|---------------|-------|
| Correctness | Pass/Gap/N/A | …     |
| Security    | …            | …     |
| Tests       | …            | …     |

## BA / integration summary
| Topic           | Result        | Notes |
|----------------|---------------|-------|
| Actors/RBAC    | …             | …     |
| Contracts      | …             | …     |
| Cross-system   | …             | …     |

## Findings
### Blocking
- …

### Non-blocking / next
- …

## Sign-off
**Ready for merge/release:** Yes / No — [one-line reason]
```

## Language

Write the report in the **same language as the user** for the current thread (e.g. Indonesian if the user uses Indonesian), unless they ask otherwise.
