# Views Maintenance Guide

## Folder Strategy
- Keep business views in domain folders:
  - `hcm/`, `saas/`, `crm/`, `applications/`, `administration/`, `performance/`, `reports/`, `finance/`
- Keep UI kit/demo templates in `ui-kit/`:
  - `layouts/`, `ui/`, `forms/`, `icons/`, `charts/`, `maps/`, `tables/`
- Keep reusable partials in `partials/` and `layout/partials/`.

## Root (`resources/views`) Rules
- Do not add new heavy page templates in root.
- Root files should be thin wrappers only when backward compatibility for existing `view('...')` names is required.
- Prefer creating new files directly in domain folders.

## Naming
- Use kebab-case Blade filenames.
- Composer files (include aggregators) should stay small and descriptive.

## Refactor Safety
- For moving existing views: move original file into target folder, then leave root wrapper:
  - `@include('new.path.to.view')`
- Validate with:
  - `php artisan view:cache`
  - targeted feature/UI smoke tests
