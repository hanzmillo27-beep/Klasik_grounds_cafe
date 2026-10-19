# SRS functional requirements audit

Reviewed on **19 October 2026 (Asia/Manila)** against `C:\Users\Hanz\Downloads\FINALSRS (1).docx`, section 6, FR-001 through FR-035. Related business rules, use cases, data requirements, and acceptance criteria were used to interpret those requirements.

Project: `C:\xampp\htdocs\sample`, PHP application, commit `10a67516ea5afb39217c4bddf8398b5eaa978769` (`Open Auth sign-in form in a dark popup dialog`). The separate Flutter application was outside this audit.

**The project does not yet satisfy all 35 functional requirements.**

| Assessment | Requirements |
| --- | ---: |
| Implemented in source | 17 |
| Partial | 10 |
| Missing | 8 |
| Total reviewed | 35 |

“Implemented” means the relevant UI, endpoint, and business logic were found. It does **not** mean that the requirement passed an end-to-end acceptance test. “Partial” means related functionality exists but a required behavior or an important supported workflow is incomplete. “Missing” means no implementation of the required behavior was found.

This was an audit, with no application changes, migrations, production account updates, or live transactions. Database workflows were reviewed in source; the deployed database schema and full cashier workflows were not exercised. Non-functional requirements were not given a comprehensive pass/fail assessment.

The accompanying [CSV matrix](FR_COMPLIANCE_MATRIX.csv) includes each requirement's full wording from the DOCX, the assessment, evidence, and an acceptance check. File references below are repository-relative, followed by their source line number at the audited commit.

## Findings to address first

1. **Checkout does not record or validate payment.** There is no amount-paid field, tender record, insufficient-payment check, or change calculation. Checkout inserts a sale, deducts inventory, and can mark a kiosk order completed anyway. This blocks FR-021, FR-022, and full compliance with FR-023; it violates BR-004, BR-005, and BR-006. Evidence: `app/views/POS/index.php:59`, `app/api/POS/POSController.php:69`, `app/Models/POS.php:403`, `app/Models/POS.php:491`.

2. **An administrator can bypass the owner-edit guard.** `/api/Users/add` accepts an existing user ID and calls a model method that then performs an update. The owner-account protection is present in `edit` and `delete`, but absent from this alternate update path. An isolated test using the actual router authorization and API controller confirmed that an admin reached the save operation for a synthetic owner through `add`, while `edit` rejected the same operation with HTTP 403. No real account was changed. Evidence: `app/api/Users/UsersController.php:27`, `app/api/Users/UsersController.php:59`, `app/api/Users/UsersController.php:85`, `app/Models/Users.php:61`.

3. **Changes to a retrieved kiosk cart are discarded at checkout.** The cashier can change quantities, add items, or remove items in the displayed cart, but the server replaces that submitted cart with the original kiosk order items. The saved sale can therefore differ from the cashier's edited cart and displayed total. Evidence: `public/js/POS.js:362`, `public/js/POS.js:459`, `app/Models/POS.php:217`, `app/Models/POS.php:241`.

4. **Deactivation is replaced with permanent deletion.** Users and products have delete operations but no active/inactive or discontinued state. Login does not check account status, and authorization trusts the session's cached user and role. Disabling a user and preventing unavailable products from entering new orders are therefore not implemented. Evidence: `app/Models/Users.php:107`, `app/Models/Products.php:246`, `app/Models/Users.php:33`, `app/Core/Auth.php:17`.

5. **Required reports are incomplete or absent.** The current-week net-profit chart is not a weekly sales report. Transaction history has no selected-date reporting or period totals. Monthly sales and product-sales reports are absent. Inventory is displayed but has no dedicated report generation, print, or export workflow. Evidence: `app/Models/Dashboard.php:9`, `app/Models/Dashboard.php:54`, `app/Models/POS.php:77`, `public/js/Inventory.js:8`.

## Functional requirements matrix

### Authentication: FR-001–FR-005

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-001 | Registered user signs in with a valid username and password | Implemented | `app/Controllers/AuthController.php:43`; `app/Models/Users.php:33`; `app/views/Auth/index.php:72` | Password verification and session creation exist. Active-account enforcement is a separate missing requirement under FR-008/BR-001. |
| FR-002 | Validate entered credentials | Implemented | `app/Controllers/AuthController.php:46`; `app/Controllers/AuthController.php:53`; `app/Models/Users.php:33` | Empty credentials and invalid username/password combinations are rejected. |
| FR-003 | Display an invalid-login error | Implemented | `app/Controllers/AuthController.php:64`; `app/views/Auth/index.php:65`; `public/js/Auth.js:35` | Error is displayed in the automatically opened sign-in dialog; failed-login cooldown is also handled. |
| FR-004 | Restrict functions according to assigned role | Partial | `app/Core/Auth.php:8`; `app/Core/Auth.php:93`; `app/Core/Router.php:70`; `app/api/Users/UsersController.php:27` | Module-level authorization exists, but the alternate user-update path bypasses owner protection. Changed/revoked roles are not reloaded for existing sessions. |
| FR-005 | Allow authenticated users to log out | Implemented | `app/Controllers/AuthController.php:111`; `app/views/Layout/default.php:68` | Session data, cookie, and session are cleared. |

### User management: FR-006–FR-010

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-006 | Administrator creates users | Implemented | `app/api/Users/UsersController.php:27`; `app/Models/Users.php:92`; `public/js/Users.js:70` | Creation and password hashing exist. Staff assignment has the separate FR-009 defect. |
| FR-007 | Administrator updates user information | Implemented | `app/api/Users/UsersController.php:74`; `app/Models/Users.php:61`; `public/js/Users.js:42` | Username, password, and role can be updated. Owner-account authorization must be corrected under FR-004. |
| FR-008 | Administrator deactivates a user | Missing | `app/Models/Users.php:107`; `app/Models/Users.php:33` | Existing operation deletes the row; there is no active/inactive status, deactivate action, or inactive-login rejection. |
| FR-009 | Administrator assigns roles | Partial | `app/views/Users/index.php:52`; `app/api/Users/UsersController.php:11`; `role_access_setup.sql:3` | UI offers Staff, but the API's accepted role list and role migration exclude it. |
| FR-010 | Administrator views registered users | Implemented | `app/api/Users/UsersController.php:20`; `app/Models/Users.php:9`; `public/js/Users.js:132` | A user list and table are implemented. |

### Product management: FR-011–FR-015

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-011 | Authorized users add products | Implemented | `app/api/Products/ProductsController.php:39`; `app/Models/Products.php:104`; `public/js/Products.js:556` | Product/recipe save path exists. Some success/error notification calls reverse their arguments; see additional findings. |
| FR-012 | Authorized users update products | Implemented | `app/api/Products/ProductsController.php:81`; `app/Models/Products.php:110`; `public/js/Products.js:405` | Product and recipe editing exist; notification defect also applies here. |
| FR-013 | Deactivate products no longer offered | Missing | `app/Models/Products.php:246` | Product and ingredient rows are deleted instead of recording a discontinued/inactive state. |
| FR-014 | Display available products | Partial | `app/Models/Products.php:10`; `app/Models/POS.php:11`; `app/Models/Kiosk.php:10` | Lists exist, but list all product rows without an availability state/filter. Kiosk order submission does not check ingredient stock. |
| FR-015 | Mark products available/unavailable | Missing | `app/Models/Products.php:112`; `app/api/Products/ProductsController.php:39`; `app/views/Products/index.php:53` | No availability field, update operation, UI control, or order-time availability enforcement. |

### Orders, payments, and receipts: FR-016–FR-025

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-016 | Cashier creates a customer order | Implemented | `public/js/POS.js:230`; `app/Models/POS.php:255`; `app/Core/Auth.php:11` | Cashier cart creation and backend processing exist; payment requirements remain separate gaps. |
| FR-017 | Add one or more products to an order | Implemented | `public/js/POS.js:230`; `app/Models/POS.php:266` | Multiple products and positive integer quantities are supported. |
| FR-018 | Modify an order before payment | Partial | `public/js/POS.js:376`; `public/js/POS.js:459`; `app/Models/POS.php:217` | Ordinary POS cart edits exist, but retrieved kiosk edits are replaced with original saved items during checkout. |
| FR-019 | Remove an item from an unpaid order | Partial | `public/js/POS.js:362`; `app/Models/POS.php:241` | Ordinary POS removal works in the cart; kiosk removals are discarded at checkout. |
| FR-020 | Automatically calculate the order total | Implemented | `app/Models/POS.php:305`; `app/Models/POS.php:350`; `public/js/POS.js:230` | Server calculates subtotals and total. The kiosk-edit defect can make the displayed cart differ from the saved sale. |
| FR-021 | Record the customer's paid amount | Missing | `app/views/POS/index.php:59`; `app/api/POS/POSController.php:69`; `app/Models/POS.php:403` | No tender field, payment validation, or persisted paid amount. |
| FR-022 | Automatically calculate change | Missing | `app/Models/POS.php:403`; `app/views/POS/index.php:59` | No amount-paid minus total calculation or change display/storage. |
| FR-023 | Save the transaction after successful payment | Partial | `app/Models/POS.php:403`; `app/Models/POS.php:491`; `app/Models/POS.php:540` | Sale, items, and inventory commit atomically, but payment is neither recorded nor validated before completion. |
| FR-024 | Generate a transaction receipt | Partial | `public/js/POS.js:711`; `public/js/POS.js:532`; `app/views/POS/index.php:186` | View Details displays a receipt-like summary with transaction ID, date, cashier, items, and total. Checkout does not present that receipt as part of UC-003's payment flow. The SRS allows displayed receipts; lack of printing/download alone is not a failure. |
| FR-025 | Generate a unique transaction number | Implemented | `app/Models/POS.php:421`; `public/js/POS.js:615`; `klasik_grounds_cafe (1).sql:262`; `klasik_grounds_cafe (1).sql:316` | Sale ID is generated by the database and displayed as the transaction number. Deployed database constraints were not inspected. |

### Inventory: FR-026–FR-030

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-026 | Authorized users add inventory items | Implemented | `app/api/Inventory/InventoryController.php:24`; `app/Models/Inventory.php:61`; `app/views/Inventory/index.php:11` | Inventory creation validates name, positive quantity, and unit. |
| FR-027 | Record newly received stock | Implemented | `app/api/Inventory/InventoryController.php:71`; `app/Models/Inventory.php:78`; `public/js/Inventory.js:218` | Dedicated replenishment adds received quantity. It does not retain a separate stock-receipt/movement journal. |
| FR-028 | Update inventory from transactions | Implemented | `app/Models/POS.php:357`; `app/Models/POS.php:436`; `app/Models/POS.php:465`; `app/Models/POS.php:550` | Recipe-based deduction uses locks, conditional updates, and rollback protection. End-to-end transaction behavior was not run. |
| FR-029 | View current inventory quantities | Implemented | `app/Models/Inventory.php:9`; `app/api/Inventory/InventoryController.php:17`; `public/js/Inventory.js:23` | Current quantity and unit are displayed. |
| FR-030 | Identify items at/below their minimum stock level | Partial | `app/Models/Dashboard.php:135`; `app/Models/Dashboard.php:170`; `klasik_grounds_cafe (1).sql:72` | Boundary comparison correctly uses `<=`, but minimum is hardcoded to 10 for all units/items. SRS section 10.3 defines an item Minimum Stock field; that field is absent from the model/form/schema supplied. If the owner explicitly approves a universal minimum of 10, this can be reclassified as implemented against that clarified policy. |

### Reports: FR-031–FR-035

| ID | Requirement | Assessment | Evidence | Gap or qualification |
| --- | --- | --- | --- | --- |
| FR-031 | Sales transactions for a selected date | Partial | `app/Models/POS.php:77`; `app/api/POS/POSController.php:38`; `app/views/POS/index.php:140` | All-date transaction history exists, but no date selector/filter, selected-day aggregate total, or report print/export as required by AC-005. |
| FR-032 | Weekly sales report | Missing | `app/Models/Dashboard.php:9`; `app/Models/Dashboard.php:54`; `app/views/Dashboard/index.php:7` | Current-week sales minus expenses is a net-profit chart, not a weekly sales report with period selection and transactions. |
| FR-033 | Monthly sales report | Missing | `app/Models/Dashboard.php:108`; `app/Models/DailyExpenses.php:119` | Monthly expense summaries exist; monthly sales reporting does not. |
| FR-034 | Sales by product report | Missing | `app/Models/POS.php:130` | Sale item details are stored, but no aggregate quantity/revenue by product report is implemented. |
| FR-035 | Inventory report | Partial | `app/Models/Dashboard.php:161`; `app/views/Dashboard/index.php:367`; `public/js/Inventory.js:8` | Current inventory and low-stock information exist. UC-010's report-generation and print/export workflow is absent. Loading DataTables export libraries alone does not enable report controls. |

## Related business rules and acceptance criteria

| SRS item | Assessment | Reason |
| --- | --- | --- |
| BR-001: only active users may log in | Missing | No user activation state/check. Deleted accounts' existing sessions are not revalidated against the database. |
| BR-002: assigned-role permissions | Partial | Module authorization exists; owner-edit bypass and stale session roles remain. |
| BR-003: unique completed transaction number | Implemented in source/schema artifacts | Auto-generated sale primary key is used as transaction number. |
| BR-004: completion requires recorded payment | Missing | Checkout can save without payment data. |
| BR-005: payment must cover the order total | Missing | No tender value or insufficient-payment validation. |
| BR-006: automatic customer change | Missing | No change calculation. |
| BR-007: unavailable products cannot enter new orders | Missing | No manual availability state or check; kiosk submission does not check ingredient availability. Stock rejection at checkout does not satisfy preventing a new unavailable-product order. |
| BR-008: inventory must not become negative | Implemented in checkout source | Row locks, guarded subtraction, affected-row checks, and rollback protect deductions. Concurrency acceptance tests remain necessary against the actual database. |
| BR-009: low stock at/below minimum | Partial | Comparison is correct, but only for a global constant of 10; item-specific minimum is missing. |
| BR-010: ordinary users cannot permanently delete completed sales | Supported in source | No completed-sale delete endpoint/model operation was found. Actual database privileges were not audited. |
| AC-001: login and protected access | Partial overall | Login/error paths exist; role enforcement gaps prevent full access-control sign-off. |
| AC-002: create order and proceed to payment | Partial | Cart/products/quantities/totals exist, but no actual payment entry step. |
| AC-003: payment | Not satisfied | Tender entry, insufficient-payment rejection, paid amount, and change are missing. |
| AC-004: inventory update | Supported in source; unverified at runtime | Deduction and negative-stock guards exist; per-item low-stock policy and database acceptance checks remain. |
| AC-005: selected-date sales report | Not satisfied | No selected-date report, daily total, or report export/print workflow. |

## Additional implementation and deployment findings

- **Database dialect mismatch in member checkout:** the connection uses PostgreSQL (`app/Core/Database.php:13`), but loyalty awarding executes `INSERT IGNORE` and `ON DUPLICATE KEY UPDATE` (`app/Models/POS.php:523`, `app/Models/POS.php:530`). This is MySQL syntax in the PostgreSQL execution path. It can prevent a linked loyalty-customer checkout from reaching commit, rolling back the sale and stock updates. This branch was not executed against production. Standardize the database dialect before acceptance testing membership-related sales.
- **Fresh-install schema is incomplete:** checked-in SQL contains no creation of `kiosk_orders` or `kiosk_order_items`; `loyalty_setup.sql:1` assumes the first already exists. Supplied schema/migrations are MySQL-oriented while the current connection is PostgreSQL. This does not prove the live tables are missing; it means the repository alone does not reproduce the current database reliably.
- **Some notifications reverse their arguments:** `msgBox(result, message)` is defined in `public/js/app.js:3`, but calls such as `public/js/Products.js:662` and `public/js/Inventory.js:211` pass the message first. This gives SweetAlert an invalid icon name and an unhelpful title. These are feedback defects alongside otherwise implemented save/replenishment paths.
- **Staff support is inconsistent:** Staff appears in the user form but is excluded by the user API and role migration. The staff role map permits Products/Inventory/Dashboard, but the layout has no matching staff navigation branch (`app/views/Layout/default.php:88`). Agree on the supported role set and apply it consistently.
- **The SRS mentions loyalty membership in scope, but assigns it no FR ID in FR-001–FR-035.** Its presence should not be counted as satisfying a missing reporting/payment requirement. Add separate traceable loyalty requirements if it is part of acceptance.

## Verification performed

- Extracted the DOCX's XML and reconciled all **35 unique** numbered functional requirements with the matrix.
- Inspected PHP routes, authorization, controllers, models, views, JavaScript, and supplied SQL artifacts. Library/plugin files were not treated as implemented business features.
- Ran PHP syntax checks on **51 PHP files**: all passed.
- Ran `php tests/rate_limiter.php`: passed. This supports the existing sign-in cooldown feature; it does not validate unrelated FRs.
- Ran an isolated administrator/owner-update reproduction with the actual authorization/API code and a stub user model. Four checks passed: admin may reach Users/add; add reports success; an existing synthetic owner ID reaches save via add; edit rejects the same owner change with HTTP 403. No database connection or live request was made.
- Did not run checkout, create/delete/deactivate real records, award/redeem points, alter schemas, or modify the SRS. Production credentials and personal data are excluded from this report.

## Prioritized implementation and acceptance plan

1. **Protect account administration:** separate create from update; enforce the same target-account and role-assignment policy in every path; refresh/revoke existing sessions after account or role changes. Verify an admin cannot bypass owner protection through another endpoint.
2. **Complete payment and receipt processing:** add paid amount and server-side insufficient-payment checks; calculate/persist change; commit only after payment validation; present the existing receipt-like transaction summary after checkout. Including tender and change in that summary is recommended for clarity. Verify exact payment, overpayment, and insufficient payment, with no sale/stock update for rejected payment. The SRS permits a displayed receipt; printing is optional for this requirement.
3. **Fix retrieved kiosk order editing:** either persist the cashier's permitted item/quantity changes using server-side price lookup, or clearly prevent unsupported edits. Verify the cart shown immediately before payment exactly matches the saved sale and receipt.
4. **Implement user and product status:** add reversible deactivation and product availability fields/actions; retain historical records; reject inactive accounts and unavailable products on the server. Verify direct API requests cannot bypass those checks.
5. **Finish reports:** add daily/weekly/monthly sales, product sales, and inventory reports; expose date/period selection, transaction rows, appropriate totals, and print/CSV export. Test date boundaries, empty periods, and reconciliation with recorded sales.
6. **Add inventory minimums:** store a minimum quantity per item and use `quantity <= minimum` consistently. Verify an item exactly at its threshold, below it, and above it, across different units.
7. **Standardize database and role configuration:** supply complete PostgreSQL-compatible migrations (or explicitly migrate the application to its chosen database); correct loyalty upserts, Staff support, and notification calls. Validate a fresh isolated installation.
8. **Run acceptance tests in an isolated test database:** cover AC-001–AC-005, unavailable/inactive records, concurrent checkout of shared stock, and duplicate kiosk checkout. Record results against every FR before declaring full SRS compliance.

Until these gaps are resolved and acceptance tests pass, this audit is a **gap assessment**, not an SRS compliance sign-off.
