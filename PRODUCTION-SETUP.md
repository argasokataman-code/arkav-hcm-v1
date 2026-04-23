# Production Setup Checklist for arkav.puree.id

For non-container shared hosting setup (direct Laravel web root, no Node proxy, local artifact build), use:
- `docs/engineering/shared-hosting-setup.md`

## 🔧 Environment Configuration

Before deploying to production at `https://arkav.puree.id/`, ensure these settings are in place:

### Backend `.env` file (at `/data/code/.env`)

```bash
# Domain & Environment
APP_NAME="Arcav HCM"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://arkav.puree.id

# Database (update with your production DB)
DB_CONNECTION=mysql
DB_HOST=your-db-host.com
DB_PORT=3306
DB_DATABASE=arcav_production
DB_USERNAME=arcav_user
DB_PASSWORD=your-secure-password

# Cache (use Redis for production)
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Session
SESSION_DRIVER=database
SESSION_DOMAIN=arkav.puree.id

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Mail (if needed)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@arkav.puree.id
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@arkav.puree.id

# Additional configs
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
# Keep dev bootstrap disabled in production startup
RUN_DEV_BOOTSTRAP=false

# HCM payroll feature flags (canonical source: backend/env.txt +
# docs/features/payroll-runs/README.md). Defaults below match config/hcm.php.
# HCM_PAYROLL_LEAVE_INTEGRATION=false
# HCM_PAYROLL_HOLIDAY_WORK_MULTIPLIER=2.0
```

#### HCM feature flags

| Flag | Default | Meaning |
| --- | --- | --- |
| `HCM_PAYROLL_LEAVE_INTEGRATION` | `false` | Turn on the leave + holiday payroll adjuster pipeline. When enabled, unpaid leave becomes a payroll deduction and holiday work becomes an addition. |
| `HCM_PAYROLL_HOLIDAY_WORK_MULTIPLIER` | `2.0` | Multiplier applied to the daily rate when an attendance record falls on an official holiday. |

The canonical reference lives in [`backend/env.txt`](backend/env.txt) and [`docs/features/payroll-runs/README.md`](docs/features/payroll-runs/README.md). Keep those three (env.txt, PRODUCTION-SETUP.md, feature README) in sync when adding new HCM flags.


### Docker Deployment

The application runs on:
- **Frontend Proxy:** `http://0.0.0.0:5179` → External access (redirects to backend)
- **Backend API:** `http://0.0.0.0:8007` → Internal only

### Nginx Reverse Proxy Setup

Configure your Nginx to forward requests to the Node proxy:

```nginx
server {
    listen 443 ssl http2;
    server_name arkav.puree.id;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Forward to frontend proxy
    location / {
        proxy_pass http://127.0.0.1:5179;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name arkav.puree.id;
    return 301 https://$server_name$request_uri;
}
```

### Health Checks

After deployment, verify:

```bash
# Check backend health
curl -i https://arkav.puree.id/health

# Check if frontend is accessible
curl -i https://arkav.puree.id/

# Check API endpoint
curl -i https://arkav.puree.id/v1/identity/auth/me
```

### Common Issues

| Issue | Solution |
|-------|----------|
| 502 Bad Gateway | Backend not running, check Docker logs |
| Connection refused | Frontend proxy not listening on 0.0.0.0 |
| Database errors | Check DB_HOST/credentials in .env |
| CORS errors | Verify APP_URL matches domain |
| Mixed content warnings | Ensure APP_URL uses https:// |

### 502 Recovery Checklist (Cloudflare)

Jalankan dari server VPS setelah deploy:

```bash
# 1) Pastikan container hidup
docker ps --format '{{.Names}}' | grep '^arkav-hcm$'

# 2) Lihat startup error terakhir
docker logs --tail 300 arkav-hcm

# 3) Cek health backend + frontend proxy internal
curl -i http://127.0.0.1:8007/health
curl -i http://127.0.0.1:5179/

# 4) Pastikan Nginx upstream ke Node proxy
sudo nginx -t
sudo systemctl reload nginx
```

Jika langkah 3 gagal, fokus ke log container dulu (biasanya APP_ENV/.env, DB connect, atau permission storage/cache). Jika langkah 3 sukses tapi domain masih 502, fokus ke Nginx/Cloudflare routing.

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Set up .env with production values
cp backend/env.txt backend/.env
# (edit .env with production settings)

# 3. Siapkan persistent storage di host
mkdir -p /data/code/storage/logs
mkdir -p /data/code/storage/framework/cache/data
mkdir -p /data/code/storage/framework/sessions
mkdir -p /data/code/storage/framework/views
mkdir -p /data/code/storage/app/public
mkdir -p /data/code/storage/app/private

# 4. Build and run Docker image
docker build -t arkav-hcm .
docker run -d --name arkav-hcm \
  --restart always \
  -p 8007:8007 \
  -p 5179:5179 \
  -v /data/code/.env:/app/backend/.env \
    -v /data/code/storage:/app/backend/storage \
  arkav-hcm

# 5. Run caches + migrations
docker exec arkav-hcm bash -c "cd /app/backend && mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public storage/app/private bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache && php artisan config:clear && php artisan view:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force"

# 6. Check logs
docker logs -f arkav-hcm
```

### Important Deploy Notes

- Jangan jalankan `php artisan key:generate` pada setiap deploy staging/production. `APP_KEY` harus stabil di file `.env` yang persistent; mengganti key tiap deploy akan menginvalidasi session/login yang sedang aktif dan bisa merusak data terenkripsi.
- `run.sh` akan skip seeder development otomatis saat `APP_ENV=production` (atau saat `RUN_DEV_BOOTSTRAP` bukan `true`). Ini mencegah startup gagal karena validasi akun dev yang memang tidak relevan untuk production.
- Data yang disimpan di filesystem container akan hilang saat container di-`rm -f` dan dibuat ulang. Karena workflow auto deploy memang recreate container, direktori `backend/storage` wajib dimount ke host atau persistent volume.
- Karena host mount akan menimpa isi `storage` bawaan image, subdirektori Laravel seperti `storage/framework/views`, `storage/framework/sessions`, `storage/framework/cache/data`, `storage/logs`, `storage/app/public`, dan `storage/app/private` harus dibuat ulang sebelum menjalankan `php artisan config:cache` atau `php artisan view:cache`.
- Database staging/production harus tetap memakai MySQL eksternal/persisten. Jika suatu saat container dijalankan tanpa `.env` yang benar atau tanpa DB persisten, gejalanya akan terlihat seperti "data hilang setelah push".
