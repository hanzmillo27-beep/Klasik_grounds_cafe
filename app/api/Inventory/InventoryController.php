<?php

namespace Api\Inventory;

use Core\BaseController;
use Models\Inventory;

class InventoryController extends BaseController
{
    private $Inventory;

    public function __construct()
    {
        $this->Inventory = new Inventory();
    }

    public function all()
    {
        $this->json([
            'data' => $this->Inventory->find()
        ]);
    }

    public function add()
    {
        $data = $_POST;

        if (empty($data['inv_name'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Item name is required.'
            ]);
        }

        if (!isset($data['amount']) || $data['amount'] === '') {
            $this->json([
                'status' => 'error',
                'message' => 'Quantity is required.'
            ]);
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            $this->json([
                'status' => 'error',
                'message' => 'Amount must be greater than zero.'
            ]);
        }

        if (empty($data['unit'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Quantity unit is required.'
            ]);
        }

        if ($this->Inventory->save($data)) {
            $this->json([
                'status' => 'success',
                'message' => empty($data['inv_id'])
                    ? 'Inventory item added successfully.'
                    : 'Inventory item updated successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to save inventory item.'
        ]);
    }

    public function restock($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->json([
                'status' => 'error',
                'message' => 'Use POST to replenish stock.'
            ]);
        }

        $inventory = $this->Inventory->findByID($id);
        if (!$inventory) {
            http_response_code(404);
            $this->json([
                'status' => 'error',
                'message' => 'Inventory item not found.'
            ]);
        }

        $amount = $_POST['amount'] ?? '';
        if (!is_numeric($amount) || !is_finite((float) $amount) || (float) $amount < 0.001) {
            $this->json([
                'status' => 'error',
                'message' => 'Amount to add must be at least 0.001.'
            ]);
        }

        if (!$this->Inventory->restock($id, $amount)) {
            $this->json([
                'status' => 'error',
                'message' => 'Unable to replenish stock.'
            ]);
        }

        $this->json([
            'status' => 'success',
            'message' => 'Stock replenished successfully.',
            'data' => $this->Inventory->findByID($id)
        ]);
    }

    public function edit($id)
    {
        $inventory = $this->Inventory->findByID($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = $_POST;
            $data['inv_id'] = $id;

            if (empty($data['inv_name'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Item name is required.'
                ]);
            }

            if (!isset($data['amount']) || $data['amount'] === '') {
                $this->json([
                    'status' => 'error',
                    'message' => 'Quantity is required.'
                ]);
            }

            if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Amount must be greater than zero.'
                ]);
            }

            if (empty($data['unit'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Quantity unit is required.'
                ]);
            }

            if ($this->Inventory->save($data)) {
                $this->json([
                    'status' => 'success',
                    'message' => 'Inventory item updated successfully.'
                ]);
            }

            $this->json([
                'status' => 'error',
                'message' => 'Unable to update inventory item.'
            ]);
        }

        if (!$inventory) {
            $this->json([
                'status' => 'error',
                'message' => 'Inventory item not found.'
            ]);
        }

        $this->json($inventory);
    }

    public function delete($id)
    {
        if (empty($id)) {
            $this->json([
                'status' => 'error',
                'message' => 'Inventory ID is required.'
            ]);
        }

        if ($this->Inventory->delete($id)) {
            $this->json([
                'status' => 'success',
                'message' => 'Inventory item deleted successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete inventory item.'
        ]);
    }
}
