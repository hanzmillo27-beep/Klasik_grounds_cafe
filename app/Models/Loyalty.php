<?php

namespace Models;

use Core\Model;

class Loyalty extends Model
{
    public const POINTS_PER_REWARD = 10;

    public function summary(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT loyalty_points
             FROM loyal
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $loyalty = $stmt->fetch();
        $points = $loyalty ? (int) $loyalty->loyalty_points : 0;

        $stmt = $this->db->prepare(
            'SELECT
                activity_type,
                description,
                activity_date,
                points,
                reward_code,
                used_sale_id,
                used_at,
                status,
                product_id,
                product_name
             FROM (
                SELECT
                    \'earned\' AS activity_type,
                    \'Purchase point\' AS description,
                    earned_at AS activity_date,
                    1 AS points,
                    NULL AS reward_code,
                    NULL AS used_sale_id,
                    NULL AS used_at,
                    NULL AS status,
                    NULL AS product_id,
                    NULL AS product_name
                FROM loyalty_earnings
                WHERE user_id = ?

                UNION ALL

                SELECT
                    \'redeemed\' AS activity_type,
                    COALESCE(product_name, \'Free coffee reward\') AS description,
                    created_at AS activity_date,
                    -points_spent AS points,
                    reward_code,
                    used_sale_id,
                    used_at,
                    CASE WHEN used_sale_id IS NULL AND used_at IS NULL
                        THEN \'available\' ELSE \'used\' END AS status,
                    product_id,
                    product_name
                FROM loyalty_redemptions
                WHERE user_id = ?
             ) AS activity
             ORDER BY activity_date DESC
             LIMIT 20'
        );
        $stmt->execute([$userId, $userId]);

        $activity = $stmt->fetchAll();
        $remainder = $points % self::POINTS_PER_REWARD;

        return [
            'points' => $points,
            'points_to_next_reward' => self::POINTS_PER_REWARD - $remainder,
            'can_redeem' => $points >= self::POINTS_PER_REWARD,
            'rewards_available' => intdiv($points, self::POINTS_PER_REWARD),
            'activity' => $activity
        ];
    }

    public function reward(string $code): array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^GC-[A-F0-9]{8}$/D', $code)) {
            throw new \InvalidArgumentException('Enter a valid free coffee reward code.');
        }

        $stmt = $this->db->prepare(
            'SELECT rewards.reward_code, users.username AS customer_name, rewards.user_id,
                rewards.created_at, rewards.points_spent, rewards.used_sale_id, rewards.used_at,
                rewards.product_id, rewards.product_name
             FROM loyalty_redemptions rewards
             INNER JOIN users ON users.id = rewards.user_id
             WHERE rewards.reward_code = ?'
        );
        $stmt->execute([$code]);
        $reward = $stmt->fetch();
        if (!$reward) throw new \Exception('Free coffee reward code not found.');
        if ($reward->used_sale_id !== null || $reward->used_at !== null) {
            throw new \Exception('This free coffee reward has already been used.');
        }

        $result = [
            'reward_code' => $reward->reward_code,
            'customer_name' => $reward->customer_name,
            'user_id' => (int) $reward->user_id,
            'created_at' => $reward->created_at,
            'points_spent' => (int) $reward->points_spent,
        ];
        if ($reward->product_id !== null) {
            $result['product_id'] = (int) $reward->product_id;
            $result['product_name'] = $reward->product_name;
        }
        return $result;
    }

    public function redeemItem(int $userId, int $productId): array
    {
        if ($userId < 1 || $productId < 1) {
            throw new \InvalidArgumentException('Choose a valid item to redeem.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT loyalty_points FROM loyal WHERE user_id = ?' . $this->lockClause()
            );
            $stmt->execute([$userId]);
            $loyalty = $stmt->fetch();
            $points = $loyalty ? (int) $loyalty->loyalty_points : 0;
            if ($points < self::POINTS_PER_REWARD) {
                throw new \Exception('You need 10 points to redeem an item.');
            }

            // The saved catalog item binds this voucher to one item. Issuing
            // a voucher validates stock but does not reserve or consume it.
            $stmt = $this->db->prepare(
                'SELECT p.product_id, p.product_name, p.is_active, p.is_available,
                    ' . ProductAvailability::stockSql() . ' AS stock_available
                 FROM products p WHERE p.product_id = ?' . $this->lockClause()
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new \Exception('This item is no longer available. Choose another item.');
            }
            ProductAvailability::apply($product);
            if (!$product->can_order) {
                throw new \Exception('This item is unavailable. Choose another item.');
            }

            $stmt = $this->db->prepare(
                'UPDATE loyal SET loyalty_points = loyalty_points - ?
                 WHERE user_id = ? AND loyalty_points >= ?'
            );
            $stmt->execute([self::POINTS_PER_REWARD, $userId, self::POINTS_PER_REWARD]);
            if ($stmt->rowCount() !== 1) {
                throw new \Exception('Your points changed. Refresh and try again.');
            }

            $rewardCode = 'GC-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $this->db->prepare(
                'INSERT INTO loyalty_redemptions
                    (user_id, reward_code, points_spent, product_id, product_name)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId, $rewardCode, self::POINTS_PER_REWARD,
                (int) $product->product_id, $product->product_name
            ]);
            $this->db->commit();

            return [
                'reward_code' => $rewardCode,
                'points' => $points - self::POINTS_PER_REWARD,
                'points_spent' => self::POINTS_PER_REWARD,
                'product_id' => (int) $product->product_id,
                'product_name' => $product->product_name
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function redeem(int $userId): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT loyalty_points
                 FROM loyal
                 WHERE user_id = ?' . $this->lockClause()
            );
            $stmt->execute([$userId]);
            $loyalty = $stmt->fetch();
            $points = $loyalty ? (int) $loyalty->loyalty_points : 0;

            if ($points < self::POINTS_PER_REWARD) {
                throw new \Exception('You need 10 points to redeem a free coffee.');
            }

            $stmt = $this->db->prepare(
                'UPDATE loyal
                 SET loyalty_points = loyalty_points - ?
                 WHERE user_id = ? AND loyalty_points >= ?'
            );
            $stmt->execute([
                self::POINTS_PER_REWARD,
                $userId,
                self::POINTS_PER_REWARD
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new \Exception('Your points changed. Refresh and try again.');
            }

            $rewardCode = 'GC-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $this->db->prepare(
                'INSERT INTO loyalty_redemptions
                    (user_id, reward_code, points_spent)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $rewardCode, self::POINTS_PER_REWARD]);

            $this->db->commit();

            return [
                'reward_code' => $rewardCode,
                'points' => $points - self::POINTS_PER_REWARD
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
