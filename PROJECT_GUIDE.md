# Klasik Grounds Cafe project guide

This document explains the application code in this repository. Third-party files under `public/plugins/`, `public/dist/`, and `vendor/` are libraries or packaged assets; they are not application logic and should not be read line by line. The important project code is under `app/`, `public/js/`, `public/css/`, the root deployment files, and the SQL scripts.

## 1. What the application is

This is a small PHP MVC-style cafe management system with:

- a customer kiosk for browsing products and submitting orders;
- a cashier POS for loading kiosk orders, taking payment, deducting inventory, and recording sales;
- owner and admin screens for products, inventory, categories, expenses, and users;
- loyalty points and redemptions;
- a Supabase PostgreSQL database;
- Supabase Storage for product photos;
- Render/Docker deployment from GitHub.

The browser talks to PHP in two ways:

1. Page routes return a PHP view, for example `/Products` or `/POS`.
2. API routes return JSON, for example `/api/Products/all` or `/api/POS/checkout`.

## 2. Request lifecycle

`public/index.php` is the single entry point. It loads configuration, registers the autoloader, and gives the request to `Core\\Router`.

The router removes the configured base URL, splits the path into parts, converts the path to a controller class, checks authorization, and calls the requested method.

For a page such as `/Products`:

1. `public/index.php` loads `app/config/init.php` and `app/autoload.php`.
2. `Router` maps `Products` to `Controllers\\ProductsController`.
3. `Auth::authorizeRoute()` checks the current session and role.
4. `ProductsController::index()` calls `BaseController::view()`.
5. The controller view is rendered inside `Layout/default.php`.
6. The layout loads `public/js/Products.js`.
7. That JavaScript calls `/api/Products/...` to load and change data.

For an API request such as `/api/Products/all`, the router maps it to `Api\\Products\\ProductsController::all()`. That controller calls a model and returns JSON through `BaseController::json()`.

## 3. Root and deployment files

### `public/index.php`

- Loads `app/config/init.php`, which starts the session and defines constants.
- Loads `app/autoload.php`, which maps namespaces to directories.
- Instantiates `Core\\Router`.
- Calls `dispatch()`. There is no separate PHP file for each URL.

### `.htaccess`

- Enables Apache rewrite rules.
- Sends existing files and directories toward `public/`.
- Sends all other requests to `public/index.php`, allowing the PHP router to handle clean URLs.

### `apache-vhost.conf`

- Sets Apache's document root to `/var/www/html/public` in Docker.
- Allows rewrite rules in that directory.
- Sends Apache logs to the container output streams.

### `Dockerfile`

- Starts from PHP 8.3 with Apache.
- Installs PostgreSQL support through `pdo_pgsql`.
- Enables Apache `rewrite` and `headers` modules.
- Changes Apache to listen on Render's port `10000`.
- Copies the project into `/var/www/html`.
- Installs the custom virtual host file.
- Gives Apache ownership of the application files.

### `CNAME`

- Contains the custom domain name used by GitHub Pages or the hosting configuration. It does not control the Render container itself.

### `.dockerignore`

- Prevents `.git`, editor files, logs, `vendor`, and `node_modules` from being sent into the Docker build context.

### `.env.example`

- Documents the environment variables used for the app URL and Supabase Storage.
- It contains placeholders only. Real values belong in Render's environment settings.

## 4. Configuration and framework core

### `app/config/init.php`

- Starts the PHP session.
- Defines `BASE_PATH` and `PUBLIC_PATH`.
- Uses `APP_BASE_URL` when Render supplies it.
- Otherwise builds the base URL from the request host, forwarded HTTPS header, and script path.
- Defines the Supabase PostgreSQL connection constants.
- Defines `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, and `SUPABASE_STORAGE_BUCKET` from environment variables.
- Sets the default timezone to `Asia/Manila`.

The commented local database block is an old local-development option. `DB_CHARSET` is defined but PDO PostgreSQL does not use it. Database credentials should be moved entirely to Render variables; a database password has previously been present in source and should be rotated.

### `app/autoload.php`

- Registers a PSR-4-style callback with `spl_autoload_register()`.
- Maps `Core\\` to `app/Core`, `Models\\` to `app/Models`, `Controllers\\` to `app/Controllers`, and `Api\\` to `app/api`.
- Converts namespace separators to filesystem separators.
- Includes the matching PHP file when it exists.

### `app/Core/Router.php`

- Reads the path portion of the request URL.
- Removes `BASE_URL` from that path.
- Splits the remaining path into segments.
- Uses the first segment `api` to choose an API controller; otherwise it chooses a page controller.
- Builds names such as `Api\\Products\\ProductsController` and `Controllers\\ProductsController`.
- Rejects missing controllers and methods with HTTP 404.
- Calls `Auth::authorizeRoute()` before invoking the action.
- Passes URL parameters after the action into the method.

Examples:

```text
/Products                  -> Controllers\\ProductsController::index()
/Products/edit/4           -> Controllers\\ProductsController::index? (page routing depends on folder structure)
/api/Products/edit/4       -> Api\\Products\\ProductsController::edit(4)
/api/POS/checkout           -> Api\\POS\\POSController::checkout()
```

### `app/Core/Database.php`

- Builds a PostgreSQL DSN from the database constants.
- Forces SSL with `sslmode=require`.
- Creates a PDO connection with exceptions enabled.
- Sets the default fetch mode to objects.
- Stops with an error message if the connection fails.

### `app/Core/Model.php`

- Stores a protected PDO connection in `$db`.
- Every model inherits this constructor and receives the same database connection behavior.

### `app/Core/BaseController.php`

- `view()` finds a view file, exposes the supplied data as local variables, renders the view into a buffer, and inserts it into the selected layout.
- `view(..., 'none')` renders without a layout.
- `json()` sets the JSON content type, encodes the value, prints it, and exits.
- `redirect()` sends a `Location` header and exits.

### `app/Core/Auth.php`

- `check()` tests whether `$_SESSION['user']` exists.
- `user()` returns the logged-in user array.
- `requireLogin()` redirects anonymous users to `/Auth`.
- `requireAdmin()` allows only owner/admin roles.
- `requireRole()` checks an explicit list of roles.
- `authorizeRoute()` is the central route guard.
- Public routes include login, logout, first registration, and guest kiosk APIs.
- The role map gives owner full access, admin user management, cashier POS access, user kiosk access, and loyal customers kiosk plus loyalty access.
- Kiosk order claiming is treated as a POS permission even though its URL starts with `Kiosk`.
- Unauthorized page requests become redirects or `Forbidden`; unauthorized API requests become JSON errors.

### `app/Core/SupabaseStorage.php`

- Limits uploads to 5 MB.
- Allows only JPEG, PNG, and WebP after inspecting the actual image contents with `getimagesize()`.
- Rejects missing configuration, failed temporary files, and failed HTTP responses.
- Generates a random path under `products/`.
- Sends the file to Supabase Storage's REST endpoint using the server-only key.
- Returns the Storage object path, not the file contents.
- `publicUrl()` converts a saved path into a public bucket URL for browser display.

## 5. Page controllers

The controllers under `app/Controllers/` mostly prepare a view and enforce access. They do not contain most SQL; models do that.

### `AuthController.php`

- Creates a `Users` model.
- `index()` redirects to registration if there are no users, otherwise renders the public login/landing view with the `auth` layout.
- `login()` reads username and password, validates non-empty values, looks up the hashed password, verifies it, regenerates the session ID, stores `id`, `username`, and `role`, then redirects by role.
- `logout()` clears the session, expires the session cookie, destroys the session, and redirects to login.

### `DashboardController.php`

- Requires login.
- Loads weekly profit, expense summary, inventory summary, recent expenses, and inventory through `Models\\Dashboard`.
- Renders `Dashboard/index.php` in the default layout.

### `ProductsController.php`, `InventoryController.php`, `CategoriesController.php`, `DailyExpensesController.php`, and `POSController.php`

- Each `index()` action requires login and renders its matching view.
- The role check is performed by the router before the action is called.

### `KioskController.php`

- Reads the current session when one exists.
- Calculates whether the user is a loyal customer.
- Renders the kiosk view. The kiosk can also be used anonymously.

### `LoyaltyController.php`

- Requires login and the loyalty role map allows loyal customers.
- Renders the loyalty dashboard.

### `UsersController.php`

- Creates a `Users` model.
- `index()` requires login and shows user management.
- `register()` is the public first-account/customer registration flow; it validates username, password length, confirmation, and uniqueness, then stores a hashed password.

## 6. API controllers

API controllers accept `$_POST` or JSON-decoded request data, validate it, call a model, and return a JSON status.

### `app/api/Products/ProductsController.php`

- `all()` returns the product list.
- `categories()` returns category choices.
- `inventory()` returns inventory choices used for ingredient autocomplete.
- `add()` validates name, category, and price, optionally uploads `product_image`, then calls `Products::save()`.
- `edit($id)` returns one product for GET and updates it for POST.
- `delete($id)` removes the product and its ingredient rows.
- The hidden `product_id` in the product form is important: without it, editing is interpreted as inserting a new product.

### `app/api/Inventory/InventoryController.php`

- Lists inventory.
- Adds or updates an inventory row after validating name, amount, and unit.
- `restock($id)` accepts only POST and requires a positive finite amount.
- Reads, updates, and deletes a row by ID.

### `app/api/Categories/CategoriesController.php`

- Lists categories.
- Adds or updates a category name.
- Deletes a category after checking an ID.

### `app/api/DailyExpenses/DailyExpensesController.php`

- Lists expenses.
- Validates expense name, positive numeric amount, and a real `YYYY-MM-DD` date.
- Adds, updates, and deletes expenses.
- Exposes daily, weekly, and monthly totals.

### `app/api/Users/UsersController.php`

- Lists users without returning password hashes.
- Adds or updates users and validates the role against the allowed role list.
- Requires a password for new users but keeps the existing password when an edit leaves it blank.
- Prevents non-owners from editing or deleting owner accounts.

### `app/api/Kiosk/KioskController.php`

- Returns customer-facing products.
- Accepts a cart and submits a pending kiosk order.
- Restricts `claim($reference)` to cashier access.
- Returns order references and structured API errors.

### `app/api/POS/POSController.php`

- Returns products and one product with ingredients.
- Lists transactions and transaction details.
- Accepts checkout data and returns loyalty results when a point is earned.

### `app/api/Loyalty/LoyaltyController.php`

- Uses the logged-in user ID.
- `summary()` returns current points, progress, and activity.
- `redeem()` accepts POST only and returns a reward code when ten points are available.

## 7. Models and database behavior

All models extend `Core\\Model`, so they use PDO prepared statements and the configured Supabase database.

### `app/Models/Users.php`

- Finds users for management without passwords.
- Finds one user by ID.
- Finds a login user by username and returns its password hash.
- Checks username uniqueness.
- `save()` updates username/role, optionally updates the password hash, or inserts a new user.
- `delete()` removes a user.

### `app/Models/Products.php`

- `find()` joins categories and returns product rows plus public image URLs.
- `findByID()` returns product details and its ingredient list.
- `categories()` and `inventory()` provide form choices.
- `save()` runs in a transaction, updates or inserts the product, replaces all ingredient rows, and resolves ingredient names to inventory IDs.
- Product updates use `COALESCE(?, product_image)` so an edit without a new upload retains the existing photo.
- `delete()` removes ingredient links before deleting the product.

### `app/Models/Inventory.php`

- Lists inventory and finds one item.
- Adds or updates name, amount, and unit.
- `restock()` increments amount atomically.
- Deletes an inventory row.

### `app/Models/Categories.php`

- Lists categories alphabetically.
- Finds one category.
- Adds or updates a category.
- Deletes a category.

### `app/Models/DailyExpenses.php`

- Lists expenses by date.
- Finds one expense.
- Adds or updates an expense.
- Deletes an expense.
- Uses `COALESCE(SUM(amount), 0)` for daily, weekly, and monthly totals.

### `app/Models/Dashboard.php`

- Reads sales and expenses and combines them by calendar date for the weekly net-profit chart.
- Calculates today, current-week, and current-month expense totals.
- Calculates inventory count/value summaries.
- Returns recent expenses and inventory data for dashboard cards/tables.

### `app/Models/Kiosk.php`

- Lists products and image URLs for the customer kiosk.
- Validates cart item IDs and quantities server-side instead of trusting browser prices.
- Locks product rows while creating a kiosk order.
- Inserts `kiosk_orders` and `kiosk_order_items` in one transaction.
- Finds an order by reference with its items.
- `claim()` locks a pending order and marks it claimed by a cashier.
- `cashierCanAccess()` verifies the caller's role.

### `app/Models/Loyalty.php`

- Uses ten points as the reward threshold.
- `summary()` reads the point balance, redemptions, earnings, and activity.
- `redeem()` locks the loyalty row, checks the threshold, subtracts ten points, creates a redemption code, and commits atomically.

### `app/Models/POS.php`

- Lists products for the cashier, including image URLs.
- Returns a product and its ingredients.
- Lists sales and loads one sale with sale items.
- `checkout()` is the largest business transaction in the app.
- It validates the logged-in cashier, optionally loads a kiosk order, locks products and ingredients, verifies prices and stock, inserts a sale and sale items, deducts inventory, completes a kiosk order, and awards at most one loyalty point per day.
- It rolls back all changes if any part fails.

## 8. Views

### `app/views/Layout/auth.php`

- Outputs the HTML document for the public login route.
- Loads fonts, icons, AdminLTE CSS, and the cafe stylesheet.
- Uses a cache-busting version based on the stylesheet modification time.
- Places the controller-rendered content inside `body.kg-landing-page`.

### `app/views/Layout/default.php`

- Creates the authenticated AdminLTE shell.
- Reads the session user and role.
- Builds the top navigation and role-specific sidebar.
- Inserts the selected page content into the content wrapper.
- Loads jQuery, Bootstrap, AdminLTE, DataTables, charts, shared JavaScript, and the page-specific JavaScript file.

### `app/views/Auth/index.php`

- Builds the public dark landing/login page.
- Shows the round cafe logo, hero background, order link, sign-in anchor, values strip, sign-in form, address, Instagram, Facebook, and phone footer.
- Displays one-time `$_SESSION['success']` and `$_SESSION['error']` messages.
- Posts credentials to `/Auth/login`.

### `app/views/Dashboard/index.php`

- Displays the weekly net-profit chart canvas and serialized PHP chart data.
- Displays expense summary cards.
- Displays inventory and recent-expense tables.

### `app/views/Products/index.php`

- Defines the products DataTable.
- Defines the add/edit modal.
- Includes the hidden `product_id` needed for updates.
- Includes product image upload and preview fields.
- Includes category, price, and dynamic ingredient fields.

### `app/views/Inventory/index.php`

- Defines the inventory table.
- Provides add/edit and restock modals.

### `app/views/Categories/index.php`

- Defines the category table and add/edit modal.

### `app/views/DailyExpenses/index.php`

- Defines expense table, total cards, and add/edit form.

### `app/views/Users/index.php`

- Defines user table and add/edit modal.
- Provides the role selector.

### `app/views/Users/register.php`

- Provides the public registration form.
- Prints session errors and posts to `/Users/register`.

### `app/views/POS/index.php`

- Defines the cashier product search, cart, checkout form, kiosk-order loader, transaction table, and transaction detail modal.

### `app/views/Kiosk/index.php`

- Defines customer product cards, search, cart, customer name, order total, and order confirmation panel.
- Shows different instructions for guests, ordinary signed-in users, and loyal customers.

### `app/views/Loyalty/index.php`

- Defines the points balance, progress indicator, activity list, and redeem button.

## 9. Browser JavaScript

### `public/js/app.js`

- Calculates `base_url` from the script URL so JavaScript requests work in a subdirectory and on Render.
- `msgBox()` shows SweetAlert toast messages.
- `isDelete()` confirms destructive actions.
- `handleAjaxError()` extracts a JSON error message and displays it.

### `Categories.js` and `Users.js`

- Use a small module object containing selectors and initialization.
- Bind new, edit, delete, submit, and modal-reset events.
- Use DataTables to load records.
- Use `FormData` for add/update requests.
- Choose `/add` or `/edit/{id}` based on the hidden ID field.

### `Products.js`

- Loads categories and inventory autocomplete data.
- Creates the products DataTable and displays image thumbnails.
- Adds/removes ingredient rows and searches inventory names.
- Loads an existing product into the edit form.
- Shows a selected or current image preview.
- Validates ingredient names and positive quantities in the browser.
- Sends the complete product form as `FormData`, including the optional file.

### `Inventory.js`

- Loads the inventory DataTable.
- Opens the edit and restock modals.
- Validates positive restock amounts.
- Sends add/update/restock/delete requests and reloads the table.

### `DailyExpenses.js`

- Loads expenses and summary totals.
- Calculates today and Monday-to-Sunday week dates in the browser.
- Formats currency and dates.
- Opens edit/delete actions and submits `FormData`.

### `Dashboard.js`

- Reads `window.weeklyNetProfitData` generated by the PHP view.
- Builds the Chart.js line chart and formats peso values/tooltips.

### `Kiosk.js`

- Loads customer products.
- Renders product cards and the cart.
- Supports search, add, increase, decrease, remove, and clear.
- Sends a JSON-encoded cart in a `FormData` request to `/api/Kiosk/submit`.
- Shows the order reference after successful submission.

### `POS.js`

- Loads products and transactions.
- Keeps the current cart, customer name, and kiosk order ID in localStorage.
- Adds and changes cart quantities.
- Loads kiosk orders by reference.
- Sends checkout data to `/api/POS/checkout`.
- Renders transaction history and transaction details.
- Shows loyalty-point results after checkout.

### `Loyalty.js`

- Loads the loyalty summary.
- Renders balance, progress, and activity.
- Posts redemption requests and shows the generated reward code.

## 10. CSS and static assets

### `public/css/cafe-theme.css`

- Defines cafe color variables.
- Styles the authenticated AdminLTE shell, sidebar, buttons, cards, tables, and forms.
- Defines the dark public landing page classes beginning with `.kg-`.
- Uses `public/dist/img/background.png` as the hero background and `logo.jpg` for round branding.
- Includes responsive breakpoints for tablet and phone widths.

### `public/dist/img/`

- Contains the cafe logo, background, product placeholders, and other images.
- `background.png` must be tracked in GitHub because Render builds from the repository.

### `public/plugins/` and `public/dist/`

- Contain AdminLTE, Bootstrap, jQuery, DataTables, Chart.js, Font Awesome, Dropzone, PDFMake, and other packaged assets.
- They are dependencies/assets, not custom business logic.

## 11. SQL and setup files

### `klasik_grounds_cafe (1).sql`

- The main seed/schema export.
- Defines categories, daily expenses, inventory, loyalty, products, product ingredients, sales, sale items, and users.
- Contains sample inserts and key/constraint statements.
- Its syntax is largely MySQL-style and should be reviewed before using it directly in Supabase PostgreSQL.

### `sample_db.sql`

- Older sample MVC database schema for courses, students, and users.
- It is not the cafe production schema.

### `loyalty_setup.sql`

- Adds kiosk-order loyalty support and creates loyalty earnings/redemption tables.

### `role_access_setup.sql`

- Adds or adjusts the role column used by `Core\\Auth`.

### `product_images_migration.sql`

- Adds nullable `public.products.product_image`.
- Creates or makes public the `product-images` Supabase Storage bucket.

### `PRODUCT_IMAGES_SETUP.md`

- Documents the bucket, Render variables, migration, accepted image formats, and 5 MB limit.

### `Supabase.txt`

- Stores a short Supabase project reference note. It is not application code.

## 12. Important current risks and maintenance points

1. Database credentials must not remain hardcoded in `app/config/init.php`. Rotate the password and configure database variables in Render.
2. Never place the Supabase service/secret key in JavaScript, GitHub, or chat. It bypasses Row Level Security.
3. The app has no visible CSRF protection on state-changing forms.
4. Product images use a public bucket, so anyone with an object URL can view them.
5. The main SQL export contains MySQL-style syntax while the application connects to PostgreSQL. Check every migration before running it in Supabase.
6. Review the loyalty SQL in `POS.php` if PostgreSQL reports syntax errors around `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE`; those are MySQL constructs and normally need PostgreSQL equivalents.
7. Browser validation improves usability, but every important validation must remain server-side, as it is for products, inventory, expenses, kiosk orders, and checkout.

## 13. The most common end-to-end flows

### Login

`Auth/index.php` form -> `AuthController::login()` -> `Users::findByUsername()` -> `password_verify()` -> session user -> role redirect.

### Product update

Products page -> edit API GET -> hidden `product_id` is populated -> `FormData` POST -> optional Supabase image upload -> `Products::save()` update branch -> ingredients replaced -> table reload.

### Customer order

Kiosk page -> `Kiosk.js` cart -> `/api/Kiosk/submit` -> `Kiosk::submit()` transaction -> reference number -> cashier loads reference in POS.

### Checkout

POS cart or kiosk order -> `/api/POS/checkout` -> locked product/ingredient rows -> stock checks -> sale and sale items -> inventory deduction -> kiosk completion -> optional loyalty earning -> JSON result.

### Loyalty redemption

Loyalty page -> `/api/Loyalty/summary` -> points/activity display -> POST `/api/Loyalty/redeem` -> locked balance check -> ten-point deduction -> redemption code.
