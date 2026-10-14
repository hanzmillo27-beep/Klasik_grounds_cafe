<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class POSController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $this->view('POS/index', [
            'title' => 'POS'
        ]);
    }
}