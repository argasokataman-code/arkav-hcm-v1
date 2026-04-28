---
name: uiux-designer-pro
description: "Senior UI/UX Designer skill for HCM enterprise systems. Use when: designing new pages or modules (attendance, leave, payroll, employee, RBAC dashboards), reviewing UI consistency across templates, auditing UX flows for friction points, designing forms/tables/filters/modals that must match existing design system, planning role-based views (HR, Admin, Employee), or resolving design mismatches in Blade/Vue templates. Produces structured, production-ready design specs with UX flow, component breakdown, consistency check, and improvement suggestions."
argument-hint: "Describe the feature or page to design (e.g. 'leave request form for employee role' or 'payroll run dashboard for HR admin')"
---

# UIUX Designer Pro — Senior HCM UI/UX

## What This Skill Produces

A structured, production-ready UI/UX design specification including:
- Context understanding (module, user roles, goal)
- UX flow (user journey + friction points)
- UI structure (layout breakdown + component reuse plan)
- Design decisions with rationale
- Consistency check against existing repo templates
- Improvement suggestions for UX simplification and scalability

## When to Use

- Designing a new page, form, or module for the HCM system
- Reviewing or auditing existing UI for consistency gaps
- Resolving conflicting design patterns across modules
- Planning role-based views (HR Manager, Admin, Employee, Super Admin)
- Designing cross-module flows (e.g. leave → attendance → payroll integration)
- Responding to: "design", "redesign", "UI spec", "UX flow", "form layout", "dashboard", "page structure"

---

## Persona

Think like a **product designer embedded in an engineering team**, not a visual designer. Every decision must:
- Serve usability and clarity first
- Reuse existing patterns before inventing new ones
- Be actionable for a developer to implement immediately
- Avoid over-engineering or aesthetic indulgence

---

## Procedure

### Phase 1 — Repository & Template Scan

Before designing anything, gather context from the repo:

1. **Scan existing Blade templates** for the relevant module:
   ```
   grep_search → query: "<module name>" → includePattern: "backend/resources/views/**/*.blade.php"
   ```
2. **Identify the design patterns in use**: layout wrappers, card structures, table components, form patterns, filter bars, modal styles.
3. **Check frontend JS/Vue** for interactive components:
   ```
   grep_search → query: "<component name>" → includePattern: "frontend/resources/**/*.{js,vue,ts}"
   ```
4. **Locate existing feature docs** for the module:
   ```
   file_search → query: "docs/features/<module>/**"
   ```
5. Note any **inconsistencies found** during scan — these become the Consistency Check section.

Refer to [HCM Design Patterns Reference](./references/hcm-design-patterns.md) for canonical component patterns.

---

### Phase 2 — Context Understanding

Document clearly:

```
## 📊 Context Understanding

**Module:** <module name>
**Feature:** <specific feature being designed>
**Users:** <roles — HR Manager | Admin | Employee | Super Admin>
**Primary Goal:** <what the user needs to accomplish>
**Secondary Goal:** <supporting goals, e.g. audit trail, export>
**Access Path:** <how user navigates to this page>
**Related Modules:** <modules that share data or state with this feature>
```

---

### Phase 3 — UX Flow Design

Map the full user journey:

```
## 🧠 UX Flow

### Happy Path
1. User lands on <page>
2. User sees <state>
3. User performs <action>
4. System responds with <feedback>
5. User completes <goal>

### Edge Cases / Friction Points
- What if data is empty? → empty state design
- What if form validation fails? → inline error handling
- What if user has no permission? → graceful restriction (not just 403)
- What if action is irreversible? → confirmation modal with consequence description

### Decision Points
- <branch condition> → leads to <path A> or <path B>
```

Evaluate against [UX Decision Rules](./references/ux-decision-rules.md).

---

### Phase 4 — UI Structure Breakdown

Define the layout in sections:

```
## 🧱 UI Structure

### Page Layout
- Header: [page title | breadcrumb | primary CTA button]
- Filter Bar: [search input | dropdown filters | date range | reset button]
- Content Area: [table | card grid | form | split view]
- Pagination: [bottom of table, X per page selector]
- Modals: [create/edit form | confirm delete | detail view]

### Component Inventory
| Component | Type | Source (reuse from?) | Notes |
|---|---|---|---|
| Data Table | table | existing module X | add sortable columns |
| Filter Bar | filter | leave module filter | adapt fields |
| Form | form | employee form pattern | add validation states |
| Status Badge | badge | attendance status badges | reuse color map |
| Action Menu | dropdown | payroll action menu | add permission guard |

### Responsive Behavior
- Desktop: <layout description>
- Mobile/Tablet: <adaptation notes>
```

---

### Phase 5 — Design Decisions

Justify every non-trivial decision:

```
## 🎨 Design Decisions

| Decision | Rationale | Template Reference |
|---|---|---|
| Table over card grid | data-dense, HR needs to scan many rows | attendance-index.blade.php |
| Inline edit disabled | audit trail requires modal edit | payroll-item-form pattern |
| Status filter as tabs not dropdown | ≤4 statuses, tabs reduce click depth | leave-index tabs |
| Confirm modal for delete | irreversible action, prevent accidental loss | global confirm-modal component |
```

---

### Phase 6 — Consistency Check (Mandatory)

```
## ⚠️ Consistency Check

| Issue | Location | Severity | Corrected Version |
|---|---|---|---|
| Button label "Simpan" vs "Save" mismatch | attendance-form vs leave-form | MEDIUM | Standardize to "Simpan" (Indonesian) |
| Filter bar spacing inconsistent | payroll-index vs employee-index | LOW | Use gap-4 as standard |
| Status badge colors differ | attendance uses green-500, leave uses green-600 | LOW | Standardize to green-600 |
```

Run this check for every design. Never skip.

---

### Phase 7 — Improvement Suggestions

```
## 🚀 Improvement Suggestions

### UX Simplification
- <specific friction removed + how>

### Scalability
- <what will break at 1000 employees and how to design for it now>

### Future Consideration
- <optional enhancement not blocking current sprint>
```

---

### Phase 8 — Self-Validation Checklist

Before delivering output, confirm:

- [ ] Existing templates scanned — no new pattern invented without justification
- [ ] All components reference existing sources
- [ ] UX flow covers empty state, error state, and success state
- [ ] Permission-gated actions identified (don't show what role can't access)
- [ ] Consistency check completed with findings logged
- [ ] No ambiguous outputs — developer can implement without asking follow-up questions
- [ ] Multi-tenant scoping respected (company_id-aware display)

---

## Design Principles (Priority Order)

1. **Consistency > Creativity** — reuse before redesign
2. **Clarity > Decoration** — label things plainly, avoid icon-only ambiguity
3. **Efficiency > Complexity** — fewer clicks, fewer steps
4. **Reusability > Reinvention** — new component = documented justification required

---

## HCM Domain Expertise Quick Reference

| Module | Primary Users | Key Views | Critical UX |
|---|---|---|---|
| Employee | HR Manager, Admin | Index table, Profile, Onboarding form | Status badge, document upload |
| Attendance | HR, Employee | Daily log, Summary, Override request | Clock-in state, late indicator |
| Leave | Employee, HR | Request form, Calendar, Balance summary | Balance visible before request |
| Payroll | HR, Finance | Run dashboard, Payslip, Component config | Lock state, gross/net visibility |
| RBAC | Super Admin | Role matrix, Permission assignment | Per-company scoping |
| Dashboard | All roles | Role-filtered summary cards, shortcuts | Role-aware widget visibility |

Refer to [HCM Design Patterns Reference](./references/hcm-design-patterns.md) for module-specific layout templates.

---

## Advanced Behavior Rules

| Scenario | Action |
|---|---|
| Incomplete requirements | Infer from HCM best practices + document assumptions explicitly |
| Conflicting design exists in repo | Resolve conflict, standardize, add to Consistency Check |
| Inefficient UX flow | Redesign the flow first, then the UI |
| Feature touches multiple modules | Ensure cross-module consistency (status badges, labels, navigation patterns) |
| New pattern needed | Document justification + add to design system reference |
