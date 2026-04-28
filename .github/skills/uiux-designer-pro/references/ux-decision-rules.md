# UX Decision Rules

## Decision Framework: When to Use Which Pattern

---

## Navigation Decisions

| Situation | Decision | Reason |
|---|---|---|
| ≤ 4 filter options | Use Tab switcher above table | Reduces click depth, always visible |
| 5+ filter options | Use Dropdown select | Prevents tab overflow |
| 2 toggle states | Use Switch / Toggle | Clearer than radio for binary |
| ≤ 6 static choices | Use Radio group | All options visible, no hidden state |
| 7+ choices or dynamic | Use Searchable select | Scalable |

---

## Form vs Modal vs Page Decision

| Condition | Use |
|---|---|
| Form has ≤ 8 fields | Modal form |
| Form has 9+ fields OR multi-section | Full page form |
| Action is destructive (delete/lock) | Confirmation modal (separate from form) |
| Action needs file upload | Full page (modals + file upload = UX friction) |
| Read-only detail view | Detail modal or detail page (based on complexity) |

---

## Empty State Decision Tree

```
Is the empty state due to:
  ├── No data yet (first time) → Show CTA to create first item
  ├── Filter returned no results → Show "Tidak ada hasil untuk filter ini" + reset filter
  ├── User has no permission to see data → Show restriction message (not empty)
  └── Data loading failed → Show error state + retry button
```

---

## Confirmation Modal Rules

Require confirmation modal when:
- Action deletes data
- Action is irreversible (lock payroll run, finalize report)
- Action affects other users (cancelling approved leave)
- Action triggers bulk changes (batch update status)

**Do NOT** show confirmation for:
- Save/submit form (let validation handle errors)
- Navigation away (warn only if form is dirty)
- Filtering/sorting (instant, reversible)

---

## Table vs Card Grid Decision

| Data Characteristics | Use |
|---|---|
| Many columns, data-dense | Table |
| Visual identity matters (photo, avatar) | Card grid |
| Comparison across many attributes | Table |
| Portfolio / gallery view | Card grid |
| Audit/log data | Table (no cards) |
| Dashboard summary items | Cards |

---

## Role-Based UI Decision

Always determine what each role CAN and CANNOT do before designing:

| Role | Typical Capabilities | Key UI Difference |
|---|---|---|
| Super Admin | Full system + all companies | Company switcher visible |
| Company Admin | Full access within company | No company switcher |
| HR Manager | Manage employees, approve flows | Bulk actions, approval queues |
| Employee | Self-service only | No management views |
| Finance | Payroll view + export | Read-heavy, export-focused |

**Rule:** Design for the most restrictive role first, then add capability layers. Do NOT design for admin and then try to hide things for employees.

---

## Feedback & State Rules

| Action | Feedback Type |
|---|---|
| Form submit (async) | Disable button + show spinner in button |
| Success | Toast notification (top-right, auto-dismiss 3s) |
| Non-critical error | Toast notification (red) |
| Critical error (data loss risk) | Inline modal, not dismissible toast |
| Long running operation | Progress indicator or "sedang diproses" state |
| Optimistic update | Show change immediately, revert on error |

---

## Cross-Module Consistency Rules

When a feature appears in multiple modules (e.g. approval workflow exists in Leave AND Overtime):

1. Use the same modal structure
2. Use the same status badge colors
3. Use the same button labels ("Setujui" / "Tolak" — not "Approve" / "Reject" mixed)
4. If one module has a better pattern → migrate the other, don't create divergence

---

## Indonesian Language UI Rules

| Context | Use |
|---|---|
| Button primary action | "Simpan", "Tambah", "Ajukan", "Setujui", "Tolak", "Hapus" |
| Page title | Indonesian (e.g. "Manajemen Karyawan") |
| Status labels | Indonesian ("Aktif", "Nonaktif", "Menunggu", "Disetujui", "Ditolak") |
| Placeholder text | Indonesian ("Cari nama karyawan...", "Pilih departemen") |
| Error messages | Indonesian, friendly tone |
| Technical identifiers (API, code) | English (keep consistent with backend) |

**Rule:** Never mix English and Indonesian labels in the same UI section.

---

## Pagination Rules

| Record Count | Pagination Style |
|---|---|
| ≤ 20 rows | No pagination needed (show all) |
| 21–200 rows | Standard pagination (prev/next + page numbers) |
| 200+ rows | Pagination + per-page selector (10/25/50/100) |
| Infinite/stream data | Infinite scroll (e.g. notification feed) |
| Export all | Separate export action — never paginate exports |

---

## Search & Filter Rules

- Search should be **instant** (debounce 300ms) or **on Enter** — decide per module and be consistent
- Filters should **persist** in URL params (bookmarkable, shareable)
- "Reset Filter" always resets to default state (not empty — some filters may have defaults)
- Active filters should be visually indicated (badge count or highlighted state)
- Filter and search work **together** (AND logic unless stated otherwise)
