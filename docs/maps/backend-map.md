# Backend Map

## 1. Core Structure

- **App Directory:** `backend/app/`
  - Controllers: `backend/app/Http/Controllers/` (API & Web)
  - Models: `backend/app/Models/`
  - Services: `backend/app/Services/`
  - Repositories: (Implicit, often within Services or Models)
  - Providers: `backend/app/Providers/`
  - Listeners/Events: `backend/app/Listeners/`, `backend/app/Events/`
  - Jobs: `backend/app/Jobs/`
  - Console Commands: `backend/app/Console/Commands/`

## 2. Routing

- API Routes: `backend/routes/api.php` and sub-folders like `backend/app/Http/Controllers/Api/*`
- Web Routes: `backend/routes/web.php` and `backend/routes/web/*` (if exists)

## 3. Database

- Migrations: `backend/database/migrations/`
- Seeders: `backend/database/seeders/`
- Factories: `backend/database/factories/`
- Models contain ORM definitions.

## 4. Configuration

- Main Config: `backend/config/`
- Environment Variables: `.env` (not committed), `env.txt` (example)

## 5. Business Logic & Features

- See `AGENTS.md` for the 6-phase process.
- Specific feature logic is often spread across Controllers, Services, Models, and Jobs.

## 6. Scripts & Testing

- Backend Scripts: `scripts/`
- E2E Tests: `backend/e2e/`
- Unit/Feature Tests: `backend/tests/`
- Playwright Tests: `backend/playwright.config.js`, `backend/e2e/`

## 7. Important Files Mentioned in AGENTS.md:

- `backend/routes/api.php`
- `backend/routes/web.php`
- `backend/database/migrations/`
