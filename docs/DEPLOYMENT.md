# SmartPOS Pro — Deployment Guide

This document covers deploying SmartPOS Pro to production.  
The stack: **Laravel 13 (PHP 8.4)** backend + **Next.js 16** frontend.

---

## Prerequisites

| Dependency | Min version |
|------------|-------------|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js | 22 LTS |
| MySQL / MariaDB | 8.0 / 10.11 |
| Redis | 7.x |
| nginx | 1.24+ |

---

## 1 · Docker Compose (recommended for development / staging)

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The `docker-compose.yml` in the repo starts: app (PHP-FPM), nginx, MySQL 8, Redis, Soketi (WebSocket).

---

## 2 · Backend (Laravel)

### 2.1 Install

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### 2.2 Environment variables (`.env`)

```dotenv
APP_ENV=production
APP_URL=https://api.yourdomain.com
APP_KEY=                # generated above

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartpos
DB_USERNAME=smartpos
DB_PASSWORD=<strong-password>

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# Sanctum: list all frontend origins
SANCTUM_STATEFUL_DOMAINS=pos.yourdomain.com

# Mail (Resend / SES / SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=resend
MAIL_PASSWORD=<api-key>
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Reverb (WebSocket)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=ws.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### 2.3 Migrate & seed

```bash
php artisan migrate --force
php artisan db:seed --class=PlanSeeder   # insert subscription plans

# Optional demo data (dev/staging only):
# php artisan db:seed

# One-off, after upgrading to per-tenant gapless invoice numbering: stamps a number
# on every completed sale that predates it and parks each tenant's counter above
# whatever its sales already carry. Existing numbers are never overwritten, and the
# command is safe to re-run. Preview with --dry-run, or scope with --tenant=<id>.
php artisan sales:backfill-invoice-numbers --dry-run
php artisan sales:backfill-invoice-numbers
```

### 2.4 Storage & cache

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.5 Queue workers (Supervisor)

```ini
[program:smartpos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/smartpos/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=4
user=www-data
stdout_logfile=/var/log/supervisor/smartpos-worker.log
```

### 2.6 Scheduler (cron)

```cron
* * * * * www-data cd /var/www/smartpos && php artisan schedule:run >> /dev/null 2>&1
```

### 2.7 nginx (API)

```nginx
server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;

    root /var/www/smartpos/public;
    index index.php;

    ssl_certificate     /etc/ssl/api.yourdomain.com.crt;
    ssl_certificate_key /etc/ssl/api.yourdomain.com.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Rate-limit auth endpoints
    location /api/v1/auth/ {
        limit_req zone=auth burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 3 · Frontend (Next.js)

### 3.1 Build

```bash
cd web
cp .env.local.example .env.local      # set NEXT_PUBLIC_API_URL
npm ci
npm run build
```

`.env.local`:
```dotenv
NEXT_PUBLIC_API_URL=https://api.yourdomain.com/api/v1
```

### 3.2 PM2 (standalone)

```bash
npm install -g pm2
pm2 start npm --name smartpos-web -- start
pm2 save
pm2 startup
```

### 3.3 nginx (frontend)

```nginx
server {
    listen 443 ssl http2;
    server_name pos.yourdomain.com;

    ssl_certificate     /etc/ssl/pos.yourdomain.com.crt;
    ssl_certificate_key /etc/ssl/pos.yourdomain.com.key;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # Cache static assets aggressively
    location /_next/static/ {
        proxy_pass http://127.0.0.1:3000;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
```

---

## 4 · Security hardening

### 4.1 HTTP security headers (nginx)

```nginx
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()";
add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload";
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self' wss://ws.yourdomain.com;";
```

### 4.2 Rate limiting (nginx)

```nginx
limit_req_zone $binary_remote_addr zone=auth:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
```

### 4.3 Database

- Create a dedicated MySQL user with minimal privileges (no `SUPER`, no `FILE`).
- Enable `binlog_format=ROW` for point-in-time recovery.
- Rotate backups daily with `mysqldump` or Percona XtraBackup.

### 4.4 Application

| Setting | Recommendation |
|---------|---------------|
| `APP_DEBUG` | `false` in production |
| `LOG_LEVEL` | `error` in production |
| Password hashing | Argon2id (default in PHP 8.4) |
| File uploads | Store on S3 via Laravel Filesystem |
| Sanctum token expiry | Set `SANCTUM_TOKEN_EXPIRATION=1440` (24 h) |

---

## 5 · Observability

### 5.1 Health endpoint

`GET /v1/health` returns database, Redis, and queue status. Requires authentication
with `dashboard.view` permission. Use for uptime monitoring (Betterstack, UptimeRobot,
etc.).

### 5.2 Sentry

**Backend (Laravel):**

1. Install: `composer require sentry/sentry-laravel`
2. Set the DSN in `.env`:
   ```dotenv
   SENTRY_LARAVEL_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
   SENTRY_ENVIRONMENT=production
   SENTRY_TRACES_SAMPLE_RATE=0.2
   ```
3. Config lives in `config/sentry.php`. Errors in `local`/`testing` are dropped automatically.

**Frontend (Next.js):**

1. Install: `bun add @sentry/nextjs`
2. Set the DSN in `.env.local`:
   ```dotenv
   NEXT_PUBLIC_SENTRY_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
   NEXT_PUBLIC_SENTRY_ENVIRONMENT=production
   ```
3. Config files: `sentry.client.config.ts`, `sentry.server.config.ts`, `sentry.edge.config.ts`.
4. A global `error.tsx` at `src/app/error.tsx` catches unhandled errors.

### 5.3 Log aggregation

```bash
# Send Laravel logs to Papertrail / Logtail
LOG_CHANNEL=stack
LOG_STACK=daily,papertrail
```

---

## 6 · CI/CD (GitHub Actions)

A `.github/workflows/ci.yml` is included in the repo. On every push to `main` it:

1. Runs `composer install` and PHPUnit tests
2. Runs `npm ci && npm run build` for the frontend
3. Runs Vitest unit tests
4. Optionally deploys via SSH on `main`

To add deployment, add these secrets in GitHub:
- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KEY` (SSH private key)
- `DEPLOY_PATH` (e.g. `/var/www/smartpos`)

---

## 7 · Offline POS (PWA)

SmartPOS Pro ships as a Progressive Web App. In Chrome/Edge:

1. Open `https://pos.yourdomain.com`
2. Click the **Install** button in the address bar
3. The POS terminal works offline — pending sales queue in IndexedDB and sync automatically when connectivity resumes

The service worker is at `/sw.js` and is registered automatically by `PwaRegister`.

---

## 8 · Multi-tenant considerations

- Each tenant is isolated by `tenant_id` on every model via `BelongsToTenant` scope.
- Never run `php artisan migrate --seed` against a shared production database without first verifying the seed guard.
- Super-admin (tenant 0) can impersonate any tenant via `X-Tenant-ID` header when authenticated as a super-admin.

---

_Generated by [Claude Code](https://claude.ai/code)_
