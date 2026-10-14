<?php

namespace Api\Products;

use Core\BaseController;
use Models\Products;

class ProductsController extends BaseController
{
    private $Products;

    public function __construct()
    {
        $this->Products = new Products();
    }

    public function all()
    {
        $this->json([
            'data' => $this->Products->find()
        ]);
    }

    public function categories()
    {
        $this->json([
            'data' => $this->Products->categories()
        ]);
    }

    public function inventory()
    {
        $this->json([
            'data' => $this->Products->inventory()
        ]);
    }

    public function add()
    {
        $data = $_POST;

        if (empty($data['product_name'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Product name is required.'
            ]);
        }

        if (empty($data['category_id'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Category is required.'
            ]);
        }

        if (!isset($data['product_price']) || $data['product_price'] === '') {
            $this->json([
                'status' => 'error',
                'message' => 'Product price is required.'
            ]);
        }

        if ($this->Products->save($data)) {
            $this->json([
                'status' => 'success',
                'message' => empty($data['product_id'])
                    ? 'Product added successfully.'
                    : 'Product updated successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to save product.'
        ]);
    }

    public function edit($id)
    {
        $product = $this->Products->findByID($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = $_POST;

            $data['product_id'] = $id;

            if (empty($data['product_name'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Product name is required.'
                ]);
            }

            if (empty($data['category_id'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Category is required.'
                ]);
            }

            if (!isset($data['product_price']) || $data['product_price'] === '') {
                $this->json([
                    'status' => 'error',
                    'message' => 'Product price is required.'
                ]);
            }

            if ($this->Products->save($data)) {
                $this->json([
                    'status' => 'success',
                    'message' => 'Product updated successfully.'
                ]);
            }

            $this->json([
                'status' => 'error',
                'message' => 'Unable to update product.'
            ]);
        }

        if (!$product) {
            $this->json([
                'status' => 'error',
                'message' => 'Product not found.'
            ]);
        }

        $this->json($product);
    }

    public function delete($id)
{
    if (empty($id)) {
        $this->json([
            'status' => 'error',
            'message' => 'Product ID is required.'
        ]);
    }

    $result = $this->Products->delete($id);

    if (!$result) {
        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete product.'
        ]);
    }

    $this->json([
        'status' => 'success',
        'message' => 'Product deleted successfully.'
    ]);
}
}