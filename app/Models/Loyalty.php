<?php

namespace Models;

use Core\Model;

class Loyalty extends Model
{
    public const POINTS_PER_REWARD = 10;

    public function summary(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT loyalty_id, loyalty_points
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
                reward_code
             FROM (
                SELECT
                    \'earned\' AS activity_type,
                    \'Purchase point\' AS description,
                    earned_at AS activity_date,
                    1 AS points,
                    NULL AS reward_code
                FROM loyalty_earnings
                WHERE user_id = ?

                UNION ALL

                SELECT
                    \'redeemed\' AS activity_type,
                    \'Free coffee reward\' AS description,
                    created_at AS activity_date,
                    -points_spent AS points,
                    reward_code
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

    public function redeem(int $userId): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT loyalty_id, loyalty_points
                 FROM loyal
                 WHERE user_id = ?
                 FOR UPDATE'
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
                 WHERE loyalty_id = ? AND loyalty_points >= ?'
            );
            $stmt->execute([
                self::POINTS_PER_REWARD,
                $loyalty->loyalty_id,
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
