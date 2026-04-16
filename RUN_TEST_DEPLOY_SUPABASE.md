# Run, test, and deploy (PHP app + Supabase CDN, no npm)

This project’s primary UI is **PHP under `public/`** with **MySQL** for day-to-day operation. Supabase integration is **optional** and uses **no npm packages**: server-side **REST** via `public/api/supabase_rest.php`, or browser **ES modules** from a CDN (see `public/assets/js/supabase-cdn.js`).

**Security:** Never commit real API keys or database passwords. Copy `public/api/supabase_config.sample.php` to `public/api/supabase_config.php` (gitignored) and paste your keys there. If keys were shared in chat or tickets, rotate them in the Supabase dashboard.

---

## 1. Local run (XAMPP / PHP)

1. Place the project under your web root (e.g. `C:\xampp\htdocs\Yasmin-Aliarose-Dormitory-System`).
2. Import or apply the schema: `dormitory_full_schema.sql` and/or `scripts/db_migrate.php` as documented in the main README.
3. Configure MySQL in `public/api/db.php` (or your env pattern).
4. Open `http://localhost/Yasmin-Aliarose-Dormitory-System/public/` (adjust path to match your folder name).
5. Test **login** (`public/login.php`), **register** (`public/register.php`), **profile** (`public/profile.php`), and **admin** (`public/admin/dashboard.php`).

---

## 2. Supabase configuration (optional)

1. In Supabase: **Settings → API** — copy **Project URL** and **anon public** key. For server-only admin tasks, optionally add the **service_role** key (keep it only in `supabase_config.php`, never in the browser).
2. Copy `public/api/supabase_config.sample.php` to `public/api/supabase_config.php`.
3. Set `SUPABASE_URL`, `SUPABASE_ANON_KEY`, and optionally `SUPABASE_SERVICE_ROLE_KEY`.
4. **Pooler / Postgres URI** (e.g. `postgresql://postgres.xxx:PASSWORD@...pooler.supabase.com:6543/postgres`) is for **migrations or CLI tools** (pg_dump, psql, PDO `pgsql`), not for the stock PHP mysqli app. Use it when you migrate data from MySQL to Postgres.

### PHP REST example

```php
require_once __DIR__ . '/supabase_rest.php';
$r = supabase_rest_select('your_table', ['id' => 'eq.1']);
```

### Browser CDN client (optional)

Load `public/assets/js/supabase-cdn.js` as `type="module"` and set `data-supabase-url` / `data-supabase-anon-key` on `body` or a root element (see comments in that file). Prefer **RLS** so the anon key is safe in the browser.

---

## 3. Testing checklist

- **Auth:** Login as user and admin; confirm redirect and session.
- **Profile:** Stats, Pay Rent button (mobile/tablet scale), dark theme from header dropdown.
- **Admin:** Dashboard charts (light/dark), sidebar scroll, payments modal.
- **Health:** If deployed, call `public/api/deploy_health.php` (with token if configured).

---

## 4. Deploy (typical)

- **PHP host:** Upload the project; point document root to `public/`; set DB credentials; ensure `uploads/` (or receipt paths) are writable if you use file uploads.
- **Vercel:** Often used as a **reverse proxy** to a PHP backend URL (see `vercel.json` / `DEPLOY_VERCEL.md` in this repo). The Next.js `frontend/` folder is separate; running it requires Node for build—omit if you stay on PHP-only hosting.

---

## 5. Performance notes

- Login/register transition uses short CSS transforms (`will-change` on panels); `prefers-reduced-motion` shortens the animation.
- Charts use Chart.js from CDN with modest animation duration; admin chart layout is responsive via `admin-charts-grid` breakpoints.
