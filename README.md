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
