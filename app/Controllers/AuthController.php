<?php

namespace Controllers;

use Core\BaseController;
use Core\RateLimiter;
use Models\Users;


class AuthController extends BaseController{

    public function index()
    {
        $users = (new Users())->find();
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
            $limiter = new RateLimiter();
            $rateLimitKey = 'login:' . RateLimiter::clientIp();
            try {
                $retryAfter = $limiter->retryAfter($rateLimitKey);
            } catch (\RuntimeException $e) {
                $this->rateLimitUnavailable($e);
                return;
            }

            if ($retryAfter > 0) {
                $this->showCooldown($retryAfter);
                return;
            }

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $_SESSION['error'] = 'Username and password are required.';
                $this->redirect(BASE_URL . '/Auth');
            }

            $user = (new Users())->findByUsername($username);
            
            if (!$user || !password_verify($password, $user->password)) {
                try {
                    $retryAfter = $limiter->recordFailure($rateLimitKey);
                } catch (\RuntimeException $e) {
                    $this->rateLimitUnavailable($e);
                    return;
                }
                if ($retryAfter > 0) {
                    $this->showCooldown($retryAfter);
                    return;
                }
                $_SESSION['error'] = 'Invalid username or password.';
                $this->redirect(BASE_URL . '/Auth');
            }

            try {
                $limiter->reset($rateLimitKey);
            } catch (\RuntimeException $e) {
                $this->rateLimitUnavailable($e);
                return;
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

    private function showCooldown(int $seconds): void
    {
        http_response_code(429);
        header('Retry-After: ' . $seconds);
        $_SESSION['error'] = 'Too many failed sign-ins. Please try again in ' . $seconds . ' seconds.';
        $this->view('Auth/index', ['title' => 'Login'], 'auth');
    }

    private function rateLimitUnavailable(\RuntimeException $error): void
    {
        error_log($error->getMessage());
        http_response_code(503);
        header('Retry-After: 30');
        $_SESSION['error'] = 'Sign-in is temporarily unavailable. Please try again in 30 seconds.';
        $this->view('Auth/index', ['title' => 'Login'], 'auth');
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
