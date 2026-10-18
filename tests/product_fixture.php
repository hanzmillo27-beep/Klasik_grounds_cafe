<?php
require_once __DIR__ . '/bootstrap.php';

$db = Core\Database::$connection;
$identity = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
    ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$activeColumn = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
    ? 'BOOLEAN NOT NULL DEFAULT TRUE' : 'INTEGER NOT NULL DEFAULT 1';
$db->exec('CREATE TABLE categories (
    category_id ' . $identity . ', category_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE inventory (
    inv_id ' . $identity . ', inv_name VARCHAR(150) NOT NULL,
    amount NUMERIC(12,3) NOT NULL, unit VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE products (
    product_id ' . $identity . ', product_name VARCHAR(150) NOT NULL,
    category_id INTEGER NOT NULL REFERENCES categories(category_id),
    product_price NUMERIC(10,2) NOT NULL, product_image TEXT,
    is_active ' . $activeColumn . ',
    is_available ' . $activeColumn . ',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$db->exec('CREATE TABLE product_ingredients (
    product_id INTEGER NOT NULL REFERENCES products(product_id),
    inv_id INTEGER NOT NULL REFERENCES inventory(inv_id),
    quantity_required NUMERIC(12,3) NOT NULL,
    PRIMARY KEY (product_id, inv_id)
)');
$db->exec("INSERT INTO categories (category_name) VALUES ('Coffee')");
$db->exec("INSERT INTO inventory (inv_name, amount, unit) VALUES ('Beans', 100, 'g')");
(new Models\Users())->save(['username' => 'test-owner', 'password' => 'password', 'role' => 'owner']);
$_SESSION['user'] = ['id' => 1, 'username' => 'test-owner', 'role' => 'owner'];

class TestProductsController extends Api\Products\ProductsController {
    public function json($data): void { throw new JsonResponse($data); }
}

function productPayload(array $overrides = []): array {
    return array_replace([
        'product_name' => 'Americano',
        'category_id' => '1',
        'product_price' => '125.50',
        'ingredient_name' => ['Beans'],
        'ingredient_quantity' => ['18.250'],
    ], $overrides);
}
