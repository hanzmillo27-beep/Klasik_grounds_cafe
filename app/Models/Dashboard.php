<?php

namespace Models;

use Core\Model;

class Dashboard extends Model
{
    public function weeklyNetProfit()
    {
        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $tomorrow = $today->modify('+1 day');

        $salesStmt = $this->db->prepare(
            'SELECT DATE(sale_date) AS report_date,
                    COALESCE(SUM(total_amount), 0) AS total
             FROM sales
             WHERE sale_date >= ? AND sale_date < ?
             GROUP BY DATE(sale_date)'
        );
        $salesStmt->execute([
            $weekStart->format('Y-m-d 00:00:00'),
            $tomorrow->format('Y-m-d 00:00:00')
        ]);

        $expensesStmt = $this->db->prepare(
            'SELECT expense_date AS report_date,
                    COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date BETWEEN ? AND ?
             GROUP BY expense_date'
        );
        $expensesStmt->execute([
            $weekStart->format('Y-m-d'),
            $today->format('Y-m-d')
        ]);

        $salesByDate = [];
        foreach ($salesStmt->fetchAll() as $row) {
            $salesByDate[$row->report_date] = (float) $row->total;
        }

        $expensesByDate = [];
        foreach ($expensesStmt->fetchAll() as $row) {
            $expensesByDate[$row->report_date] = (float) $row->total;
        }

        $labels = [];
        $values = [];
        for ($day = $weekStart; $day <= $today; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $labels[] = $day->format('D, M j');
            $values[] = ($salesByDate[$date] ?? 0) - ($expensesByDate[$date] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    public function expenseSummary()
    {
        $today = date('Y-m-d');

        $date = new \DateTime($today);

        $dayOfWeek = (int) $date->format('N');

        $start = clone $date;
        $start->modify('-' . ($dayOfWeek - 1) . ' days');

        $end = clone $start;
        $end->modify('+6 days');

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        $monthStart = $date->format('Y-m-01');
        $nextMonth = (clone $date)->modify('first day of next month')->format('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date = ?'
        );

        $stmt->execute([
            $today
        ]);

        $todayTotal = $stmt->fetch();

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date BETWEEN ? AND ?'
        );

        $stmt->execute([
            $startDate,
            $endDate
        ]);

        $weekTotal = $stmt->fetch();

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date >= ? AND expense_date < ?'
        );

        $stmt->execute([
            $monthStart,
            $nextMonth
        ]);

        $monthTotal = $stmt->fetch();

        return [
            'today' => (float) $todayTotal->total,
            'week' => (float) $weekTotal->total,
            'month' => (float) $monthTotal->total
        ];
    }

    public function inventorySummary()
    {
        $stmt = $this->db->query(
            'SELECT
                COUNT(*) AS total_items,
                SUM(
                    CASE
                        WHEN amount <= 10 THEN 1
                        ELSE 0
                    END
                ) AS low_stock
             FROM inventory'
        );

        return $stmt->fetch();
    }

    public function recentExpenses()
    {
        $stmt = $this->db->query(
            'SELECT
                expense_id,
                expense_name,
                amount,
                expense_date
             FROM daily_expenses
             ORDER BY expense_date DESC, expense_id DESC
             LIMIT 5'
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
                unit,
                CASE
                    WHEN amount <= 10 THEN \'Low Stock\'
                    ELSE \'Available\'
                END AS stock_status
             FROM inventory
             ORDER BY
                CASE
                    WHEN amount <= 10 THEN 0
                    ELSE 1
                END,
                inv_name'
        );

        return $stmt->fetchAll();
    }
}
