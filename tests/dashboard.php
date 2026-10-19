<?php
date_default_timezone_set('Asia/Manila');
require __DIR__ . '/product_fixture.php';

$db->exec('CREATE TABLE daily_expenses (
    expense_id ' . $identity . ', expense_name VARCHAR(150) NOT NULL,
    amount NUMERIC(10,2) NOT NULL, expense_date DATE NOT NULL
)');
$db->exec('CREATE TABLE sales (total_amount NUMERIC(10,2) NOT NULL, sale_date TIMESTAMP NOT NULL)');
$today = date('Y-m-d');
$insert = $db->prepare('INSERT INTO daily_expenses (expense_name, amount, expense_date) VALUES (?, ?, ?)');
for ($number = 1; $number <= 6; $number++) $insert->execute(['Expense ' . $number, $number * 1.25, $today]);
$insert->execute(['Older expense', '9.99', date('Y-m-d', strtotime('-1 day'))]);
$db->prepare('INSERT INTO sales (total_amount, sale_date) VALUES (?, ?)')->execute(['100.00', $today . ' 12:00:00']);
$db->exec('UPDATE inventory SET amount = 0.001, min_stock = 0.001 WHERE inv_id = 1');

$dashboard = new Models\Dashboard();
$recent = $dashboard->recentExpenses();
check(count($recent) === 5 && array_map(function ($row) { return $row->expense_name; }, $recent)
    === ['Expense 6', 'Expense 5', 'Expense 4', 'Expense 3', 'Expense 2'],
    'Recent expenses must use expense fields and return the latest five records in stable order.');

class TestDashboardPage extends Controllers\DashboardController {
    public $pageData;
    public $html;
    public function view($view, $data = [], $layout = 'default') {
        $this->pageData = $data;
        extract($data);
        ob_start();
        require dirname(__DIR__) . '/app/views/' . $view . '.php';
        $this->html = ob_get_clean();
    }
}
$page = new TestDashboardPage();
$page->index();
check(abs($page->pageData['expenseSummary']['today'] - 26.25) < 0.00001
    && (int) $page->pageData['inventorySummary']->low_stock === 1
    && count($page->pageData['recentExpenses']) === 5
    && count($page->pageData['weeklyNetProfit']['values']) >= 1,
    'The dashboard controller must run all five model queries with expense and inventory schemas separated.');
check(strpos($page->html, 'Expense 6') !== false && strpos($page->html, 'Beans') !== false
    && strpos($page->html, '0.001') !== false && strpos($page->html, 'Minimum stock') !== false,
    'The dashboard view must render recent expenses and precise inventory minimums together.');
echo "Dashboard passed: all controller queries and the real view render against separate expense/inventory schemas.\n";
