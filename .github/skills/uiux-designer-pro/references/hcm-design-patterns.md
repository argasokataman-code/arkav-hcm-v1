# HCM Design Patterns Reference

## Layout Conventions

### Standard Index/List Page
```
[Page Header]
  ├── Page Title (h1)
  ├── Breadcrumb (secondary nav)
  └── Primary CTA Button (top-right, e.g. "+ Tambah")

[Filter Bar]
  ├── Search Input (leftmost, placeholder: "Cari...")
  ├── Dropdown Filters (status, department, date range)
  ├── Date Range Picker (if applicable)
  └── Reset Filter Link (rightmost)

[Data Table]
  ├── Column headers (sortable where applicable)
  ├── Row actions: [Detail] [Edit] [Hapus] — in action menu for 3+
  ├── Status badge column (always uses color-coded badge)
  └── Empty state: illustration + label + CTA if applicable

[Pagination]
  └── Items per page selector + page nav (bottom-right)
```

### Standard Form Page / Modal
```
[Form Header]
  ├── Title ("Tambah X" or "Edit X")
  └── Close button (if modal)

[Form Body]
  ├── Section grouping (if > 6 fields)
  ├── Label above input (not inline/floating)
  ├── Required field marker: asterisk (*) in label
  ├── Helper text: below input, muted color
  └── Validation error: below input, red text, red border on input

[Form Footer]
  ├── [Batal] button — secondary/outline
  └── [Simpan] button — primary, rightmost
```

### Standard Detail/Profile Page
```
[Profile Header Card]
  ├── Avatar / initials
  ├── Name + primary identifier
  └── Status badge

[Tab Navigation]
  └── Tabs for: Informasi Umum | Dokumen | Riwayat | dll.

[Content Sections]
  └── Cards per section (not flat list)
```

---

## Status Badge Color Map

| Status | Color Class | Usage |
|---|---|---|
| Active / Hadir | green-600 | Employee active, attendance present |
| Pending | yellow-500 | Leave pending, payroll draft |
| Approved / Selesai | blue-600 | Leave approved, payroll completed |
| Rejected / Ditolak | red-600 | Leave rejected |
| Inactive / Non-aktif | gray-400 | Employee inactive |
| Locked | purple-600 | Payroll run locked |
| On Leave | orange-500 | Attendance on_leave |
| Late / Terlambat | amber-500 | Attendance late |

**Rule:** Use consistent color across modules. Never invent new badge colors without updating this table.

---

## Typography Scale

| Role | Class | Usage |
|---|---|---|
| Page Title | text-2xl font-semibold | H1 per page |
| Section Title | text-lg font-medium | Card headers, section labels |
| Table Header | text-sm font-semibold uppercase | Column headers |
| Body / Label | text-sm | Form labels, table cells |
| Helper / Muted | text-xs text-gray-500 | Helper text, metadata |
| Status Badge | text-xs font-medium | Status labels |

---

## Spacing Conventions

| Context | Class |
|---|---|
| Page padding | p-6 (desktop), p-4 (mobile) |
| Card padding | p-4 or p-6 |
| Form field gap | gap-4 (vertical), gap-6 (section gap) |
| Filter bar gap | gap-3 or gap-4 |
| Table row padding | py-3 px-4 |
| Button padding | px-4 py-2 (default), px-3 py-1.5 (small) |

---

## Button Hierarchy

| Type | Class Pattern | When to Use |
|---|---|---|
| Primary | bg-primary text-white | Main CTA (Save, Submit, Create) |
| Secondary | border border-gray-300 text-gray-700 | Cancel, secondary actions |
| Danger | bg-red-600 text-white | Destructive actions (after confirm modal) |
| Ghost/Link | text-primary underline | Inline actions, navigation links |
| Icon Only | p-2 rounded | Table row quick actions (use tooltip) |

**Rule:** Never use Danger button without a confirmation modal step.

---

## Modal Patterns

### Create/Edit Modal
- Max width: `max-w-2xl` (form modals), `max-w-lg` (simple confirm)
- Always trap focus within modal
- Close on ESC + backdrop click (unless form has unsaved changes)
- Show unsaved changes warning if user tries to close dirty form

### Confirm/Destructive Modal
```
[Title] "Hapus <item>?"
[Body]  "Tindakan ini tidak dapat dibatalkan. Data <item> akan dihapus permanen."
[Footer] [Batal] [Hapus] (Danger button)
```

### Detail/Preview Modal
- Read-only, no form inputs
- Close button prominent (top-right X)
- Optional: "Edit" button at bottom leading to edit modal

---

## Table Patterns

### Standard Data Table
- Sticky header on scroll
- Zebra rows or hover highlight (pick one, be consistent)
- Action column: always rightmost
- For 3+ actions: use dropdown action menu, not inline buttons
- Sortable columns: show sort icon on hover, active sort highlighted
- Bulk actions: checkbox column (leftmost) + bulk action bar appears when selected

### Empty State
```
[Illustration or Icon]
[Label] "Belum ada data <X>"
[Sub-label] "Mulai dengan menambahkan <X> baru"
[CTA Button] "+ Tambah <X>" (only if user has permission)
```

---

## Form Patterns

### Field Types by Data
| Data Type | Component |
|---|---|
| Short text | `<input type="text">` |
| Long text | `<textarea rows="3">` |
| Number | `<input type="number">` with min/max |
| Date | Date picker component |
| Date range | Dual date picker |
| Select (few options, ≤6) | Radio group or tab switcher |
| Select (many options) | Searchable select/combobox |
| Multi-select | Tag input or checkbox list |
| File upload | Drag-drop zone + file list |
| Toggle | Switch component (not checkbox for settings) |

### Validation States
- **Pristine**: default border
- **Valid**: no indicator needed (avoid green checkmarks — adds noise)
- **Invalid**: red border + red helper text below
- **Loading**: input disabled, spinner in submit button

---

## Permission-Aware UI Rules

| Condition | UI Behavior |
|---|---|
| User lacks create permission | Hide "+ Tambah" button entirely |
| User lacks edit permission | Show row without Edit action |
| User lacks delete permission | Hide Delete from action menu |
| User lacks view permission | Redirect with 403 page, not empty page |
| Action needs approval | Show "Ajukan" not "Simpan" on button |

**Rule:** Never show a button that will return 403 on click. Guard at template level, not just API level.

---

## Navigation Patterns

### Sidebar (main nav)
- Group by module, not by role
- Active state: highlighted background + left border accent
- Sub-items: collapsed by default, expand on parent click

### Breadcrumb
- Always present on non-root pages
- Format: `Dashboard > Module > Sub-page`
- Last item: current page (non-clickable)

### Tab Navigation (within page)
- Use for same-entity different views (not for navigation between pages)
- Active tab: border-bottom accent, not background fill
- Max 5 tabs visible; overflow → dropdown

---

## Responsive Breakpoints

| Breakpoint | Target | Layout Adjustment |
|---|---|---|
| `sm` (640px) | Large phone | Stack filter bar, hide secondary columns |
| `md` (768px) | Tablet | Sidebar collapses to hamburger |
| `lg` (1024px) | Laptop | Full layout, sidebar visible |
| `xl` (1280px) | Desktop | Comfortable padding, wider tables |
