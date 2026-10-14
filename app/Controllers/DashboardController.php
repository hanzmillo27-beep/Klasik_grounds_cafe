<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;
use Models\Dashboard;

class DashboardController extends BaseController
{
    private $Dashboard;

    public function __construct()
    {
        $this->Dashboard = new Dashboard();
    }

    public function index()
    {
        Auth::requireLogin();

        $this->view('Dashboard/index', [
            'title' => 'Dashboard',
            'weeklyNetProfit' =>
                $this->Dashboard->weeklyNetProfit(),
            'expenseSummary' =>
                $this->Dashboard->expenseSummary(),
            'inventorySummary' =>
                $this->Dashboard->inventorySummary(),
            'recentExpenses' =>
                $this->Dashboard->recentExpenses(),
            'inventory' =>
                $this->Dashboard->inventory()
        ]);
    }
}
