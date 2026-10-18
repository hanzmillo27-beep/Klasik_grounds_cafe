<?php

namespace Models;

use Core\Model;
use Core\Auth;
use Core\SupabaseStorage;
use Core\Money;

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
             WHERE p.is_active = TRUE AND p.is_available = TRUE
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
             WHERE p.product_id = ? AND p.is_active = TRUE AND p.is_available = TRUE'
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
                s.amount_paid,
                s.change_amount,
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
                s.amount_paid,
                s.change_amount,
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
                s.amount_paid,
                s.change_amount,
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
            $checkoutKey = $data['checkout_key'] ?? null;
            if ($checkoutKey !== null && (!is_string($checkoutKey) || !preg_match('/^[a-zA-Z0-9_-]{8,80}$/D', $checkoutKey))) {
                throw new \Exception('Invalid order checkout key.');
            }
            $kioskOrderId = isset($data['kiosk_order_id'])
                && $data['kiosk_order_id'] !== ''
                ? filter_var($data['kiosk_order_id'], FILTER_VALIDATE_INT)
                : null;
            if ($kioskOrderId !== null && (!$kioskOrderId || $kioskOrderId < 1)) throw new \Exception('Invalid kiosk order.');
            if (!is_string($data['customer_name'] ?? '') || strlen(trim($data['customer_name'] ?? '')) > 100) {
                throw new \Exception('Customer name must be at most 100 characters.');
            }
            $fingerprint = $checkoutKey === null ? null : $this->checkoutFingerprint($data, $kioskOrderId);

            $userId = Auth::user()['id'] ?? null;

            if (!$userId) {
                throw new \Exception('You must be logged in to complete checkout.');
            }

            $this->db->beginTransaction();
            if ($checkoutKey !== null && ($completed = $this->completedCheckout($checkoutKey, $userId, $fingerprint))) {
                $this->db->commit();
                return $completed;
            }

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
                     ' . $this->lockClause()
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

            }

            if (
                !isset($data['items'])
                || !is_array($data['items'])
                || count($data['items']) === 0
                || count($data['items']) > 100
            ) {
                throw new \Exception('Cart is empty.');
            }

            $items = [];
            $total = 0;
            $totalCents = 0;
            $seen = [];

            foreach ($data['items'] as $item) {
                if (!is_array($item)) throw new \Exception('Invalid cart item.');
                $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 99 || isset($seen[$productId])) {
                    throw new \Exception('Invalid cart item.');
                }
                $seen[$productId] = true;

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
                     WHERE product_id = ? AND is_active = TRUE AND is_available = TRUE
                     ' . $this->lockClause()
                );

                $stmt->execute([$productId]);

                $product = $stmt->fetch();

                if (!$product) {
                    throw new \Exception(
                        'A product in this order is unavailable. Please review the order.'
                    );
                }

                /*
                 * Regular POS uses the current database price.
                 * Kiosk checkout uses the saved price from the submitted order.
                 */
                $savedItem = null;
                if ($kioskOrderId !== null) {

                    foreach ($kioskItems as $candidate) {
                        if (
                            (int) $candidate->product_id === $productId
                        ) {
                            $savedItem = $candidate;
                            break;
                        }
                    }

                }
                if ($savedItem) {
                    $productName = $savedItem->product_name;
                    $unitPrice = $savedItem->unit_price;
                } else {
                    $productName = $product->product_name;
                    $unitPrice = $product->product_price;
                }

                $unitCents = Money::cents($unitPrice);
                $unitPrice = $unitCents / 100;
                $subtotalCents = $unitCents * $quantity;
                $subtotal = $subtotalCents / 100;

                $items[] = [
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];

                $totalCents += $subtotalCents;
                if ($totalCents > 9999999999) throw new \Exception('Order total exceeds the supported amount.');
            }

            $total = $totalCents / 100;
            if (isset($data['expected_total']) && Money::cents($data['expected_total']) !== $totalCents) {
                throw new \Exception('Order total changed. Review the current prices before checkout.');
            }
            $paidCents = Money::paymentCents($data['amount_paid'] ?? null);
            if ($paidCents < $totalCents) throw new \Exception('Amount paid must cover the order total.');
            $amountPaid = $paidCents / 100;
            $changeAmount = ($paidCents - $totalCents) / 100;

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
                     ' . $this->lockClause()
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
                    amount_paid,
                    change_amount,
                    checkout_key,
                    checkout_payload_hash,
                    sale_date
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (checkout_key) DO NOTHING';
            $stmt = $this->db->prepare($saleInsertSql);

            $stmt->execute([
                $userId,
                $customerName,
                $total,
                $amountPaid,
                $changeAmount,
                $checkoutKey,
                $fingerprint,
                date('Y-m-d H:i:s')
            ]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                $completed = $this->completedCheckout($checkoutKey, $userId, $fingerprint);
                if (!$completed) throw new \Exception('Unable to recover the completed order.');
                return $completed;
            }

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
                        completed_at = CURRENT_TIMESTAMP
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
                $earningInsertSql = 'INSERT INTO loyalty_earnings
                    (user_id, sale_id, point_date)
                    VALUES (?, ?, ?) ON CONFLICT (user_id, point_date) DO NOTHING';
                $stmt = $this->db->prepare($earningInsertSql);
                $stmt->execute([$customerUserId, $saleId, $pointDate]);

                if ($stmt->rowCount() === 1) {
                    $loyaltyInsertSql = 'INSERT INTO loyal (user_id, loyalty_points)
                        VALUES (?, 1)
                        ON CONFLICT (user_id) DO UPDATE SET
                        loyalty_points = loyal.loyalty_points + 1';
                    $stmt = $this->db->prepare($loyaltyInsertSql);
                    $stmt->execute([$customerUserId]);
                    $loyaltyPointEarned = true;
                }
            }

            $this->db->commit();

            return [
                'sale_id' => $saleId,
                'total_amount' => $total,
                'amount_paid' => $amountPaid,
                'change_amount' => $changeAmount,
                'kiosk_order_id' => $kioskOrderId,
                'loyalty_customer' => $customerUserId > 0,
                'loyalty_point_earned' => $loyaltyPointEarned
            ];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (isset($checkoutKey, $fingerprint, $userId)) {
                $completed = $this->completedCheckout($checkoutKey, $userId, $fingerprint);
                if ($completed) return $completed;
            }
            throw $e;
        }
    }

    private function checkoutFingerprint(array $data, ?int $kioskId): string
    {
        $rows = $data['items'] ?? null;
        if (!is_array($rows) || !$rows || count($rows) > 100) throw new \Exception('Cart is empty or invalid.');
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \Exception('Invalid cart item.');
            $id = filter_var($row['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1 || !$quantity || $quantity < 1 || $quantity > 99 || isset($items[$id])) throw new \Exception('Invalid cart item.');
            $items[$id] = $quantity;
        }
        ksort($items);
        return hash('sha256', json_encode([$items, trim($data['customer_name'] ?? ''),
            Money::paymentCents($data['amount_paid'] ?? null), $kioskId,
            isset($data['expected_total']) ? Money::cents($data['expected_total']) : null]));
    }

    private function completedCheckout(string $key, int $userId, string $fingerprint): ?array
    {
        $stmt = $this->db->prepare('SELECT sale_id, user_id, total_amount, amount_paid, change_amount, checkout_payload_hash FROM sales WHERE checkout_key = ?');
        $stmt->execute([$key]);
        $sale = $stmt->fetch();
        if (!$sale) return null;
        if ((int) $sale->user_id !== $userId) throw new \Exception('This order was completed by another cashier.');
        if (!hash_equals($sale->checkout_payload_hash ?? '', $fingerprint)) {
            throw new \Exception('This order was already paid with different details. Open transaction #' . $sale->sale_id . ' before starting a new order.');
        }
        return ['sale_id'=>(int) $sale->sale_id, 'total_amount'=>(float) $sale->total_amount,
            'amount_paid'=>(float) $sale->amount_paid, 'change_amount'=>(float) $sale->change_amount,
            'kiosk_order_id'=>null, 'loyalty_customer'=>false, 'loyalty_point_earned'=>false, 'replayed'=>true];
    }
}

