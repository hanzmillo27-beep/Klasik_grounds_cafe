<?php

namespace Models;

use Core\Model;

class DailyExpenses extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT
                expense_id,
                expense_name,
                amount,
                expense_date,
                created_at
             FROM daily_expenses
             ORDER BY expense_date DESC, expense_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                expense_id,
                expense_name,
                amount,
                expense_date,
                created_at
             FROM daily_expenses
             WHERE expense_id = ?'
        );

        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function save($data)
    {
        if (!empty($data['expense_id'])) {

            $stmt = $this->db->prepare(
                'UPDATE daily_expenses
                 SET expense_name = ?,
                     amount = ?,
                     expense_date = ?
                 WHERE expense_id = ?'
            );

            return $stmt->execute([
                $data['expense_name'],
                $data['amount'],
                $data['expense_date'],
                $data['expense_id']
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO daily_expenses
            (
                expense_name,
                amount,
                expense_date
            )
            VALUES (?, ?, ?)'
        );

        return $stmt->execute([
            $data['expense_name'],
            $data['amount'],
            $data['expense_date']
        ]);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM daily_expenses
             WHERE expense_id = ?'
        );

        return $stmt->execute([$id]);
    }

    public function dailyTotal($date)
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date = ?'
        );

        $stmt->execute([$date]);

        return $stmt->fetch();
    }

    public function weeklyTotal($startDate, $endDate)
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date BETWEEN ? AND ?'
        );

        $stmt->execute([
            $startDate,
            $endDate
        ]);

        return $stmt->fetch();
    }

    public function monthlyTotal($year, $month)
    {
        $year = (int) $year;
        $month = (int) $month;

        if ($year < 1 || $month < 1 || $month > 12) {
            return (object) ['total' => 0];
        }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $nextMonth = (new \DateTimeImmutable($monthStart))
            ->modify('+1 month')
            ->format('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total
             FROM daily_expenses
             WHERE expense_date >= ? AND expense_date < ?'
        );

        $stmt->execute([
            $monthStart,
            $nextMonth
        ]);

        return $stmt->fetch();
    }
}
