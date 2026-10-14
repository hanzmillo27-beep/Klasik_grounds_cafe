<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class KioskController extends BaseController
{
    public function index()
    {
        $user = Auth::user();
        $isLoyalCustomer = $user && ($user['role'] ?? '') === 'loyal_customer';

        $this->view('Kiosk/index', [
            'title' => 'Kiosk',
            'username' => $user['username'] ?? '',
            'isLoyalCustomer' => $isLoyalCustomer
        ]);
    }
}
