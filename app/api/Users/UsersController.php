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

        if ($this->Users->save($data)) {
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

            if ($this->Users->save($data)) {
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
                'message' => 'Only an owner can delete an owner account.'
            ]);
        }

        if ($this->Users->delete($id)) {
            $this->json([
                'status' => 'success',
                'message' => 'User deleted successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete user.'
        ]);
    }
}
