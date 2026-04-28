---
name: backend-engineer-pro
description: 'Senior backend engineering workflow for scalable HCM systems. Use when designing or implementing backend modules, APIs, schema changes, RBAC, multi-tenant isolation, anomaly analysis, and production hardening with strict validation and performance checks.'
argument-hint: 'Feature/module, business goal, and constraints'
user-invocable: true
---

# Backend Engineer Pro

Production-grade backend workflow for enterprise HCM systems with strict focus on data integrity, scalability, security, and maintainability.

## When to Use
- Build or refactor backend features for HCM modules (employee, payroll, attendance, tax, leave, RBAC)
- Design or review database schema and relationships
- Define or implement REST APIs and contracts
- Investigate anomalies in data integrity, performance, or authorization
- Validate multi-tenant boundaries and role-permission enforcement
- Prepare backend changes for production rollout

## Repository Rule Enforcement (Mandatory for This Repo)
When used in this repository, backend work must follow repository governance rules.

Mandatory rules:
1. Security first: enforce authorization server-side, not UI-only; validate tenant isolation on every tenant-scoped endpoint.
2. Documentation sync: update affected docs under docs/features and docs root when backend behavior changes.
3. API contract sync: if route/controller contract changes, update both docs/api/openapi.yaml and related docs/api/<feature>-api.md.
4. HCM role alignment: keep endpoint permissions aligned with docs/planning/active-hcm-templates-and-permissions.md.
5. Local test gate before completion claim or push: run bash scripts/local-test-gate.sh and require full pass evidence.
6. Deploy/runtime guard awareness: if touching deploy/runtime files, follow scripts/check-deploy-runtime-guard.sh discipline.
7. No contract drift: do not change active API contracts unless required by bug, security issue, regression, or approved new feature.

Completion evidence required in this repo:
1. Security checks performed and results summarized
2. Docs and OpenAPI sync status summarized
3. Local test gate result summarized
4. Any unresolved risk explicitly listed

## Outcome
This skill produces:
1. A structurally correct backend design and implementation plan
2. Schema and API decisions aligned to business workflow
3. Security and tenant isolation validation
4. Performance and anomaly checks before closure
5. Actionable optimization recommendations

## Core Mindset
- Think in systems, not isolated endpoints
- Prioritize correctness first, then performance, then convenience
- Keep logic predictable, testable, and extensible
- Avoid overengineering, but design for realistic scale

## Architecture Principles
- Clean/Modular structure (Controller, Service, Repository, Domain)
- Separation of concerns and single responsibility
- Secure by default
- Explicit validation and error handling
- Backward-compatible API evolution unless change is approved

## Standard Workflow

### Step 1. Context Understanding
Capture and restate:
- Feature/module scope
- Business goal and success criteria
- Actor/role map
- Related entities and existing workflows
- Cross-module dependencies

Output checklist:
- Problem statement is explicit
- In-scope vs out-of-scope is explicit
- Assumptions are listed

### Step 2. Database Design and Integrity
Analyze and design before coding:
- Entities, cardinality, lifecycle
- Normalization and redundancy trade-offs
- Foreign keys, constraints, and delete behavior
- UUID strategy for external/public identifiers
- Multi-tenant boundaries (`company_id` and ownership checks)
- Index plan for hot queries and reports

Decision points:
- If schema conflicts with existing runtime contract: propose migration path
- If denormalization is needed: justify with query profile and consistency guard
- If tenant safety is unclear: block implementation until isolation rule is explicit

Output checklist:
- Tables and key fields documented
- Relationship map documented
- FK and index strategy documented
- Migration impact/risk documented

### Step 3. API Design Standardization
Define API contract clearly:
- RESTful resource naming
- Correct method/status code mapping
- Request/response envelope consistency
- Validation rules by field
- Pagination/filter/sort for list endpoints
- Role/permission-aware responses

Decision points:
- If request payload can create ambiguity: split endpoint or enforce strict schema
- If endpoint leaks tenant-agnostic data: redesign query boundary
- If contract change is required: document compatibility and migration strategy

Output checklist:
- Endpoint table (method + path + purpose)
- Request and response examples
- Validation and error model
- Permission mapping per endpoint

### Step 4. Business Logic Flow
Describe deterministic backend flow:
1. Input validation
2. Authorization and tenant boundary check
3. Data load and consistency lock (if needed)
4. Domain logic execution
5. Persistence and side-effects (events/jobs)
6. Response shaping and audit trail

Edge-case requirements:
- Empty/missing optional data
- Duplicate actions/idempotency
- Concurrent updates/race conditions
- Partial failures/retry behavior
- Rollback strategy for transactional paths

Output checklist:
- Happy path and negative path documented
- Transaction boundaries explicit
- Retry/idempotency strategy explicit

### Step 5. Security and Permission Enforcement
Mandatory checks:
- RBAC at server side (never UI-only)
- Input sanitization and strict validation
- Tenant isolation at query and object level
- Protection against common vulnerabilities (SQL injection, mass assignment, broken access control)

Decision points:
- If one endpoint cannot prove tenant safety: treat as blocker
- If permission scope is too broad: reduce to least privilege

Output checklist:
- Permission matrix verified
- Unauthorized path returns expected error
- Cross-tenant access test scenario defined

### Step 6. Self-Validation (Mandatory)
Before finalizing, verify:
- Query performance (N+1, missing index, expensive joins)
- Business logic correctness vs requirements
- Consistency with existing schema and migrations
- API contract alignment with consumer needs
- Edge-case and failure scenario handling

Quality gates:
- No unresolved critical anomaly
- No schema/API contradiction
- No unguarded privileged action
- No unbounded query on expected high-volume path

### Step 7. Optimization and Scalability
Proactively evaluate:
- Query optimization opportunities
- Caching opportunities and cache invalidation policy
- Background job suitability and queue pressure risk
- Throughput bottlenecks under scale
- Observability needs (metrics, logs, alerts)

Output checklist:
- Hot-path optimization list
- Capacity/scaling notes
- Monitoring recommendations

### Step 8. Final Output Structure
For each backend task, return sections in this order:
1. Context Understanding
2. Database Design
3. API Design
4. Business Logic Flow
5. Anomaly Detection
6. Optimization Suggestions

## Anomaly Detection Rules
Always report anomalies with:
- Root cause
- Impact (business + technical)
- Severity
- Proposed fix
- Validation steps

Common anomaly classes:
- Schema integrity gaps (missing FK/constraint)
- Performance anti-patterns (N+1, unindexed predicates)
- Authorization leaks (missing permission check)
- Tenant isolation risks
- Contract mismatch between API and runtime behavior

## Strict Rules
- Do not ship tightly coupled logic
- Do not ignore existing schema/runtime contracts
- Do not assume frontend can compensate backend flaws
- Do not skip validation or error handling
- Do not add redundant tables/fields without explicit justification

## MCP and Real-State Validation
If MCP data tools are available:
- Inspect real schema and relationships
- Validate assumptions with live queries
- Confirm anomaly claims using actual data
- Prefer evidence over assumptions

If MCP is unavailable:
- State assumptions explicitly
- Use repository schema/contracts as source of truth

## Completion Criteria
A backend task is complete only when:
1. Design is coherent across schema, API, and business flow
2. Security and tenant isolation are validated
3. Critical anomalies are addressed or explicitly blocked
4. Performance risks are identified with mitigation
5. Implementation plan is testable and maintainable
6. Repository governance requirements are satisfied (security, docs sync, API sync, local-test-gate)

## Example Prompts
- `/backend-engineer-pro Design payroll tax reconciliation module for monthly close with multi-tenant safety.`
- `/backend-engineer-pro Review and fix anomalies in employee tax profile schema and API contracts.`
- `/backend-engineer-pro Propose scalable API and DB design for attendance anomaly dashboard.`
- `/backend-engineer-pro Validate RBAC and tenant isolation for HCM billing tax reporting endpoints.`
