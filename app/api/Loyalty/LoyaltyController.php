<?php

namespace Api\Loyalty;

use Core\Auth;
use Core\BaseController;
use Models\Loyalty;

class LoyaltyController extends BaseController
{
    private $Loyalty;

    public function __construct()
    {
        $this->Loyalty = new Loyalty();
    }

    private function customerId(): int
    {
        Auth::requireRole(['loyal_customer']);
        return (int) (Auth::user()['id'] ?? 0);
    }

    public function summary(): void
    {
        $this->json([
            'status' => 'success',
            'data' => $this->Loyalty->summary($this->customerId())
        ]);
    }

    public function redeem(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json([
                'status' => 'error',
                'message' => 'Use POST to redeem a reward.'
            ]);
        }

        try {
            $result = $this->Loyalty->redeem($this->customerId());
            $this->json([
                'status' => 'success',
                'message' => 'Reward issued. Show this code to the cashier to choose your free coffee.',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
