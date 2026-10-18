<?php
namespace Models;

use Core\Model;

class Reports extends Model
{
    public function sales($period, $value): array
    {
        [$start, $end] = $this->period($period, $value);
        $stmt = $this->db->prepare('SELECT s.sale_id, s.sale_date, s.customer_name,
            u.username AS user_name, s.total_amount, s.amount_paid, s.change_amount
            FROM sales s LEFT JOIN users u ON u.id = s.user_id
            WHERE s.sale_date >= ? AND s.sale_date < ? ORDER BY s.sale_date, s.sale_id');
        // Sales timestamps are stored as local Asia/Manila wall time.
        $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll();
        $totalCents = 0;
        foreach ($rows as $row) $totalCents += (int) round((float) $row->total_amount * 100);
        return ['period'=>$period, 'start_date'=>$start->format('Y-m-d'),
            'end_date'=>$end->modify('-1 day')->format('Y-m-d'),
            'label'=>'Daily sales: ' . $start->format('Y-m-d'), 'rows'=>$rows,
            'transaction_count'=>count($rows), 'total_amount'=>$totalCents / 100];
    }

    private function period($period, $value): array
    {
        if ($period !== 'daily' || !is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new \InvalidArgumentException('Choose a valid report date.');
        }
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Asia/Manila'));
        if (!$start || $start->format('Y-m-d') !== $value || (int) $start->format('Y') < 1000 || (int) $start->format('Y') > 9998) {
            throw new \InvalidArgumentException('Choose a valid report date.');
        }
        return [$start, $start->modify('+1 day')];
    }
}
