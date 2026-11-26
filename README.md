# ClothEase
ClothEase

# ClothEase POS

ClothEase is a local-first clothing shop billing and inventory application built on Laravel 12, PHP 8.2+, and a database abstraction supporting SQLite for development and MySQL/MariaDB for shop installations.

## Current project state

The foundation is in place: first-run owner/shop setup, throttled password and cashier PIN login, owner-managed staff roles, shop-scoped catalogue and variant stock, an inventory movement ledger for opening stock, purchases, sales, returns and count adjustments, transactional billing with cash/UPI/card/split and partial Udhaar records, customer and supplier payment collection, printable receipts, expense entry, date-filtered sales/profit reports with CSV export, audit history, and private SQLite backup/restore with checksum and integrity checks. A daily Laravel backup command is scheduled for 02:00, but Windows Task Scheduler registration and MySQL backups remain pending. See [CODEX_PROGRESS.md](CODEX_PROGRESS.md) for implementation and verification status. This is an early development build, not a commercial release.

## Requirements

- PHP 8.2 or newer with PDO SQLite for the default local development setup.
- Composer 2.
- A modern browser. No remote assets are required by the UI.
- For production-like shop setup, MySQL or MariaDB and the Windows deployment work described in [INSTALLATION_WINDOWS.md](INSTALLATION_WINDOWS.md).

## Run locally on Windows/XAMPP

The checked-in `.env` is configured for a local SQLite development database. If setting up a clean checkout:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Open `http://127.0.0.1:8000`. The first visit offers single-use shop setup. Register a shop owner with a unique email and a password of at least 10 characters. Keep `.env`, `database/database.sqlite`, and backups private.

In this workspace, PHP is at `C:\xampp\php\php.exe`. Composer works when that directory is on PATH. The delivered interface uses local CSS/vanilla JavaScript and does not need the Vite build for runtime.

## Notes

- Financial tables use shop ownership and sale-time snapshots. Billing runs in a database transaction.
- SQLite is for development and a single local process; configure MySQL/MariaDB for multi-device LAN use.
- PHP's built-in server is a development server only. Do not expose it to the public internet.
- Required commercial modules and deployment verification remain in progress; do not use this build as a production POS yet.
