# Apleqz 3.0 — Job Application Tracker

![Version](https://img.shields.io/badge/Version-3.0-blue?style=flat)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-4169E1?style=flat&logo=postgresql&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?style=flat&logo=vuedotjs&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-2-9553E9?style=flat&logo=inertia&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-4-F59E0B?style=flat)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3-38B2AC?style=flat&logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=flat&logo=vite&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-4-FF6384?style=flat&logo=chartdotjs&logoColor=white)
![vue-i18n](https://img.shields.io/badge/vue--i18n-11-42b883?style=flat&logo=vue.js&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat)

Multi-user Laravel application to track job applications, statistics, and interview timelines. Built with **Laravel 13**, **PostgreSQL**, **Inertia.js**, **Vue 3**, and **Filament** for administration.

## Requirements

- PHP 8.3+ (with extensions: `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`)
- Composer
- Node.js 20+ (build assets; Playwright scraping when using job interactions)
- PostgreSQL 14+

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL in `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=apleqz
DB_USERNAME=apleqz
DB_PASSWORD=your_password
```

Then:

```bash
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

Visit `http://localhost:8000`.

### Playwright job scraping (optional)

Sources that need cookies, JavaScript rendering, or “load more” clicks use a Node + Playwright bridge (`scripts/scrape-page.mjs`). After `npm install`, install Chromium once:

```bash
npx playwright install chromium
```

Set `engine` to `playwright` or add `interactions` in a job source’s extraction config. Tune timeouts via `JOB_SCRAPE_PLAYWRIGHT_TIMEOUT` in `.env`.

### Default accounts (after seeding)

| Role  | Email               | Password  |
|-------|---------------------|-----------|
| Admin | admin@apleqz.test   | password  |
| User  | demo@apleqz.test    | password  |

- User app: `/dashboard`, `/applications`
- Admin panel: `/admin` (admin users only)

## Import from Excel

Import rows from the **Vagas** sheet of your spreadsheet:

```bash
php artisan applications:import "/path/to/file.xlsx" --user=demo@apleqz.test
```

## Translations

Default strings live in `lang/en/app.php` and `lang/pt/app.php`, seeded into the database. Admins can edit translations at **Admin → Translation lines**.

Users switch language with the EN / PT toggle in the navigation bar.

## File attachments & preview

Per-user file uploads are **disabled by default**. An admin can enable them under **Admin → Users** for each account:

- **Application files** — attach PDF or DOCX files (up to 10 MB each) when editing an application.
- **Personal files** — a private library at `/files` for the same file types.

Supported uploads: **PDF** and **DOCX** only (validated on upload).

Users can **view** attachments in the browser without downloading:

- **PDF** — inline preview in a modal.
- **DOCX** — rendered in the modal via [docx-preview](https://www.npmjs.com/package/docx-preview) (layout may differ slightly from Word).

Download remains available when a local copy is needed.

## Production deployment

These steps assume a Linux VPS with Nginx (or Apache) and PHP-FPM. Adjust paths and PHP binary names to match your server.

### 1. Server prerequisites

Install and enable:

- PHP **8.3+** and extensions: `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`
- Composer, Node.js 20+, PostgreSQL 14+, Nginx, Git
- A system user for the app (e.g. `www-data` or your deploy user)

If the server has multiple PHP versions, **always call the correct binary** in cron, Supervisor, and deploy scripts (e.g. `/usr/bin/php84` instead of `php`).

Create the database and clone the app:

```bash
sudo mkdir -p /var/www/apleqz
sudo chown $USER:www-data /var/www/apleqz
git clone <your-repo-url> /var/www/apleqz
cd /var/www/apleqz
```

### 2. Environment (`.env`)

```bash
cp .env.example .env
php artisan key:generate
```

Set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_VERSION=3.0

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=apleqz
DB_USERNAME=apleqz
DB_PASSWORD=your_secure_password

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=hello@your-domain.example
MAIL_FROM_NAME="${APP_NAME}"

# Job alerts (if enabled)
MISTRAL_API_KEY=your_key
MISTRAL_MODEL=ministral-3b-latest
```

Job alerts (scraping, AI matching, digests) rely on the **database queue** (`QUEUE_CONNECTION=database`). You must run a queue worker in production (see below).

### 3. First-time install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force   # optional; skip on production if you create users manually
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point the web server document root to `public/`. Example Nginx `root`:

```
root /var/www/apleqz/public;
```

Use PHP-FPM and HTTPS (e.g. Certbot). Ensure `storage/` and `bootstrap/cache/` are writable by the web server user.

### 4. Playwright on the server (optional)

Only needed if job sources use `playwright` or page interactions:

```bash
npm ci
npx playwright install chromium
```

Set `JOB_SCRAPE_NODE_BINARY` in `.env` if `node` is not on the default PATH for PHP-FPM/cron.

### 5. Laravel scheduler (cron)

Scheduled tasks are defined in `bootstrap/app.php`:

| Task | Schedule |
|------|----------|
| `applications:send-reminders` | Every 30 minutes |
| `jobs:scrape-sources` | Daily at 09:00 and 19:00 UTC |

Add **one** cron entry for the user that owns the app (replace paths and PHP binary):

```cron
* * * * * cd /var/www/apleqz && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

On some hosts use `crontab -e` as the deploy user; on others add a file under `/etc/cron.d/`. Verify after a few minutes:

```bash
php artisan schedule:list
```

### 6. Queue worker (required for job alerts)

Scraping, AI match evaluation, and digest emails are dispatched to the queue. Without a worker, scheduled scrapes will enqueue jobs that never run.

Run a persistent worker with **Supervisor** (recommended). Example `/etc/supervisor/conf.d/apleqz-worker.conf`:

```ini
[program:apleqz-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/apleqz/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/apleqz/storage/logs/worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start apleqz-worker:*
```

For a quick test without Supervisor:

```bash
php artisan queue:work database --once
```

After deploys that change job code, restart workers:

```bash
php artisan queue:restart
sudo supervisorctl restart apleqz-worker:*
```

### 7. Deploying updates (`git pull`)

```bash
cd /var/www/apleqz
git pull

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

sudo systemctl reload php-fpm    # or your PHP-FPM service name
sudo supervisorctl restart apleqz-worker:*
```

### 8. Job sources across environments

Export configured sources from **Administration → Job sources → Export**, then import the JSON on production. Sources are matched by URL (existing URLs are updated; new URLs are created).

### 9. Post-deploy checks

- Site loads over HTTPS; login works
- `php artisan about` shows correct environment and PHP version
- Cron: `php artisan schedule:list` shows upcoming runs
- Queue: `php artisan queue:monitor database:default` or check `storage/logs/worker.log`
- Application export (PDF/DOCX/XLSX) if you use those features
- Job alerts: active sources scrape, matches appear, digests send (if mail is configured)

## Tests

```bash
php artisan test
```

## License

MIT
