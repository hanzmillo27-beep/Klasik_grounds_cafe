<?php

namespace Models;

use Core\Model;

class Categories extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT category_id, category_name
             FROM categories
             ORDER BY category_name'
        );

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT category_id, category_name
             FROM categories
             WHERE category_id = ?'
        );

        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function save($data)
    {
        if (!empty($data['category_id'])) {
            $stmt = $this->db->prepare(
                'UPDATE categories
                 SET category_name = ?
                 WHERE category_id = ?'
            );

            return $stmt->execute([
                $data['category_name'],
                $data['category_id']
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO categories (category_name)
             VALUES (?)'
        );

        return $stmt->execute([
            $data['category_name']
        ]);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM categories
             WHERE category_id = ?'
        );

        return $stmt->execute([$id]);
    }
}