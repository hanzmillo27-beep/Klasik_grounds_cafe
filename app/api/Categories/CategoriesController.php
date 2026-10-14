<?php

namespace Api\Categories;

use Core\BaseController;
use Models\Categories;

class CategoriesController extends BaseController
{
    private $Categories;

    public function __construct()
    {
        $this->Categories = new Categories();
    }

    public function all()
    {
        $this->json([
            'data' => $this->Categories->find()
        ]);
    }

    public function add()
    {
        $data = $_POST;

        if (empty($data['category_name'])) {
            $this->json([
                'status' => 'error',
                'message' => 'Category name is required.'
            ]);
        }

        if ($this->Categories->save($data)) {
            $this->json([
                'status' => 'success',
                'message' => empty($data['category_id'])
                    ? 'Category added successfully.'
                    : 'Category updated successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to save category.'
        ]);
    }

    public function edit($id)
    {
        $category = $this->Categories->findByID($id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $data['category_id'] = $id;

            if (empty($data['category_name'])) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Category name is required.'
                ]);
            }

            if ($this->Categories->save($data)) {
                $this->json([
                    'status' => 'success',
                    'message' => 'Category updated successfully.'
                ]);
            }

            $this->json([
                'status' => 'error',
                'message' => 'Unable to update category.'
            ]);
        }

        if (!$category) {
            $this->json([
                'status' => 'error',
                'message' => 'Category not found.'
            ]);
        }

        $this->json($category);
    }

    public function delete($id)
    {
        if (empty($id)) {
            $this->json([
                'status' => 'error',
                'message' => 'Category ID is required.'
            ]);
        }

        if ($this->Categories->delete($id)) {
            $this->json([
                'status' => 'success',
                'message' => 'Category deleted successfully.'
            ]);
        }

        $this->json([
            'status' => 'error',
            'message' => 'Unable to delete category.'
        ]);
    }
}