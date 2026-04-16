# Yasmin & Aliarose Dormitory Management System

This project has been restructured to follow standard secure directory architecture. 

## Directory Architecture:
- **/public:** Publicly accessible files (HTML, PHP frontend, JS, CSS, images, user uploads). Serve web traffic from this directory!
- **/private:** Restricted files, backups, logs, and sensitive materials. Not accessible from the web.
- **/config:** Configuration files. Contains `config.env` where database credentials and site-wide constants are stored securely.
- **/temp:** Temporary files requiring cleanup.
- **/scripts:** Automation and miscellaneous utility scripts.

## Database
The system automatically reads database configuration from `config/config.env`. Ensure your XAMPP MySQL has a database named `dormitory_db` and your root user password is configured in the `.env` file correctly. The database schema backup can be found in `private/backups/db.sql`.

### Initialize / Migrate
- Quick setup: run `setup_db.php` once.
- Idempotent migrations (recommended): `php scripts/db_migrate.php`
- Full consolidated SQL schema: `dormitory_full_schema.sql`

### Deployment Health Check
After deployment, check:
- `public/api/deploy_health.php`

Optional secure mode:
- set `HEALTHCHECK_TOKEN` in your environment
- then call `public/api/deploy_health.php?token=YOUR_TOKEN`

## Accessing the Project
Since everything public corresponds to the `public/` directory, open your browser and navigate to:
**http://localhost/Dormitory_System/public/**

Enjoy the organized and bug-free system!