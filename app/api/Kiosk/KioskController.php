<?php

namespace Api\Kiosk;

use Core\BaseController;
use Core\Auth;
use Models\Kiosk;

class KioskController extends BaseController
{
    private $Kiosk;

    public function __construct()
    {
        $this->Kiosk = new Kiosk();
    }

    private function requirePosAccess()
    {
        Auth::requireLogin();
        Auth::requireRole(['cashier', 'owner']);
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function products()
    {
        $this->json([
            'data' => $this->Kiosk->products()
        ]);
    }

    public function submit()
    {
        $items = json_decode($_POST['items'] ?? '[]', true);

        if (!is_array($items) || !$items) {
            $this->json([
                'status' => 'error',
                'message' => 'Your cart is empty.'
            ]);

            return;
        }

        try {
            $user = Auth::user();
            $customerUserId = $user
                && ($user['role'] ?? '') === 'loyal_customer'
                ? (int) $user['id']
                : null;

            $result = $this->Kiosk->submit([
                'items' => $items,
                'customer_name' => $_POST['customer_name'] ?? '',
                'customer_user_id' => $customerUserId
            ]);

            $this->json([
                'status' => 'success',
                'message' => 'Order submitted. Please pay at the cashier.',
                'reference_code' => $result['reference_code'],
                'total_amount' => $result['total_amount']
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function claim($reference)
    {
       $userId = $this->requirePosAccess();

        try {
            $order = $this->Kiosk->claim($reference, $userId);

            $this->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
