<?php

namespace Models;

use Core\Model;
use Core\Auth;
use Core\SupabaseStorage;

class POS extends Model
{
    public function products()
    {
        $stmt = $this->db->query(
            'SELECT
                p.product_id,
                p.product_name,
                p.category_id,
                c.category_name,
                p.product_price,
                p.product_image
             FROM products p
             LEFT JOIN categories c
                ON c.category_id = p.category_id
             ORDER BY p.product_name'
        );

        $products = $stmt->fetchAll();
        foreach ($products as $product) {
            $product->product_image_url = SupabaseStorage::publicUrl($product->product_image ?? null);
        }
        return $products;
    }

    public function product($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.product_id,
                p.product_name,
                p.product_price,
                c.category_name
             FROM products p
             LEFT JOIN categories c
                ON c.category_id = p.category_id
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
                pi.quantity_required,
                i.inv_name,
                i.amount,
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

    public function transactions()
    {
        $stmt = $this->db->query(
            'SELECT
                s.sale_id,
                s.user_id,
                s.customer_name,
                s.total_amount,
                s.sale_date,
                u.username AS user_name,
                COUNT(si.sale_item_id) AS item_count
             FROM sales s
             LEFT JOIN users u
                ON u.id = s.user_id
             LEFT JOIN sale_items si
                ON si.sale_id = s.sale_id
             GROUP BY
                s.sale_id,
                s.user_id,
                s.customer_name,
                s.total_amount,
                s.sale_date,
                u.username
             ORDER BY s.sale_date DESC, s.sale_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function transaction($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                s.sale_id,
                s.user_id,
                s.customer_name,
                s.total_amount,
                s.sale_date,
                u.username AS user_name
             FROM sales s
             LEFT JOIN users u
                ON u.id = s.user_id
             WHERE s.sale_id = ?'
        );

        $stmt->execute([$id]);

        $sale = $stmt->fetch();

        if (!$sale) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT
                si.sale_item_id,
                si.product_id,
                COALESCE(
                    si.product_name,
                    p.product_name,
                    \'Deleted Product\'
                ) AS product_name,
                si.quantity,
                si.unit_price,
                si.subtotal
             FROM sale_items si
             LEFT JOIN products p
                ON p.product_id = si.product_id
             WHERE si.sale_id = ?
             ORDER BY si.sale_item_id'
        );

        $stmt->execute([$id]);

        $sale->items = $stmt->fetchAll();

        return $sale;
    }

    public function checkout($data)
    {
        try {
            $kioskOrderId = isset($data['kiosk_order_id'])
                && $data['kiosk_order_id'] !== ''
                ? (int) $data['kiosk_order_id']
                : null;

            $userId = Auth::user()['id'] ?? null;

            if (!$userId) {
                throw new \Exception('You must be logged in to complete checkout.');
            }

            $this->db->beginTransaction();

            $kioskOrder = null;

            /*
             * Lock and validate the kiosk order before processing it.
             * This prevents two cashiers from completing the same order.
             */
            if ($kioskOrderId !== null) {
                if ($kioskOrderId <= 0) {
                    throw new \Exception('Invalid kiosk order.');
                }

                $stmt = $this->db->prepare(
                    'SELECT
                        kiosk_order_id,
                        reference_code,
                        customer_name,
                        status,
                        claimed_by,
                        customer_user_id
                     FROM kiosk_orders
                     WHERE kiosk_order_id = ?
                     FOR UPDATE'
                );

                $stmt->execute([$kioskOrderId]);

                $kioskOrder = $stmt->fetch();

                if (!$kioskOrder) {
                    throw new \Exception('Kiosk order not found.');
                }

                if ($kioskOrder->status === 'completed') {
                    throw new \Exception('This kiosk order has already been checked out.');
                }

                if (
                    $kioskOrder->status !== 'claimed'
                    || (int) $kioskOrder->claimed_by !== (int) $userId
                ) {
                    throw new \Exception(
                        'This kiosk order is not claimed by your cashier account.'
                    );
                }

                /*
                 * Use the database order items, not submitted browser prices.
                 */
                $stmt = $this->db->prepare(
                    'SELECT
                        kiosk_item_id,
                        product_id,
                        product_name,
                        quantity,
                        unit_price,
                        subtotal
                     FROM kiosk_order_items
                     WHERE kiosk_order_id = ?
                     ORDER BY kiosk_item_id'
                );

                $stmt->execute([$kioskOrderId]);

                $kioskItems = $stmt->fetchAll();

                if (!$kioskItems) {
                    throw new \Exception('The kiosk order has no items.');
                }

                $data['items'] = [];

                foreach ($kioskItems as $kioskItem) {
                    $data['items'][] = [
                        'product_id' => $kioskItem->product_id,
                        'quantity' => $kioskItem->quantity,
                        'product_name' => $kioskItem->product_name,
                        'unit_price' => $kioskItem->unit_price
                    ];
                }

                $data['customer_name'] = $kioskOrder->customer_name ?? '';
            }

            if (
                !isset($data['items'])
                || !is_array($data['items'])
                || count($data['items']) === 0
            ) {
                throw new \Exception('Cart is empty.');
            }

            $items = [];
            $total = 0;

            foreach ($data['items'] as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;

                if (
                    !is_numeric($productId)
                    || !is_numeric($quantity)
                    || (int) $quantity <= 0
                    || (int) $quantity != $quantity
                ) {
                    throw new \Exception('Invalid cart item.');
                }

                $productId = (int) $productId;
                $quantity = (int) $quantity;

                /*
                 * Lock the current product row and confirm it still exists.
                 */
                $stmt = $this->db->prepare(
                    'SELECT
                        product_id,
                        product_name,
                        product_price
                     FROM products
                     WHERE product_id = ?
                     FOR UPDATE'
                );

                $stmt->execute([$productId]);

                $product = $stmt->fetch();

                if (!$product) {
                    throw new \Exception(
                        'A product in this order no longer exists. Please review the order.'
                    );
                }

                /*
                 * Regular POS uses the current database price.
                 * Kiosk checkout uses the saved price from the submitted order.
                 */
                if ($kioskOrderId !== null) {
                    $savedItem = null;

                    foreach ($kioskItems as $candidate) {
                        if (
                            (int) $candidate->product_id === $productId
                            && (int) $candidate->quantity === $quantity
                            && (string) $candidate->product_name === (string) ($item['product_name'] ?? '')
                            && (float) $candidate->unit_price === (float) ($item['unit_price'] ?? -1)
                        ) {
                            $savedItem = $candidate;
                            break;
                        }
                    }

                    if (!$savedItem) {
                        throw new \Exception('Invalid saved kiosk order item.');
                    }

                    $productName = $savedItem->product_name;
                    $unitPrice = (float) $savedItem->unit_price;
                } else {
                    $productName = $product->product_name;
                    $unitPrice = (float) $product->product_price;
                }

                if ($unitPrice < 0) {
                    throw new \Exception('Invalid product price.');
                }

                $subtotal = round($unitPrice * $quantity, 2);

                $items[] = [
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];

                $total += $subtotal;
            }

            $total = round($total, 2);

            /*
             * Check all required ingredients before creating the sale.
             */
            foreach ($items as $item) {
                $stmt = $this->db->prepare(
                    'SELECT
                        pi.inv_id,
                        pi.quantity_required,
                        i.inv_name,
                        i.amount,
                        i.unit
                     FROM product_ingredients pi
                     INNER JOIN inventory i
                        ON i.inv_id = pi.inv_id
                     WHERE pi.product_id = ?
                     FOR UPDATE'
                );

                $stmt->execute([$item['product_id']]);

                $ingredients = $stmt->fetchAll();

                foreach ($ingredients as $ingredient) {
                    $required = (float) $ingredient->quantity_required
                        * $item['quantity'];

                    $available = (float) $ingredient->amount;

                    if ($available < $required) {
                        throw new \Exception(
                            'Insufficient inventory for ' .
                            $ingredient->inv_name .
                            '. Available: ' .
                            $available . ' ' .
                            $ingredient->unit .
                            ', Required: ' .
                            $required . ' ' .
                            $ingredient->unit . '.'
                        );
                    }
                }
            }

            $customerName = trim($data['customer_name'] ?? '');

            if ($customerName === '') {
                $customerName = null;
            }

            $saleInsertSql =
                'INSERT INTO sales
                (
                    user_id,
                    customer_name,
                    total_amount,
                    sale_date
                )
                VALUES (?, ?, ?, ?)';
            $stmt = $this->db->prepare($saleInsertSql);

            $stmt->execute([
                $userId,
                $customerName,
                $total,
                date('Y-m-d H:i:s')
            ]);

            $saleId = (int) $this->db->lastInsertId();

            $insertItem = $this->db->prepare(
                'INSERT INTO sale_items
                (
                    sale_id,
                    product_id,
                    product_name,
                    quantity,
                    unit_price,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?, ?)'
            );

            $deductInventory = $this->db->prepare(
                'UPDATE inventory
                 SET amount = amount - ?
                 WHERE inv_id = ?
                   AND amount >= ?'
            );

            foreach ($items as $item) {
                $insertItem->execute([
                    $saleId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ]);

                $stmt = $this->db->prepare(
                    'SELECT
                        inv_id,
                        quantity_required
                     FROM product_ingredients
                     WHERE product_id = ?'
                );

                $stmt->execute([$item['product_id']]);

                $ingredients = $stmt->fetchAll();

                foreach ($ingredients as $ingredient) {
                    $required = (float) $ingredient->quantity_required
                        * $item['quantity'];

                    if ($required <= 0) {
                        continue;
                    }

                    $deductInventory->execute([
                        $required,
                        $ingredient->inv_id,
                        $required
                    ]);

                    if ($deductInventory->rowCount() !== 1) {
                        throw new \Exception(
                            'Inventory changed during checkout. Please try again.'
                        );
                    }
                }
            }

            /*
             * Mark the kiosk order completed in the same transaction.
             * If this update fails, all sale and inventory changes roll back.
             */
            if ($kioskOrderId !== null) {
                $stmt = $this->db->prepare(
                    'UPDATE kiosk_orders
                     SET
                        status = \'completed\',
                        sale_id = ?,
                        completed_at = NOW()
                     WHERE kiosk_order_id = ?
                       AND status = \'claimed\'
                       AND claimed_by = ?'
                );

                $stmt->execute([
                    $saleId,
                    $kioskOrderId,
                    $userId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new \Exception(
                        'Unable to complete this kiosk order. It may already have been checked out.'
                    );
                }
            }

            $loyaltyPointEarned = false;
            $customerUserId = $kioskOrderId !== null
                ? (int) ($kioskOrder->customer_user_id ?? 0)
                : 0;

            if ($customerUserId > 0) {
                $pointDate = date('Y-m-d');
                $earningInsertSql = 'INSERT IGNORE INTO loyalty_earnings
                    (user_id, sale_id, point_date)
                    VALUES (?, ?, ?)';
                $stmt = $this->db->prepare($earningInsertSql);
                $stmt->execute([$customerUserId, $saleId, $pointDate]);

                if ($stmt->rowCount() === 1) {
                    $loyaltyInsertSql = 'INSERT INTO loyal (user_id, loyalty_points)
                        VALUES (?, 1)
                        ON DUPLICATE KEY UPDATE
                        loyalty_points = loyalty_points + 1';
                    $stmt = $this->db->prepare($loyaltyInsertSql);
                    $stmt->execute([$customerUserId]);
                    $loyaltyPointEarned = true;
                }
            }

            $this->db->commit();

            return [
                'sale_id' => $saleId,
                'total_amount' => $total,
                'kiosk_order_id' => $kioskOrderId,
                'loyalty_customer' => $customerUserId > 0,
                'loyalty_point_earned' => $loyaltyPointEarned
            ];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}

