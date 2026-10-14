<?php

namespace Api\DailyExpenses;

use Core\BaseController;
use Models\DailyExpenses;

class DailyExpensesController extends BaseController
{
    private $DailyExpenses;

    public function __construct()
    {
        $this->DailyExpenses = new DailyExpenses();
    }

    public function all()
    {
        $this->json([
            'data' => $this->DailyExpenses->find()
        ]);
    }

    public function add()
    {
        $data = $_POST;

        if (empty(trim($data['expense_name'] ?? ''))) {
            $this->json([
                'status' => 'error',
                'message' => 'Expense name is required.'
            ]);
        }

        if (!isset($data['amount']) || $data['amount'] === '') {
            $this->json([
                'status' => 'error',
                'message' => 'Expense amount is required.'
            ]);
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            $this->json([
                'status' => 'error',
                'message' => 'Expense amount must be greater than zero.'
            ]);
        }

        if (empty($data['expense_date'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Expense date is required.'
            ]);
        }

        $date = \DateTime::createFromFormat(
            'Y-m-d',
            $data['expense_date']
        );

        if (!$date || $date->format('Y-m-d') !== $data['expense_date']) {
            $this->json([
                'status' => 'error',
                'message' => 'Invalid expense date.'
            ]);
        }

        if ($this->DailyExpenses->save($data)) {
            $this->json([
                'status' => 'success',
                'message' => empty($data['expense_id'])
                    ? 'Expense added successfully.'
                    : 'Expense updated successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to save expense.'
        ]);
    }

    public function edit($id)
    {
        $expense = $this->DailyExpenses->findByID($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = $_POST;
            $data['expense_id'] = $id;

            if (empty(trim($data['expense_name'] ?? ''))) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Expense name is required.'
                ]);
            }

            if (!isset($data['amount']) || $data['amount'] === '') {
                $this->json([
                    'status' => 'error',
                    'message' => 'Expense amount is required.'
                ]);
            }

            if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Expense amount must be greater than zero.'
                ]);
            }

            if (empty($data['expense_date'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Expense date is required.'
                ]);
            }

            $date = \DateTime::createFromFormat(
                'Y-m-d',
                $data['expense_date']
            );

            if (!$date || $date->format('Y-m-d') !== $data['expense_date']) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Invalid expense date.'
                ]);
            }

            if ($this->DailyExpenses->save($data)) {
                $this->json([
                    'status' => 'success',
                    'message' => 'Expense updated successfully.'
                ]);
            }

            $this->json([
                'status' => 'error',
                'message' => 'Unable to update expense.'
            ]);
        }

        if (!$expense) {
            $this->json([
                'status' => 'error',
                'message' => 'Expense not found.'
            ]);
        }

        $this->json($expense);
    }

    public function delete($id)
    {
        if (empty($id)) {
            $this->json([
                'status' => 'error',
                'message' => 'Expense ID is required.'
            ]);
        }

        if ($this->DailyExpenses->delete($id)) {
            $this->json([
                'status' => 'success',
                'message' => 'Expense deleted successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete expense.'
        ]);
    }

    public function daily($date = null)
    {
        $date = $date ?: date('Y-m-d');

        $this->json([
            'data' => $this->DailyExpenses->dailyTotal($date)
        ]);
    }

    public function weekly($startDate = null, $endDate = null)
    {
        if (!$startDate || !$endDate) {

            $today = new \DateTime();

            $dayOfWeek = (int) $today->format('N');

            $start = clone $today;
            $start->modify('-' . ($dayOfWeek - 1) . ' days');

            $end = clone $start;
            $end->modify('+6 days');

            $startDate = $start->format('Y-m-d');
            $endDate = $end->format('Y-m-d');
        }

        $this->json([
            'data' => $this->DailyExpenses->weeklyTotal(
                $startDate,
                $endDate
            )
        ]);
    }

    public function monthly($year = null, $month = null)
    {
        $year = $year ?: date('Y');
        $month = $month ?: date('m');

        $this->json([
            'data' => $this->DailyExpenses->monthlyTotal(
                $year,
                $month
            )
        ]);
    }
}