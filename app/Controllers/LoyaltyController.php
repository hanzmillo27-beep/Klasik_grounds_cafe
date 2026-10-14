<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;

class LoyaltyController extends BaseController
{
    public function index()
    {
        Auth::requireRole(['loyal_customer']);

        $this->view('Loyalty/index', [
            'title' => 'Loyalty'
        ]);
    }
}
