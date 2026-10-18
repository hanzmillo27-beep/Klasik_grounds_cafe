<?php
require __DIR__ . '/product_fixture.php';

$products = new Models\Products();
check($products->save(productPayload(['product_image' => 'products/active.jpg'])), 'Could not create an active product.');
check($products->save(productPayload(['product_name' => 'Discontinued', 'product_image' => 'products/inactive.jpg'])),
    'Could not create the discontinued product.');
check($products->save(productPayload(['product_name' => 'Water on the house', 'product_price' => '0'])),
    'Could not create a zero-price product.');
check($products->setActive(2, false), 'Could not deactivate a product.');

$pos = new Models\POS();
$kiosk = new Models\Kiosk();
foreach (['POS' => $pos, 'Kiosk' => $kiosk] as $name => $model) {
    $listed = $model->products();
    $ids = array_map(function ($product) { return (int) $product->product_id; }, $listed);
    check($ids === [1, 3], $name . ' must list active products in name order and exclude inactive products.');
    foreach ($listed as $product) {
        foreach (['product_id', 'product_name', 'category_name', 'product_price', 'product_image', 'product_image_url'] as $field) {
            check(property_exists($product, $field), $name . ' omitted product field ' . $field . '.');
        }
        check($product->category_name === 'Coffee', $name . ' omitted the product category.');
    }
    check($listed[0]->product_name === 'Americano' && abs((float) $listed[0]->product_price - 125.50) < 0.00001,
        $name . ' returned incorrect product details.');
    check($listed[0]->product_image === 'products/active.jpg', $name . ' omitted the stored image path.');
    if (SUPABASE_URL === '') {
        check($listed[0]->product_image_url === null, $name . ' must handle image storage without a configured URL.');
    } else {
        check(is_string($listed[0]->product_image_url)
            && str_starts_with($listed[0]->product_image_url, rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/')
            && str_ends_with($listed[0]->product_image_url, '/products/active.jpg'),
            $name . ' must return a usable public image URL.');
    }
    check((float) $listed[1]->product_price === 0.0, $name . ' must include active products whose price is zero.');
    check($listed[1]->product_image === null && $listed[1]->product_image_url === null,
        $name . ' must allow products without images.');
}

check($pos->product(2) === null, 'The POS product lookup must reject an inactive product.');
check($pos->product(99999) === null, 'The POS product lookup must return null for a missing product.');
$active = $pos->product(1);
check($active && count($active->ingredients) === 1 && $active->ingredients[0]->inv_name === 'Beans',
    'The POS product lookup must include an active product and its recipe.');
$management = $products->find();
check(count($management) === 3, 'The management list must retain inactive products.');
$inactive = array_values(array_filter($management, function ($product) { return (int) $product->product_id === 2; }));
check(count($inactive) === 1 && $inactive[0]->is_active === false, 'Management must show the inactive status.');

class TestPOSProductListController extends Api\POS\POSController {
    public function json($data): void { throw new JsonResponse($data); }
}
class TestKioskProductListController extends Api\Kiosk\KioskController {
    public function json($data): void { throw new JsonResponse($data); }
}
foreach ([new TestPOSProductListController(), new TestKioskProductListController()] as $controller) {
    try {
        $controller->products();
        throw new LogicException('The product list API must return JSON.');
    } catch (JsonResponse $response) {
        $ids = array_map(function ($product) { return (int) $product->product_id; }, $response->data['data']);
        check($ids === [1, 3], 'The product list API must return only active products.');
    }
}

check($products->setActive(2, true) && count($pos->products()) === 3 && count($kiosk->products()) === 3
    && $pos->product(2) !== null, 'Reactivated products must return to the lists and POS lookup.');
foreach ([1, 2, 3] as $id) check($products->setActive($id, false), 'Could not deactivate the remaining products.');
check($pos->products() === [] && $kiosk->products() === [], 'Lists must be empty when no active products remain.');
check(count($products->find()) === 3, 'Filtering order lists must never remove management records.');

echo "FR-014 passed: active product lists and lookups, zero-price products, image fields, retained management records.\n";
