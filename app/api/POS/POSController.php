<?php

namespace Api\POS;

use Core\BaseController;
use Models\POS;

class POSController extends BaseController
{
    private $POS;

    public function __construct()
    {
        $this->POS = new POS();
    }

    public function products()
    {
        $this->json([
            'data' => $this->POS->products()
        ]);
    }

    public function product($id)
    {
        $product = $this->POS->product($id);

        if (!$product) {
            $this->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ]);
        }

        $this->json($product);
    }

    public function transactions()
    {
        $this->json([
            'data' => $this->POS->transactions()
        ]);
    }

    public function transaction($id)
    {
        if (empty($id)) {
            $this->json([
                'status' => 'error',
                'message' => 'Transaction ID is required.'
            ]);
        }

        $transaction = $this->POS->transaction($id);

        if (!$transaction) {
            $this->json([
                'status' => 'error',
                'message' => 'Transaction not found.'
            ]);
        }

        $this->json([
            'status' => 'success',
            'data' => $transaction
        ]);
    }

    public function checkout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to checkout.']);
        }
        $data = $_POST;

        if (empty($data['items'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Cart is empty.'
            ]);
        }

        $items = is_string($data['items']) ? json_decode($data['items'], true) : null;

        if (!is_array($items) || count($items) === 0) {
            $this->json([
                'status' => 'error',
                'message' => 'Cart is empty.'
            ]);
        }

        $data['items'] = $items;

        try {
            $result = $this->POS->checkout($data);
        } catch (\Throwable $e) {
            $this->json(['status'=>'error','message'=>$e->getMessage(),
                'code'=>strpos($e->getMessage(), 'Order total changed.') === 0 ? 'review_prices' : null]);
            return;
        }
        $message = 'Sale completed successfully.';
        if ($result['loyalty_point_earned']) $message .= ' 1 loyalty point was added.';
        elseif ($result['loyalty_customer']) $message .= ' The daily loyalty point has already been earned.';
        $this->json([
            'status'=>'success', 'message'=>$message,
            'sale_id'=>$result['sale_id'], 'total_amount'=>$result['total_amount'],
            'amount_paid'=>$result['amount_paid'],
            'loyalty_point_earned'=>$result['loyalty_point_earned']
        ]);
    }
}
