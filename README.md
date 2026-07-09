# Apleqz — Job Application Tracker

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

- PHP 8.2+
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

## VPS deployment (no Docker)

1. Install PHP 8.2+, extensions (`pgsql`, `mbstring`, `xml`, `curl`, `zip`), Composer, PostgreSQL, Nginx, Git.
2. Create database and user; clone the repo to e.g. `/var/www/apleqz`.
3. `cp .env.example .env` — set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and database credentials.
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build` (on your machine or the server once)
6. `php artisan migrate --force && php artisan db:seed --force`
7. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
8. Point Nginx `root` to `public/`; use PHP-FPM; enable HTTPS (e.g. Certbot).
9. Cron: `* * * * * cd /var/www/apleqz && php artisan schedule:run >> /dev/null 2>&1`
10. Ensure `storage/` and `bootstrap/cache/` are writable by the web server user.

## Tests

```bash
php artisan test
```

## License

MIT
