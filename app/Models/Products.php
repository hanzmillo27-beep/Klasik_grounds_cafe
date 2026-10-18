<?php

namespace Models;

use Core\Model;
use Core\SupabaseStorage;

class Products extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT
                p.product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                p.product_price,
                p.product_image,
                p.is_active,
                p.created_at
             FROM products p
             LEFT JOIN categories c
                ON c.category_id = p.category_id
             ORDER BY p.product_name'
        );
        $products = $stmt->fetchAll();
        foreach ($products as $product) {
            $product->is_active = Users::isActive($product);
            $product->product_image_url = SupabaseStorage::publicUrl($product->product_image ?? null);
        }
        return $products;
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.product_id,
                p.product_name,
                p.category_id,
                p.product_price,
                p.product_image,
                p.is_active,
                p.created_at
             FROM products p
             WHERE p.product_id = ?'
        );

        $stmt->execute([$id]);

        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }
        $product->is_active = Users::isActive($product);
        $product->product_image_url = SupabaseStorage::publicUrl($product->product_image ?? null);

        $stmt = $this->db->prepare(
            'SELECT
                pi.inv_id,
                i.inv_name,
                pi.quantity_required,
                i.unit
             FROM product_ingredients pi
             INNER JOIN inventory i
                ON i.inv_id = pi.inv_id
             WHERE pi.product_id = ?
             ORDER BY i.inv_name'
        );

        $stmt->execute([$id]);

        $product->ingredients = $stmt->fetchAll();

        return $product;
    }

    public function categories()
    {
        $stmt = $this->db->query(
            'SELECT
                category_id,
                category_name
             FROM categories
             ORDER BY category_name'
        );

        return $stmt->fetchAll();
    }

    public function inventory()
    {
        $stmt = $this->db->query(
            'SELECT
                inv_id,
                inv_name,
                amount,
                unit
             FROM inventory
             ORDER BY inv_name'
        );

        return $stmt->fetchAll();
    }

    public function save($data)
    {
        if ($this->validationError($data) !== null) return false;
        $data['product_name'] = trim($data['product_name']);
        $data['product_price'] = round((float) $data['product_price'], 2);
        try {

            $this->db->beginTransaction();

            if (!empty($data['product_id'])) {

                $stmt = $this->db->prepare(
                    'UPDATE products
                     SET product_name = ?,
                         category_id = ?,
                         product_price = ?,
                         product_image = COALESCE(?, product_image)
                     WHERE product_id = ?'
                );

                $stmt->execute([
                    $data['product_name'],
                    $data['category_id'],
                    $data['product_price'],
                    $data['product_image'] ?? null,
                    $data['product_id']
                ]);

                $productId = $data['product_id'];
                if ($stmt->rowCount() !== 1) throw new \RuntimeException('Product not found.');

                $stmt = $this->db->prepare(
                    'DELETE FROM product_ingredients
                     WHERE product_id = ?'
                );

                $stmt->execute([
                    $productId
                ]);

            } else {

                $productInsertSql =
                    'INSERT INTO products
                    (
                        product_name,
                        category_id,
                        product_price,
                        product_image,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?)';
                $stmt = $this->db->prepare($productInsertSql);

                $stmt->execute([
                    $data['product_name'],
                    $data['category_id'],
                    $data['product_price'],
                    $data['product_image'] ?? null,
                    date('Y-m-d H:i:s')
                ]);

                $productId = $this->db->lastInsertId();
            }

            if (
                isset($data['ingredient_name']) &&
                is_array($data['ingredient_name'])
            ) {

                $findInventory = $this->db->prepare(
                    'SELECT inv_id
                     FROM inventory
                     WHERE LOWER(inv_name) = LOWER(?)
                     LIMIT 1'
                );

                $insertIngredient = $this->db->prepare(
                    'INSERT INTO product_ingredients
                    (
                        product_id,
                        inv_id,
                        quantity_required
                    )
                    VALUES (?, ?, ?)'
                );

                foreach (
                    $data['ingredient_name']
                    as $key => $ingredientName
                ) {

                    $ingredientName = trim($ingredientName);

                    if ($ingredientName === '') {
                        throw new \InvalidArgumentException('Ingredient name is required.');
                    }

                    $quantity =
                        $data['ingredient_quantity'][$key]
                        ?? 0;

                    if (
                        !is_numeric($quantity) ||
                        $quantity <= 0
                    ) {
                        throw new \InvalidArgumentException('Ingredient quantity must be positive.');
                    }

                    $findInventory->execute([
                        $ingredientName
                    ]);

                    $inventoryItem =
                        $findInventory->fetch();

                    if (!$inventoryItem) {

                        throw new \Exception(
                            'Inventory item not found: ' .
                            $ingredientName
                        );
                    }

                    $insertIngredient->execute([
                        $productId,
                        $inventoryItem->inv_id,
                        $quantity
                    ]);
                }
            }

            $this->db->commit();

            return true;

        } catch (\Exception $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function validationError(array $data): ?string
    {
        if (!is_string($data['product_name'] ?? null) || trim($data['product_name']) === '' || strlen($data['product_name']) > 150) return 'Enter a product name of up to 150 characters.';
        $price = $data['product_price'] ?? null;
        if (!is_numeric($price) || !is_finite((float) $price) || $price < 0 || $price > 99999999.99) return 'Enter a valid nonnegative product price.';
        $category = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$category || $category < 1) return 'Choose an existing category.';
        $stmt = $this->db->prepare('SELECT category_id FROM categories WHERE category_id = ?');
        $stmt->execute([$category]);
        if (!$stmt->fetch()) return 'Choose an existing category.';
        $names = $data['ingredient_name'] ?? [];
        $quantities = $data['ingredient_quantity'] ?? [];
        if (!is_array($names) || !is_array($quantities)) return 'Enter valid ingredient rows.';
        $seen = [];
        foreach ($names as $index=>$name) {
            if (!is_string($name) || trim($name) === '') return 'Choose an inventory item for each ingredient.';
            $quantity = $quantities[$index] ?? null;
            if (!is_numeric($quantity) || !is_finite((float) $quantity) || $quantity <= 0) return 'Ingredient quantities must be positive.';
            $name = strtolower(trim($name));
            if (isset($seen[$name])) return 'Use each ingredient only once.';
            $seen[$name] = true;
        }
        return null;
    }

    public function setActive($id, bool $active): bool
    {
        $stmt = $this->db->prepare('UPDATE products SET is_active = ? WHERE product_id = ?');
        $stmt->bindValue(1, $active, \PDO::PARAM_BOOL);
        $stmt->bindValue(2, $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function delete($id): bool
    {
        return $this->setActive($id, false);
    }
}