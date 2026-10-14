<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class InventoryController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $this->view('Inventory/index', [
            'title' => 'Inventory'
        ]);
    }
}