<?php

namespace Api\Users;

use Core\BaseController;
use Core\Auth;
use Models\Users;

class UsersController extends BaseController
{
    private const ROLES = ['owner', 'admin', 'cashier', 'user', 'loyal_customer'];

    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function all()
    {
        $this->json([
            'data' => $this->Users->find()
        ]);
    }

    public function add()
    {
        $data = $_POST;
        if (!empty($data['id'])) {
            http_response_code(400);
            $this->json(['status'=>'error', 'message'=>'Use the edit action to update an existing account.']);
        }
        $this->guardOwnerRole($data['role'] ?? '');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to create an account.']);
        }
        if (!is_string($data['username'] ?? null) || !is_string($data['password'] ?? null)) {
            $this->json(['status'=>'error','message'=>'Enter a valid username and password.']);
        }
        $data['username'] = trim($data['username']);
        if (strlen($data['username']) > 100 || strlen($data['password']) < 4 || strlen($data['password']) > 72) {
            $this->json(['status'=>'error','message'=>'Use a username up to 100 characters and a password of 4 to 72 characters.']);
        }
        if ($this->Users->usernameExists($data['username'])) {
            $this->json(['status'=>'error','message'=>'Username is already taken.']);
        }

        if (empty($data['username'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Username is required.'
            ]);
        }

        if (empty($data['id']) && empty($data['password'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Password is required.'
            ]);
        }

        if (empty($data['role'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Role is required.'
            ]);
        }

        if (!in_array($data['role'], self::ROLES, true)) {
            $this->json([
                'status' => 'error',
                'message' => 'Invalid role.'
            ]);
        }

        try { $saved = $this->Users->save($data); }
        catch (\PDOException $error) {
            $duplicate = in_array((string) $error->getCode(), ['23000','23505'], true);
            http_response_code($duplicate ? 409 : 500);
            $this->json(['status'=>'error','message'=>$duplicate ? 'Username is already taken.' : 'Unable to save user.']);
        }
        if ($saved) {
            $this->json([
                'status' => 'success',
                'message' => empty($data['id'])
                    ? 'User added successfully.'
                    : 'User updated successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to save user.'
        ]);
    }

    public function edit($id)
    {
         $user = $this->Users->findByID($id);

        if (!$user) {
            $this->json([
                'status' => 'error',
                'message' => 'User not found.'
            ]);
        }

        if (($user->role ?? '') === 'owner' && (Auth::user()['role'] ?? '') !== 'owner') {
            http_response_code(403);
            $this->json([
                'status' => 'error',
                'message' => 'Only an owner can edit an owner account.'
            ]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST'){
            $data = $_POST;
            $this->guardOwnerRole($data['role'] ?? '');
            if (!is_string($data['username'] ?? null) || !is_string($data['password'] ?? '')) {
                $this->json(['status'=>'error','message'=>'Enter a valid username and password.']);
            }
            $data['username'] = trim($data['username']);
            $data['password'] = $data['password'] ?? '';
            if (strlen($data['username']) > 100 || ($data['password'] !== '' && (strlen($data['password']) < 4 || strlen($data['password']) > 72))) {
                $this->json(['status'=>'error','message'=>'Use a username up to 100 characters and a password of 4 to 72 characters.']);
            }
            if ($this->Users->usernameExists($data['username'], $id)) {
                $this->json(['status'=>'error','message'=>'Username is already taken.']);
            }

            if (empty($data['username'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Username is required.'
                ]);
            }


            if (empty($data['role'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Role is required.'
                ]);
            }

            if (!in_array($data['role'], self::ROLES, true)) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Invalid role.'
                ]);
            }

            $data['id'] = $id;

            try { $saved = $this->Users->save($data); }
            catch (\PDOException $error) {
                http_response_code(in_array((string) $error->getCode(), ['23000','23505'], true) ? 409 : 500);
                $this->json(['status'=>'error','message'=>'Unable to update the account. Check that the username is unique.']);
            }
            if ($saved) {
                $this->json([
                    'status' => 'success',
                    'message' => 'User updated successfully.'
                ]);
            }

            $this->json([
                'status' => 'error',
                'message' => 'Unable to save user.'
            ]);
        }
        $this->json($user);
        
    }

    private function guardOwnerRole($role): void
    {
        if ($role === 'owner' && (Auth::user()['role'] ?? '') !== 'owner') {
            http_response_code(403);
            $this->json(['status'=>'error', 'message'=>'Only an owner can assign the owner role.']);
        }
    }

    public function delete($id)
    {
        $_POST['is_active'] = '0';
        $this->status($id);
    }

    public function status($id)
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) $this->json(['status'=>'error','message'=>'Invalid user ID.']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to change account status.']);
        }
        $active = $_POST['is_active'] ?? null;
        if (!in_array($active, ['0','1',0,1], true)) {
            $this->json(['status'=>'error','message'=>'Choose active or inactive status.']);
        }
        $active = (string) $active === '1';
        if (!$active && (int) $id === (int) (Auth::user()['id'] ?? 0)) {
            $this->json(['status'=>'error','message'=>'You cannot deactivate your own account.']);
        }
        if (empty($id)) {
            $this->json([
                'status' => 'error',
                'message' => 'User ID is required.'
            ]);
        }

        $user = $this->Users->findByID($id);
        if (!$user) {
            $this->json([
                'status' => 'error',
                'message' => 'User not found.'
            ]);
        }

        if (($user->role ?? '') === 'owner' && (Auth::user()['role'] ?? '') !== 'owner') {
            http_response_code(403);
            $this->json([
                'status' => 'error',
                'message' => 'Only an owner can change the status of an owner account.'
            ]);
        }

        if ($this->Users->setActive($id, $active)) {
            $this->json([
                'status' => 'success',
                'message' => $active ? 'Account reactivated.' : 'Account deactivated.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to change account status.'
        ]);
    }
}
