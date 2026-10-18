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
        if ($this->validationError($data) !== null) return false;
        $data['inv_name'] = trim($data['inv_name']);
        $data['unit'] = trim($data['unit']);
        $data['amount'] = round((float) $data['amount'], 3);
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

    public function validationError(array $data): ?string
    {
        if (!is_string($data['inv_name'] ?? null) || trim($data['inv_name']) === '' || strlen(trim($data['inv_name'])) > 150) return 'Enter an item name of up to 150 characters.';
        if (!is_string($data['unit'] ?? null) || trim($data['unit']) === '' || strlen(trim($data['unit'])) > 30) return 'Enter a unit of up to 30 characters.';
        $amount = $data['amount'] ?? null;
        if (!self::validQuantity($amount)) return 'Enter a nonnegative quantity with at most three decimal places.';
        $id = $data['inv_id'] ?? null;
        if ($id !== null && $id !== '' && (!filter_var($id, FILTER_VALIDATE_INT) || $id < 1 || !$this->findByID($id))) return 'Inventory item not found.';
        $stmt = $this->db->prepare('SELECT inv_id FROM inventory WHERE LOWER(inv_name) = LOWER(?) AND inv_id <> ?');
        $stmt->execute([trim($data['inv_name']), $id ?: 0]);
        if ($stmt->fetch()) return 'An inventory item with this name already exists.';
        return null;
    }

    public function restock($id, $amount): bool
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id || $id < 1 || !self::validQuantity($amount, false)) return false;
        $amount = number_format((float) $amount, 3, '.', '');
        $stmt = $this->db->prepare(
            'UPDATE inventory
             SET amount = amount + ?
             WHERE inv_id = ? AND amount <= 999999999.999 - ?'
        );

        $stmt->execute([$amount, $id, $amount]);
        return $stmt->rowCount() === 1;
    }

    public static function validQuantity($amount, bool $allowZero = true): bool
    {
        return (is_string($amount) || is_int($amount) || is_float($amount))
            && preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', (string) $amount)
            && is_finite((float) $amount) && $amount <= 999999999.999
            && ($allowZero || $amount >= 0.001);
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
