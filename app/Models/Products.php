<?php

namespace Models;

use Core\Model;

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
                p.created_at
             FROM products p
             LEFT JOIN categories c
                ON c.category_id = p.category_id
             ORDER BY p.product_name'
        );

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.product_id,
                p.product_name,
                p.category_id,
                p.product_price,
                p.created_at
             FROM products p
             WHERE p.product_id = ?'
        );

        $stmt->execute([$id]);

        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

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
        try {

            $this->db->beginTransaction();

            if (!empty($data['product_id'])) {

                $stmt = $this->db->prepare(
                    'UPDATE products
                     SET product_name = ?,
                         category_id = ?,
                         product_price = ?
                     WHERE product_id = ?'
                );

                $stmt->execute([
                    $data['product_name'],
                    $data['category_id'],
                    $data['product_price'],
                    $data['product_id']
                ]);

                $productId = $data['product_id'];

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
                        created_at
                    )
                    VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($productInsertSql);

                $stmt->execute([
                    $data['product_name'],
                    $data['category_id'],
                    $data['product_price'],
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
                        continue;
                    }

                    $quantity =
                        $data['ingredient_quantity'][$key]
                        ?? 0;

                    if (
                        !is_numeric($quantity) ||
                        $quantity <= 0
                    ) {
                        continue;
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

    public function delete($id): bool
{
    try {

        $this->db->beginTransaction();

        $stmt = $this->db->prepare(
            'DELETE FROM product_ingredients
             WHERE product_id = ?'
        );

        $stmt->execute([
            $id
        ]);

        $stmt = $this->db->prepare(
            'DELETE FROM products
             WHERE product_id = ?'
        );

        $result = $stmt->execute([
            $id
        ]);

        $this->db->commit();

        return $result;

    } catch (\Exception $e) {

        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        return false;
    }
}
}
