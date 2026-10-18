<?php
namespace Models;

use Core\Model;

class Reports extends Model
{
    public function inventory(): array
    {
        $rows = (new Inventory())->find();
        $generated = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        $low = 0;
        foreach ($rows as $row) if ($row->is_low) $low++;
        return ['report_type'=>'inventory', 'label'=>'Inventory report: ' . $generated,
            'generated_at'=>$generated, 'rows'=>$rows, 'total_items'=>count($rows), 'low_stock_count'=>$low];
    }

    public function products($period, $value): array
    {
        [$start, $end] = $this->period($period, $value);
        $stmt = $this->db->prepare('SELECT s.sale_id, si.product_id,
            COALESCE(si.product_name, p.product_name, \'Deleted Product\') AS product_name,
            si.quantity, si.subtotal FROM sale_items si
            INNER JOIN sales s ON s.sale_id = si.sale_id
            LEFT JOIN products p ON p.product_id = si.product_id
            WHERE s.sale_date >= ? AND s.sale_date < ? ORDER BY s.sale_date, si.sale_item_id');
        $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $groups = [];
        $saleIds = [];
        $totalCents = 0;
        $totalQuantity = 0;
        foreach ($stmt->fetchAll() as $item) {
            $key = $item->product_id === null ? 'name:' . $item->product_name : 'id:' . $item->product_id;
            if (!isset($groups[$key])) $groups[$key] = (object) ['product_id'=>$item->product_id === null ? null : (int) $item->product_id,
                'product_name'=>$item->product_name, 'quantity_sold'=>0, 'cents'=>0];
            if (strcmp($item->product_name, $groups[$key]->product_name) > 0) $groups[$key]->product_name = $item->product_name;
            $cents = (int) round((float) $item->subtotal * 100);
            $groups[$key]->quantity_sold += (int) $item->quantity;
            $groups[$key]->cents += $cents;
            $totalCents += $cents;
            $totalQuantity += (int) $item->quantity;
            $saleIds[$item->sale_id] = true;
        }
        $rows = array_values($groups);
        foreach ($rows as $row) { $row->total_sales = $row->cents / 100; unset($row->cents); }
        usort($rows, function ($a, $b) { return $b->total_sales <=> $a->total_sales ?: strcmp($a->product_name, $b->product_name); });
        $endDate = $end->modify('-1 day')->format('Y-m-d');
        return ['report_type'=>'products', 'period'=>$period, 'start_date'=>$start->format('Y-m-d'), 'end_date'=>$endDate,
            'label'=>'Product sales: ' . $start->format('Y-m-d') . ($period === 'daily' ? '' : ' to ' . $endDate),
            'rows'=>$rows, 'total_quantity'=>$totalQuantity, 'total_amount'=>$totalCents / 100, 'transaction_count'=>count($saleIds)];
    }

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
        $endDate = $end->modify('-1 day')->format('Y-m-d');
        return ['period'=>$period, 'start_date'=>$start->format('Y-m-d'),
            'end_date'=>$end->modify('-1 day')->format('Y-m-d'),
            'label'=>ucfirst($period) . ' sales: ' . $start->format('Y-m-d') . ($period === 'daily' ? '' : ' to ' . $endDate), 'rows'=>$rows,
            'transaction_count'=>count($rows), 'total_amount'=>$totalCents / 100];
    }

    private function period($period, $value): array
    {
        if ($period === 'monthly') {
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})$/D', $value, $match)
                || (int) $match[1] < 1000 || (int) $match[1] > 9998) {
                throw new \InvalidArgumentException('Choose a valid report month.');
            }
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01', new \DateTimeZone('Asia/Manila'));
            if (!$start || $start->format('Y-m') !== $value) throw new \InvalidArgumentException('Choose a valid report month.');
            return [$start, $start->modify('+1 month')];
        }
        if ($period === 'weekly') {
            if (!is_string($value) || !preg_match('/^(\d{4})-W(\d{2})$/D', $value, $match)
                || (int) $match[1] < 1001 || (int) $match[1] > 9997) {
                throw new \InvalidArgumentException('Choose a valid ISO report week.');
            }
            $start = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
                ->setISODate((int) $match[1], (int) $match[2])->setTime(0, 0);
            if ($start->format('o-\WW') !== $value) throw new \InvalidArgumentException('Choose a valid ISO report week.');
            return [$start, $start->modify('+1 week')];
        }
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
