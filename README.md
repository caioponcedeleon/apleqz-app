# Apleqz — Job Application Tracker

Multi-user Laravel application to track job applications, statistics, and interview timelines. Built with **Laravel 13**, **PostgreSQL**, **Inertia.js**, **Vue 3**, and **Filament** for administration.

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+ (build assets only)
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
