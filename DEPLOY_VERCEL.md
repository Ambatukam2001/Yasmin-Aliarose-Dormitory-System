# Vercel Deployment Guide (PHP System)

This project is PHP-based, so Vercel should be used as an edge proxy while the PHP runtime is hosted on a PHP-capable platform.

## Recommended architecture

- **Vercel**: public domain, SSL, global edge proxy
- **PHP host** (XAMPP-like runtime): backend app execution
  - Examples: Railway (PHP), Render (PHP), VPS, cPanel hosting
- **MySQL database**: hosted DB (or managed DB service)

## Setup steps

1. Deploy this codebase to your PHP hosting provider.
2. Confirm backend is reachable, for example:
   - `https://your-php-backend.example.com/public/index.php`
3. Update `vercel.json` destination:
   - Replace `https://your-php-backend.example.com` with your real backend domain.
4. Import this repository to Vercel.
5. Deploy.

Vercel will forward all routes to your PHP backend, including:

- `index.php`, `profile.php`, `booking.php`
- admin routes under `public/admin/`
- API routes under `public/api/`

## Database setup

Use one of these:

- Run `setup_db.php` once after deployment, or
- Import `dormitory_full_schema.sql` directly to MySQL
- Run idempotent migrations:
  - `php scripts/db_migrate.php`

## Pre-launch checklist

- `config/config.env` values are set in backend host
- File upload folders are writable:
  - `public/uploads/chat/`
  - `public/uploads/documents/`
  - `public/uploads/receipts/`
- Admin login works
- Resident login/register/profile works
- Request transfer and request-out flows work end-to-end
- Payment submission and admin review work
- Health endpoint responds OK:
  - `https://your-domain/public/api/deploy_health.php`
  - If you set `HEALTHCHECK_TOKEN`, call:
    - `https://your-domain/public/api/deploy_health.php?token=YOUR_TOKEN`

## Notes

- If you want a pure Vercel-native deployment (without external PHP host), the backend must be migrated from PHP to serverless Node/Next APIs (or another Vercel-supported runtime).
