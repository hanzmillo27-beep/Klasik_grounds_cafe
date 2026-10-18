<?php
require_once __DIR__ . '/product_fixture.php';

$db->exec('CREATE TABLE sales (
    sale_id ' . $identity . ', user_id INTEGER NOT NULL REFERENCES users(id),
    customer_name VARCHAR(100), total_amount NUMERIC(10,2) NOT NULL, amount_paid NUMERIC(10,2),
    change_amount NUMERIC(10,2),
    sale_date TIMESTAMP NOT NULL
)');
$db->exec('CREATE TABLE sale_items (
    sale_item_id ' . $identity . ', sale_id INTEGER NOT NULL REFERENCES sales(sale_id),
    product_id INTEGER NOT NULL REFERENCES products(product_id), product_name VARCHAR(150),
    quantity INTEGER NOT NULL, unit_price NUMERIC(10,2) NOT NULL, subtotal NUMERIC(10,2) NOT NULL
)');
$db->exec('CREATE TABLE kiosk_orders (
    kiosk_order_id ' . $identity . ', reference_code VARCHAR(30) NOT NULL UNIQUE,
    customer_name VARCHAR(100), total_amount NUMERIC(10,2) NOT NULL,
    customer_user_id INTEGER REFERENCES users(id), status VARCHAR(20) NOT NULL DEFAULT \'pending\',
    claimed_by INTEGER REFERENCES users(id), sale_id INTEGER REFERENCES sales(sale_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, claimed_at TIMESTAMP, completed_at TIMESTAMP
)');
$db->exec('CREATE TABLE kiosk_order_items (
    kiosk_item_id ' . $identity . ', kiosk_order_id INTEGER NOT NULL REFERENCES kiosk_orders(kiosk_order_id),
    product_id INTEGER NOT NULL REFERENCES products(product_id), product_name VARCHAR(150) NOT NULL,
    quantity INTEGER NOT NULL, unit_price NUMERIC(10,2) NOT NULL, subtotal NUMERIC(10,2) NOT NULL
)');
$db->exec('CREATE TABLE loyal (
    user_id INTEGER PRIMARY KEY REFERENCES users(id), loyalty_points INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE loyalty_earnings (
    earning_id ' . $identity . ', user_id INTEGER NOT NULL REFERENCES users(id),
    sale_id INTEGER NOT NULL REFERENCES sales(sale_id), point_date DATE NOT NULL,
    UNIQUE (user_id, point_date)
)');
check((new Models\Products())->save(productPayload()), 'Could not create the POS product.');
check((new Models\Users())->save(['username' => 'test-cashier', 'password' => 'password', 'role' => 'cashier']),
    'Could not create the cashier account.');
$_SESSION['user'] = ['id' => 2, 'username' => 'test-cashier', 'role' => 'cashier'];

class TestPOSController extends Api\POS\POSController {
    public function json($data): void { throw new JsonResponse($data); }
}
