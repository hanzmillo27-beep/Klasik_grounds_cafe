<?php

namespace Models;

use Core\Model;
use Core\SupabaseStorage;

class Kiosk extends Model
{
    public function products()
    {
        $stmt = $this->db->query(
            'SELECT
                p.product_id,
                p.product_name,
                p.product_price,
                p.product_image,
                c.category_name
             FROM products p
             LEFT JOIN categories c
                ON c.category_id = p.category_id
             WHERE p.is_active = TRUE AND p.is_available = TRUE
             ORDER BY p.product_name'
        );

        $products = $stmt->fetchAll();
        foreach ($products as $product) {
            $product->product_image_url = SupabaseStorage::publicUrl($product->product_image ?? null);
        }
        return $products;
    }

    public function submit($data)
    {
        $items = $data['items'] ?? [];

        if (!is_array($items) || !$items || count($items) > 100) {
            throw new \Exception('Your cart is empty.');
        }

        $this->db->beginTransaction();

        try {
            $orderItems = [];
            $total = 0;
            $seen = [];

            foreach ($items as $item) {
                if (!is_array($item)) throw new \Exception('Invalid product or quantity.');
                $productId = filter_var(
                    $item['product_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                $quantity = filter_var(
                    $item['quantity'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 99 || isset($seen[$productId])) {
                    throw new \Exception('Invalid product or quantity.');
                }
                $seen[$productId] = true;

                $stmt = $this->db->prepare(
                    'SELECT product_id, product_name, product_price
                     FROM products
                     WHERE product_id = ? AND is_active = TRUE AND is_available = TRUE'
                );
                $stmt->execute([$productId]);

                $product = $stmt->fetch();

                if (!$product) {
                    throw new \Exception('A product in your cart is no longer available.');
                }

                $price = (float) $product->product_price;
                $subtotal = round($price * $quantity, 2);

                $orderItems[] = [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'subtotal' => $subtotal
                ];

                $total += $subtotal;
            }

            $reference = 'KG-' . strtoupper(bin2hex(random_bytes(4)));

            $orderInsertSql =
                'INSERT INTO kiosk_orders
                    (reference_code, customer_name, total_amount, customer_user_id)
                 VALUES (?, ?, ?, ?)';
            $stmt = $this->db->prepare($orderInsertSql);

            $customerName = trim($data['customer_name'] ?? '');
            $stmt->execute([
                $reference,
                $customerName !== '' ? $customerName : null,
                $total,
                $data['customer_user_id'] ?? null
            ]);

            $orderId = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare(
                'INSERT INTO kiosk_order_items
                    (kiosk_order_id, product_id, product_name,
                     quantity, unit_price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($orderItems as $item) {
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ]);
            }

            $this->db->commit();

            return [
                'reference_code' => $reference,
                'total_amount' => $total
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function findByReference($reference)
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM kiosk_orders
             WHERE reference_code = ?
             LIMIT 1'
        );

        $stmt->execute([strtoupper(trim($reference))]);

        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

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

        $stmt->execute([$order->kiosk_order_id]);

        $order->items = $stmt->fetchAll();

        return $order;
    }

    public function claim($reference, $userId)
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT kiosk_order_id, status, claimed_by
                 FROM kiosk_orders
                 WHERE reference_code = ?
                 ' . $this->lockClause()
            );

            $stmt->execute([strtoupper(trim($reference))]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new \Exception('Order reference not found.');
            }

            // Let the same cashier recover an already claimed order after a page reload.
            if (
                $order->status === 'claimed'
                && (int) $order->claimed_by === (int) $userId
            ) {
                $this->db->commit();
                return $this->findByReference($reference);
            }

            if ($order->status !== 'pending') {
                throw new \Exception(
                    'This order is no longer available. Status: ' .
                    $order->status
                );
            }

            $stmt = $this->db->prepare(
                'UPDATE kiosk_orders
                 SET status = ?, claimed_by = ?, claimed_at = CURRENT_TIMESTAMP
                 WHERE kiosk_order_id = ? AND status = ?'
            );

            $stmt->execute([
                'claimed',
                $userId,
                $order->kiosk_order_id,
                'pending'
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new \Exception('Another cashier has already claimed this order.');
            }

            $this->db->commit();

            return $this->findByReference($reference);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function cashierCanAccess($userId)
    {
        $stmt = $this->db->prepare(
            'SELECT role
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user && $user->role === 'cashier';
    }
}
