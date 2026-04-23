# Shared Hosting Setup (No Node Proxy)

This guide is for standard hosting where you can run PHP + MySQL but cannot keep a permanent Node/queue daemon alive.
This mode uses local build artifact deployment: Composer and npm run on local machine, not on hosting server.

## Target Architecture

- Web server serves Laravel directly from `backend/public`.
- API and web pages run from the same Laravel app/domain.
- Frontend assets are built once at deploy time using Vite and served statically from `backend/public/build`.
- Queue processing uses cron fallback (no long-running supervisor worker required).

## Minimum Requirements

- PHP 8.2+
- MySQL
- Local machine: Composer + Node.js + npm (for artifact build)
- Hosting server: no Composer/npm required
- Cron access (at least every minute)
- Write permission for `backend/storage` and `backend/bootstrap/cache`

## 1) Web Root / Document Root

Point your domain/subdomain web root to:

- `.../arcav_new_v2/backend/public`

Do not point web root to repository root.

## 2) Environment Configuration

Create `backend/.env` from `backend/env.txt` and set at minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=database

# Keep disabled in production startup
RUN_DEV_BOOTSTRAP=false
```

## 3) Build Artifact Locally (with Composer + npm)

From repository root on your local machine:

```bash
bash scripts/shared-hosting-package-local.sh
```

Output artifact:

- `release/shared-hosting/shared-hosting-artifact-<timestamp>.tar.gz`

What this local packaging script does:

- validates PHP version (8.2+)
- installs Composer dependencies
- installs npm dependencies and runs `npm run build`
- prepares production-ready backend with `vendor` and `public/build`
- creates deploy artifact for upload

## 4) Upload Artifact + Deploy on Hosting Server

On hosting server (without Composer/npm):

```bash
# Example
cd /path/to/app
tar -xzf shared-hosting-artifact-<timestamp>.tar.gz
bash scripts/shared-hosting-deploy.sh
```

Alternative (same behavior, easier to read and debug):

```bash
bash scripts/shared-hosting-deploy-easy.sh
```

Server deploy script does:

- validates PHP version (8.2+)
- verifies `backend/vendor` and `backend/public/build/manifest.json` exist
- prepares `storage/*` and `bootstrap/cache`
- runs `php artisan migrate --force`
- refreshes Laravel caches
- ensures `public/storage` symlink

## 5) Cron Setup (Scheduler + Queue Fallback)

Add two cron entries:

```cron
* * * * * cd /path/to/arcav_new_v2/backend && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /path/to/arcav_new_v2 && bash scripts/shared-hosting-queue-cron.sh >> /dev/null 2>&1
```

Notes:

- first cron runs Laravel scheduler tasks.
- second cron runs queue worker in short burst (`--stop-when-empty`) so it fits shared hosting limits.
- lock file is used when `flock` is available to reduce overlap risk.

## 6) File Permissions

Ensure writable paths:

- `backend/storage/logs`
- `backend/storage/framework/cache/data`
- `backend/storage/framework/sessions`
- `backend/storage/framework/views`
- `backend/storage/app/public`
- `backend/storage/app/private`
- `backend/bootstrap/cache`

## 7) Validation Checklist

Run after deploy:

```bash
cd backend
php artisan about
php artisan migrate:status
php artisan route:list --path=v1 --compact
```

Open in browser:

- `https://your-domain.example/`
- `https://your-domain.example/health`
- `https://your-domain.example/api-docs`

## Troubleshooting

- `500` after deploy: verify `backend/.env` and write permissions.
- CSS/JS missing: rerun local artifact build `bash scripts/shared-hosting-package-local.sh`, upload artifact again, then rerun `bash scripts/shared-hosting-deploy.sh` on server.
- queued jobs not processed: verify second cron entry and `QUEUE_CONNECTION=database`.
- scheduler features not firing: verify first cron entry and server timezone.