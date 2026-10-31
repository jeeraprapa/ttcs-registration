# UH Registration

Laravel 13 + MySQL application for the Upper House Residences Hong Kong Private Preview, 14–15 November 2026. Public Blade registration and confirmation follow the assets in `brief/`. The `/admin` portal has separate authentication, dashboard, registration search/filter/detail, and session opening/closing.

## Local preview on this machine

The prepared `.env` uses isolated MySQL 8.0 bound to `127.0.0.1:3307`; data is in ignored `.tools/mysql-data`. Application and test databases are separate. Existing Laragon databases were not changed.

From the project directory in PowerShell:

```powershell
./scripts/start-local.ps1
```

Open `http://127.0.0.1:8000/` or `http://127.0.0.1:8000/admin/login`. A random local preview admin password is saved in ignored `.tools/admin-login.txt`; do not publish that file. One synthetic booking, `Preview Test` / `preview-test@example.test`, was used for browser verification.

The script starts hidden web, queue and scheduler processes and supports `-Php` for a different executable. Queue/scheduler IDs are in `.tools/processes.json`. Processes stop when Windows is stopped; stop known process IDs when no longer needed. Do not expose this development server publicly.

## Setup elsewhere

Use PHP/extensions compatible with locked dependencies (tested PHP 8.4.18), Composer, a compatible Node runtime (tested Node 23.5.0), and MySQL. Point the web server document root to **`public/`**, never the repository root containing `.env` and `brief/`.

```text
composer install
copy .env.example .env
php artisan key:generate
```

Create a dedicated MySQL database/user, fill `DB_*`, then:

```text
php artisan migrate --seed
npm install
npm run build
php artisan admin:create your-admin@example.com --name="Administrator"
php artisan serve
```

The admin command prompts for a password and confirmation without echoing them. There is no public admin registration or default production password. Recovery commands: `admin:password` and `admin:disable`; check arguments with `php artisan help <command>`.

## Booking and email

### Admin account seeder

Set `ADMIN_SEED_NAME`, `ADMIN_SEED_EMAIL`, and `ADMIN_SEED_PASSWORD` in your private `.env`. Use a password with at least 12 characters, uppercase/lowercase letters, a number and a symbol (maximum 72 characters). There are no default credentials.

```text
php artisan config:clear
php artisan db:seed --class=AdminUserSeeder
```

The main `php artisan db:seed` also calls this seeder. With no credentials configured it skips admin creation. Existing accounts are left unchanged, including passwords and disabled status. Use `admin:password` to reset an existing password. Remove `ADMIN_SEED_PASSWORD` from the environment after provisioning; rebuild cached configuration if used.

- Four sessions from the brief. Guests 1–4 include the registrant. Session capacity is unlimited as confirmed by the user.
- CSRF, server validation, browser-owned idempotency tokens, atomic booking/consent/outbox writes and confirmation snapshots. A repeated successful submission returns its original booking even after a session closes.
- Public confirmation belongs to the browser session. Admin requires an active account and password-version checks.
- `REGISTRATION_MAIL_MODE=local` records `local_preview` without sending mail or logging contacts; this is not a delivery test.
- `confirmations:dispatch` runs every minute through the scheduler. `queue:work` processes its jobs. Run both in production; manually process local pending records with `php artisan confirmations:dispatch` then `php artisan queue:work --stop-when-empty`.
- Real email needs provider/sender configuration, `REGISTRATION_MAIL_MODE=send`, and a real sending mailer (not log). Provider acceptance does not prove delivery; ambiguous transport failures can cause duplicate delivery.

## Pending business decisions

Title/country lists, consent wording/required/Mainland rules and `Asia/Hong_Kong` timezone remain provisional in `config/registration.php`. Local defaults require privacy acknowledgement, make marketing optional, and use a self-declared Mainland checkbox. Production booking is blocked unless `REGISTRATION_POLICY_CONFIRMED=true`; set it only after the client confirms these rules. Confirm email provider, retention and hosting before deployment. Export, resend, cancellation, 2FA and admin-user management screens are optional and not implemented.

## Tests

Create a dedicated disposable MySQL database ending in `_test` or `_testing`; copy `.env.example` to `.env.testing`, set `APP_ENV=testing` and its database credentials. Never point tests at application/production data. Tests enforce the suffix and MySQL connection before migrations.

```text
php artisan key:generate --env=testing
php artisan test
php vendor/bin/pint --test
npm run build
```

Tests cover validation, ownership, idempotent replay/concurrency across two PHP processes, rollback, consent snapshots, admin authentication/throttle/CSRF/revocation, filters and email failures/lease recovery. Test records are confined to the test database.

Context: `.ai/agent.md`; technical instructions: `.ai/skills/laravel-mysql/SKILL.md`; design: `.ai/system-design.md`.

### Registration Excel export
Admin users can select **Export Excel (.xlsx)** on /admin/registrations. Apply filters first; the download includes all matching bookings across pages, with contact details, session snapshots, guest counts, referral source and email status. Phone numbers remain text. The export uses the same admin authentication and filter validation as the list.

