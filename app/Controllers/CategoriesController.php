<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class CategoriesController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $this->view('Categories/index', [
            'title' => 'Categories'
        ]);
    }
}