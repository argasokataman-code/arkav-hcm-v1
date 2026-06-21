# Frontend Map

## 1. Core Structure

- **Resources:** `frontend/resources/`
  - Views (Blade): `frontend/resources/views/`
  - Assets (JS, CSS): `frontend/resources/assets/` (might be processed by Vite/Build tools)

## 2. Build & Development

- Vite Configuration: `vite.config.js`
- TypeScript Config: `tsconfig.json`
- Vitest Config: `vitest.config.js`

## 3. Entry Points & Routing (Frontend)

- Main HTML: `backend/landing-source/index.html` (for landing page)
- Vite Entry Points: Likely defined in `vite.config.js` and `frontend/resources/` (e.g., `main.js`)
- Frontend Routing: Handled by a JS framework (e.g., Vue Router, React Router) - check `frontend/resources/js/` or related configuration files.

## 4. Key Directories (Inferred from `ls -R`)

- `frontend/resources/js/`: Likely contains core application logic, components, routing setup.
- `frontend/resources/views/`: Blade templates, potentially acting as entry points or including JS-rendered sections.

## 5. Important Files Mentioned in AGENTS.md:

- `vite.config.js`
