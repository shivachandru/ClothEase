# Testing

Run the Laravel test suite with:

```powershell
php artisan test
```

PHP executable in the current XAMPP environment: `C:\xampp\php\php.exe`.

The default test environment uses in-memory SQLite. At minimum, add coverage for first-run setup, login throttling/roles, tenant isolation, product uniqueness, billing totals and discounts, split payments, insufficient stock, transaction rollback, stock ledger consistency, purchase/return accounting, profit reports and backup restore.

Automated coverage currently includes first-run setup, login state, cross-shop catalogue isolation, sale snapshots/tax/discount, stock rollback, split-payment equality, POS rendering, stock-manager adjustment authorization, expenses/profit report/CSV, supplier purchase and later partial payment, purchase rollback, multiple product variants and price/stock separation, partial return/exchange, excess-return prevention, condition-based restock and profit adjustment after returns, customer credit and collection, owner-created staff PIN login, audit-log rendering, and SQLite backup create/verify/restore. The latest run passed 18 tests and 154 assertions. Browser interaction on real hardware, MySQL/MariaDB, concurrency, LAN security, disconnected operation, physical scanner, thermal printer widths, live-app restore, automatic Windows scheduler registration and startup/shutdown are not yet verified. Passing these tests does not establish those requirements.
