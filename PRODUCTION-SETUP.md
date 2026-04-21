# Production Setup Checklist for arkav.puree.id

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
```

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

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Set up .env with production values
cp backend/env.txt backend/.env
# (edit .env with production settings)

# 3. Siapkan persistent storage di host
mkdir -p /data/code/storage

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
docker exec arkav-hcm php artisan config:cache
docker exec arkav-hcm php artisan route:cache
docker exec arkav-hcm php artisan view:cache
docker exec arkav-hcm php artisan migrate --force

# 6. Check logs
docker logs -f arkav-hcm
```

### Important Deploy Notes

- Jangan jalankan `php artisan key:generate` pada setiap deploy staging/production. `APP_KEY` harus stabil di file `.env` yang persistent; mengganti key tiap deploy akan menginvalidasi session/login yang sedang aktif dan bisa merusak data terenkripsi.
- Data yang disimpan di filesystem container akan hilang saat container di-`rm -f` dan dibuat ulang. Karena workflow auto deploy memang recreate container, direktori `backend/storage` wajib dimount ke host atau persistent volume.
- Database staging/production harus tetap memakai MySQL eksternal/persisten. Jika suatu saat container dijalankan tanpa `.env` yang benar atau tanpa DB persisten, gejalanya akan terlihat seperti "data hilang setelah push".
