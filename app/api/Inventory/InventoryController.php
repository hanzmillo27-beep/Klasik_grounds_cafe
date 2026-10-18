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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to save inventory.']);
        }
        $this->saveItem($_POST);
    }

    private function saveItem(array $data): void
    {
        $error = $this->Inventory->validationError($data);
        if ($error !== null) $this->json(['status'=>'error','message'=>$error]);
        try { $saved = $this->Inventory->save($data); }
        catch (\PDOException $error) { $saved = false; }
        $this->json(['status'=>$saved ? 'success' : 'error',
            'message'=>$saved ? 'Inventory item saved successfully.' : 'Unable to save inventory item. Check the name is unique.']);
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
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $inventory = $id && $id > 0 ? $this->Inventory->findByID($id) : null;
        if (!$inventory) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Inventory item not found.']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $data = $_POST;
            $data['inv_id'] = $id;
            $this->saveItem($data);
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
