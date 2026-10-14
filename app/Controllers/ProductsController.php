<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class ProductsController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $this->view('Products/index', [
            'title' => 'Products'
        ]);
    }
}