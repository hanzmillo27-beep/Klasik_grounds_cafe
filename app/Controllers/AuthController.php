<?php

namespace Controllers;

use Core\BaseController;
use Models\Users;


class AuthController extends BaseController{

    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function index()
    {
        $users = $this->Users->find();
        $count = count($users);
        if ($count === 0) {
            $this->redirect(BASE_URL . '/Users/register');
        }
        
        $this->view('Auth/index', [
            'title' => 'Login',
        ], 'auth');
    }


    public  function login()
    {
         if ($_SERVER['REQUEST_METHOD'] === 'POST'){
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $_SESSION['error'] = 'Username and password are required.';
                $this->redirect(BASE_URL . '/Auth');
            }

            $user = $this->Users->findByUsername($username);
            
            if (!$user || !password_verify($password, $user->password)) {
                $_SESSION['error'] = 'Invalid username or password.';
                $this->redirect(BASE_URL . '/Auth');
            }
        
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
            ];
            $destination = [
                'owner' => '/Dashboard',
                'admin' => '/Users',
                'staff' => '/Products',
                'cashier' => '/POS',
                'user' => '/Kiosk',
                'loyal_customer' => '/Loyalty'
            ][$user->role] ?? '/Auth';
            $this->redirect(BASE_URL . $destination);
         }
    }


    public function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect(BASE_URL . '/Auth');
    }
}
