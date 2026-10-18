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
| FR-030 | Pending | |
| FR-031 | Pending | |
| FR-032 | Pending | |
| FR-033 | Pending | |
| FR-034 | Pending | |
| FR-035 | Pending | |
