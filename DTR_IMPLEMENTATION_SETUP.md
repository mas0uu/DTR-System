# DTR Implementation Setup

## Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL or SQLite (for local and tests, SQLite is supported)

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Run

```bash
php artisan serve
npm run dev
```

## Access

- App: `http://localhost:8000`
- Login: `http://localhost:8000/login`

Public registration is intentionally disabled. Create users through:

- `Admin > Users` (`/admin/employees`)

## Initial Admin Account

Create the first admin manually (tinker or seeder), then use admin UI for all succeeding accounts.

Example via tinker:

```php
use App\Models\User;

User::create([
    'name' => 'System Admin',
    'email' => 'admin@example.com',
    'password' => 'password',
    'role' => 'admin',
    'is_admin' => true,
    'email_verified_at' => now(),
]);
```

## Recommended Post-Setup Checks

1. Login as admin and create one regular employee and one intern.
2. Verify DTR rows are generated from `starting_date` and `working_days`.
3. Verify employee can clock in/out and submit leave/absence request.
4. Verify admin can review/finalize payroll and attendance locks apply.
5. Verify `leave:refresh-balances` scheduler runs daily.

## Testing

```bash
php artisan test
```

If tests fail with SQLite driver errors, install/enable `pdo_sqlite`.
