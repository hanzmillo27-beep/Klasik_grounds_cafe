# Functional requirement implementation

Complete requirements in SRS order. Test, commit and push each requirement to
`origin/main` before starting the next. Use jQuery and existing UI plugins.
The earlier audit is retained as the historical baseline.

| Requirement | Status | Verification |
| --- | --- | --- |
| FR-001 | Complete | `php tests/fr_001.php`; jQuery/Bootstrap sign-in popup |
| FR-002 | Complete | `php tests/fr_002.php`; malformed credential validation |
| FR-003 | Complete | `php tests/fr_003.php`; accessible error and cooldown popup |
| FR-004 | Complete | `php tests/fr_004.php`; role refresh, action permissions, owner protection |
| FR-005 | Complete | `php tests/fr_005.php`; POST logout and destroyed session |
| FR-006 | Complete | `php tests/fr_006.php`; validated account creation and duplicate checks |
| FR-007 | Complete | `php tests/fr_007.php`; safe targeted updates and password retention |
| FR-008 | Complete | `php tests/fr_008.php`; migration `008_user_status.sql`; deactivate/reactivate |
| FR-009 | Complete | `php tests/fr_009.php`; shared role list and PostgreSQL role constraint |
| FR-010 | Complete | `php tests/fr_010.php`; safe active/inactive user list |
| FR-011 | Complete | `php tests/fr_011.php`; SQLite and isolated PostgreSQL product/recipe tests |
| FR-012 | Complete | `php tests/fr_012.php`; SQLite and isolated PostgreSQL update/rollback tests |
| FR-013 | Complete | `php tests/fr_013.php`; reversible deactivation and retained recipes/history; PostgreSQL migration |
| FR-014 | Complete | `php tests/fr_014.php`; active products only in POS/kiosk menus; management retains inactive records |
| FR-015 | Complete | `php tests/fr_015.php`; availability controls and server-side POS/kiosk rejection; PostgreSQL migration |
| FR-016 | Complete | `php tests/fr_016.php`; cashier order creation, fresh-order UI, POST/input checks |
| FR-017 | Complete | `php tests/fr_017.php`; multiple products, integer quantities, duplicate/invalid item rejection |
| FR-018 | Complete | `php tests/fr_018.php`; edited POS/kiosk quantities saved; original kiosk prices retained, new items priced by server |
| FR-019 | Complete | `php tests/fr_019.php` on SQLite/PostgreSQL; `tests/browser-pos.ps1` on Edge; item removal, reload, historical prices and guarded cart edits |
| FR-020 | Complete | `php tests/fr_020.php`; integer-cent calculations, trusted prices, displayed-total check, price-refresh UI |
| FR-021 | Complete | `php tests/fr_021.php`; required validated cash tender, persisted payment, no sale/stock change for insufficient payment |
| FR-022 | Complete | `php tests/fr_022.php`; trusted saved change, exact/overpayment and live jQuery change preview |
| FR-023 | Complete | `php tests/fr_023.php`; paid atomic checkout/rollback, retry deduplication, PostgreSQL-compatible loyalty updates |
| FR-024 | Complete | `php tests/fr_024.php`; receipt opens after paid checkout; saved item snapshots, cashier, paid amount and change; history re-view |
| FR-025 | Complete | `php tests/fr_025.php`; database-generated unique sale IDs, stable receipt/retry identity, deployed primary key confirmed |
| FR-026 | Complete | `php tests/fr_026.php`; validated zero/fractional stock creation, unique names, existing edit targets, accurate form precision |
| FR-027 | Complete | `php tests/fr_027.php`; validated atomic stock additions, exact fractional quantities, missing/overflow rejection |
| FR-028 | Complete | `php tests/fr_028.php` SQLite/PostgreSQL; isolated `tests/stock_concurrency.php` confirms competing shared-stock sales cannot oversell |
| FR-029 | Complete | `php tests/fr_029.php`; `tests/browser-inventory.ps1`; current quantities, three-decimal safe table, refreshed stock |
| FR-030 | Complete | `php tests/fr_030.php`; per-item minimums and inclusive low-stock boundaries across Inventory/Dashboard; PostgreSQL migration |
| FR-031 | Complete | `php tests/fr_031.php` SQLite/PostgreSQL; owner daily report, selected-day boundaries, totals and CSV/print UI |
| FR-032 | Complete | `php tests/fr_032.php`; selected ISO weeks, Monday/Sunday boundaries and cross-year reports with CSV/print |
| FR-033 | Complete | `php tests/fr_033.php`; selected monthly reports, leap years, month/year boundaries and matching exports |
| FR-034 | Complete | `php tests/fr_034.php`; quantities/revenue by product for selected periods using saved sale snapshots and CSV/print |
| FR-035 | Complete | `php tests/fr_035.php`; current inventory snapshot with quantities/minimums/status, safe CSV/print and inventory-only staff access |

All 35 acceptance scripts pass. Critical database paths were also checked using
isolated PostgreSQL schemas, including two concurrent sales competing for the
last shared ingredient. The browser harnesses use the actual PHP views, local
jQuery/Bootstrap/DataTables assets, and offline API fixtures.

Run the requirement checks from the project root in PowerShell:

```powershell
Get-ChildItem tests/fr_*.php | ForEach-Object {
    php $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "Requirement check failed: $($_.Name)" }
}
php tests/rate_limiter.php
powershell -NoProfile -ExecutionPolicy Bypass -File tests/browser-pos.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tests/browser-inventory.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tests/browser-reports.ps1
```

For PostgreSQL, enable PDO's PostgreSQL extension and use the isolated runner:

```powershell
php -d extension=php_pdo_pgsql.dll -d auto_prepend_file=tests/postgres.php tests/fr_035.php
php -d extension=php_pdo_pgsql.dll -d auto_prepend_file=tests/postgres.php tests/stock_concurrency.php
```

The runner creates and removes its own randomly named schema and uses the
session-mode connection on port 5432. Application connections continue using
the configured production port.

Database migrations in `migrations/` were applied to the configured PostgreSQL
database before publishing the code that uses them. On another existing
database, run each file in filename order through `php scripts/migrate.php
<filename.sql>`. The migration ledger makes repeat runs safe. Historical sales
retain unknown payment/change values as NULL; all new checkouts require payment.

Owners can open `/Reports` for sales, product sales, and inventory reports.
Inventory staff can open `/Reports` for inventory snapshots only. Report CSV and
print exports use the loaded period/snapshot, including totals or inventory
counts. Inventory quantities and minimums support three decimal places.
