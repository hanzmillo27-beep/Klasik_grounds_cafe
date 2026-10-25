<?php

namespace Api\Products;

use Core\BaseController;
use Core\SupabaseStorage;
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to save products.']);
        }
        $error = $this->Products->validationError($data);
        if ($error !== null) $this->json(['status'=>'error','message'=>$error]);
        if (!empty($data['product_id']) && (!filter_var($data['product_id'], FILTER_VALIDATE_INT) || !$this->Products->findByID($data['product_id']))) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Product not found.']);
        }

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

        $this->attachUploadedImage($data);

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
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) $this->json(['status'=>'error','message'=>'Invalid product ID.']);
        $product = $this->Products->findByID($id);
        if (!$product) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Product not found.']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $data = $_POST;

            $data['product_id'] = $id;
            $error = $this->Products->validationError($data);
            if ($error !== null) $this->json(['status'=>'error','message'=>$error]);

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

            $this->attachUploadedImage($data);

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

    public function status($id)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to change product status.']);
        }
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $active = $_POST['is_active'] ?? null;
        if (!$id || $id < 1 || !in_array($active, ['0','1',0,1], true)) {
            $this->json(['status'=>'error','message'=>'Choose a valid product status.']);
        }
        if (!$this->Products->setActive($id, (bool) $active)) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Product not found.']);
        }
        $this->json(['status'=>'success','message'=>$active ? 'Product reactivated.' : 'Product deactivated.']);
    }

    public function availability($id)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to change availability.']);
        }
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $available = $_POST['is_available'] ?? null;
        if (!$id || $id < 1 || !in_array($available, ['0','1',0,1], true)) {
            $this->json(['status'=>'error','message'=>'Choose a valid availability.']);
        }
        if (!$this->Products->setAvailable($id, (bool) $available)) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Product not found.']);
        }
        $this->json(['status'=>'success','message'=>$available ? 'Product marked available.' : 'Product marked unavailable.']);
    }

    public function delete($id = null)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->json(['status'=>'error','message'=>'Use POST to delete a product.']);
        }
        $id = is_string($id) || is_int($id) ? filter_var($id, FILTER_VALIDATE_INT) : false;
        if (!$id || $id < 1) {
            http_response_code(400);
            $this->json(['status'=>'error','message'=>'Choose a valid product.']);
        }
        try {
            $deleted = $this->Products->delete($id);
        } catch (\DomainException $error) {
            http_response_code(409);
            $this->json(['status'=>'error','message'=>$error->getMessage()]);
            return;
        } catch (\Throwable $error) {
            http_response_code(500);
            $this->json(['status'=>'error','message'=>'Unable to delete product. Please try again.']);
            return;
        }
        if (!$deleted) {
            http_response_code(404);
            $this->json(['status'=>'error','message'=>'Product not found.']);
        }
        $this->json(['status'=>'success','message'=>'Product deleted successfully.']);
    }
    private function attachUploadedImage(array &$data): void
    {
        if (!isset($_FILES['product_image'])) {
            return;
        }

        try {
            $path = (new SupabaseStorage())->uploadProductImage($_FILES['product_image']);
            if ($path !== null) {
                $data['product_image'] = $path;
            }
        } catch (\RuntimeException $e) {
            $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
