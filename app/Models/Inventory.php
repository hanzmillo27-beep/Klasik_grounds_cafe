<?php

namespace Models;

use Core\Model;

class Inventory extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT
                inv_id,
                inv_name,
                amount,
                unit
             FROM inventory
             ORDER BY inv_name'
        );

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                inv_id,
                inv_name,
                amount,
                unit
             FROM inventory
             WHERE inv_id = ?'
        );

        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function save($data)
    {
        if (!empty($data['inv_id'])) {

            $stmt = $this->db->prepare(
                'UPDATE inventory
                 SET inv_name = ?,
                     amount = ?,
                     unit = ?
                 WHERE inv_id = ?'
            );

            return $stmt->execute([
                $data['inv_name'],
                $data['amount'],
                $data['unit'],
                $data['inv_id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO inventory
            (
                inv_name,
                amount,
                unit
            )
            VALUES (?, ?, ?)'
        );

        return $stmt->execute([
            $data['inv_name'],
            $data['amount'],
            $data['unit'],
        ]);
    }

    public function restock($id, $amount): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE inventory
             SET amount = amount + ?
             WHERE inv_id = ?'
        );

        return $stmt->execute([$amount, $id]);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM inventory
             WHERE inv_id = ?'
        );

        return $stmt->execute([$id]);
    }
}
