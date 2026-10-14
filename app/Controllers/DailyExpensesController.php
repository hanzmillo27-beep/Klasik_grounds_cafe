<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class DailyExpensesController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $this->view('DailyExpenses/index', [
            'title' => 'DailyExpenses'
        ]);
    }
}