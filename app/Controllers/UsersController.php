<?php
namespace Controllers;

use Core\BaseController;
use Models\Users;
use Core\Auth;

class UsersController extends BaseController
{
    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }
    
    public function index()
    {
        Auth::requireLogin();
        $this->view('Users/index', [
            'title' => 'Users'
        ]);
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($username === '' || $password === '') {
                $_SESSION['error'] = 'All required fields must be completed.';
                $this->redirect(BASE_URL . '/Users/register');
            }

            if (strlen($password) < 4) {
                $_SESSION['error'] = 'Password must contain at least 4 characters.';
                $this->redirect(BASE_URL . '/Users/register');
            }

            if ($password !== $confirm) {
                $_SESSION['error'] = 'Passwords do not match.';
                $this->redirect(BASE_URL . '/Users/register');
            }

            if ($this->Users->usernameExists($username)) {
                $_SESSION['error'] = 'Username is already taken.';
                $this->redirect(BASE_URL . '/Users/register');
            }

            $role = count($this->Users->find()) === 0 ? 'owner' : 'user';
            if (!$this->Users->save([
                'username' => $username,
                'password' => $password,
                'role' => $role,
            ])) {
                $_SESSION['error'] = 'Unable to create the account.';
                $this->redirect(BASE_URL . '/Users/register');
            }

            $_SESSION['success'] = 'Registration successful. You may now log in.';

            $this->redirect(BASE_URL . '/Auth');
        }

        $this->view('Users/register', [
            'title' => 'Register',
        ], 'auth');
    }
}
