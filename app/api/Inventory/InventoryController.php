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

    public function history()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            $this->json(['status'=>'error', 'message'=>'Use GET to load inventory history.']);
            return;
        }
        try {
            $result = $this->Inventory->history($_GET);
        } catch (\InvalidArgumentException $error) {
            http_response_code(400);
            $this->json(['status'=>'error', 'message'=>$error->getMessage()]);
            return;
        } catch (\PDOException $error) {
            http_response_code(500);
            $this->json(['status'=>'error', 'message'=>'Unable to load inventory history. Please try again.']);
            return;
        }
        $this->json($result);
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

        $id = filter_var($id, FILTER_VALIDATE_INT);
        $inventory = $id && $id > 0 ? $this->Inventory->findByID($id) : null;
        if (!$inventory) {
            http_response_code(404);
            $this->json([
                'status' => 'error',
                'message' => 'Inventory item not found.'
            ]);
        }

        $amount = $_POST['amount'] ?? '';
        if (!Inventory::validQuantity($amount, false)) {
            $this->json([
                'status' => 'error',
                'message' => 'Enter stock received from 0.001 to 999999999.999, with at most three decimal places.'
            ]);
        }

        try { $restocked = $this->Inventory->restock($id, $amount); }
        catch (\PDOException $error) { $restocked = false; }
        if (!$restocked) {
            $this->json([
                'status' => 'error',
                'message' => 'Unable to replenish stock. Check the item and maximum quantity.'
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error', 'message'=>'Use POST to delete inventory.']);
        }
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            $this->json([
                'status' => 'error',
                'message' => 'Inventory ID is required.'
            ]);
        }

        try { $deleted = $this->Inventory->delete($id); }
        catch (\PDOException $error) { $deleted = false; }
        if ($deleted) {
            $this->json([
                'status' => 'success',
                'message' => 'Inventory item deleted successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete inventory item. It may still be used by a product recipe.'
        ]);
    }
}
