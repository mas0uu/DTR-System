# DTR Web App

Daily Time Record (DTR) application built with Laravel, Inertia.js, React, and TypeScript.

## Stack

- Backend: Laravel 12 (PHP 8.2+)
- Frontend: React 18 + TypeScript + Inertia.js
- UI: Ant Design + Tailwind CSS v3
- Build tools: Vite

## Main Features

- Session-based authentication (email or student number login)
- Admin-only user provisioning (public registration is disabled)
- DTR rows with:
  - Clock in / clock out
  - Break tracking
  - Leave/absence requests for missed past rows
  - Late-minute computation
- Monthly DTR view with print layout
- Payroll generation/review/finalization workflow
- Profile management

## Project Setup

1. Install PHP dependencies
```bash
composer install
```

2. Install Node dependencies
```bash
npm install
```

3. Prepare environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations and seeders
```bash
php artisan migrate --seed
```

5. Start development servers
```bash
php artisan serve
npm run dev
```

## Useful Commands

- Run tests:
```bash
php artisan test
```
Note: feature tests use SQLite in-memory by default (`pdo_sqlite` extension required).

- Build assets:
```bash
npm run build
```

## Optional Local Redis Cache

For local development on Windows, this project can use Redis for the Laravel cache without changing sessions or queues.

```bash
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

The app currently keeps `SESSION_DRIVER=database` and `QUEUE_CONNECTION=database` so enabling Redis cache remains a low-risk local change.

## Core Paths

- Routes: `routes/web.php`
- DTR Controllers:
  - `app/Http/Controllers/DtrMonthController.php`
  - `app/Http/Controllers/DtrRowController.php`
- DTR Pages:
  - `resources/js/Pages/Dtr/Index.tsx`
  - `resources/js/Pages/Dtr/Show.tsx`

## Notes

- Timezone-sensitive logic uses `Asia/Manila`.
- DTR monthly/row ownership is enforced with policies.

## Deploy on Render

This repository includes a Render Blueprint (`render.yaml`) and Docker setup for production.

1. Push this project to GitHub/GitLab.
2. In Render, choose **New +** -> **Blueprint**.
3. Connect your repository and select this project's `render.yaml`.
4. During setup, provide:
   - `APP_URL` = your final Render URL or custom domain (for example `https://your-app.onrender.com`)
   - `APP_KEY` = output of `php artisan key:generate --show` (must be Laravel key format like `base64:...`)
   - Optional starter account vars:
     - `AUTO_SEED_STARTER_ACCOUNTS=true` (run starter seeder on boot)
     - `STARTER_ACCOUNTS_ALLOW_ON_NON_EMPTY_DB=false` (recommended; prevents re-seeding once users exist)
     - `STARTER_ACCOUNTS_PASSWORD=<your-temp-password>` (used only when creating starter accounts)
     - `STARTER_ACCOUNTS_FORCE_RESET=false` (set to `true` only if you intentionally want to overwrite starter account passwords)
5. Deploy the Blueprint.

What gets created:
- Web service (`dtr-web-app`) built from `Dockerfile`
- Managed PostgreSQL database (`dtr-postgres`)
- Generated `APP_KEY`

The container startup script (`scripts/render-start.sh`) will:
- Create Laravel runtime folders
- Ensure `storage` symlink exists
- Run `php artisan migrate --force`
- Optionally seed starter accounts (guarded by env vars)
- Start the app on Render's assigned `PORT`

### Important production note

Profile photos currently use Laravel's local `public` disk. On Render, local filesystem data is ephemeral unless you attach a persistent disk or move uploads to S3-compatible storage.
