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
                min_stock,
                unit
             FROM inventory
             ORDER BY inv_name'
        );

        $items = $stmt->fetchAll();
        foreach ($items as $item) $item->is_low = (float) $item->amount <= (float) $item->min_stock;
        return $items;
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                inv_id,
                inv_name,
                amount,
                min_stock,
                unit
             FROM inventory
             WHERE inv_id = ?'
        );

        $stmt->execute([$id]);

        $item = $stmt->fetch() ?: null;
        if ($item) $item->is_low = (float) $item->amount <= (float) $item->min_stock;
        return $item;
    }

    public function save($data)
    {
        if ($this->validationError($data) !== null) return false;
        $data['inv_name'] = trim($data['inv_name']);
        $data['unit'] = trim($data['unit']);
        $data['amount'] = round((float) $data['amount'], 3);
        $minimum = $data['min_stock'] ?? (!empty($data['inv_id']) ? $this->findByID($data['inv_id'])->min_stock : 10);
        if (!empty($data['inv_id'])) {

            $stmt = $this->db->prepare(
                'UPDATE inventory
                 SET inv_name = ?,
                     amount = ?,
                     min_stock = ?,
                     unit = ?
                 WHERE inv_id = ?'
            );

            return $stmt->execute([
                $data['inv_name'],
                $data['amount'],
                $minimum,
                $data['unit'],
                $data['inv_id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO inventory
            (
                inv_name,
                amount,
                min_stock,
                unit
            )
            VALUES (?, ?, ?, ?)'
        );

        return $stmt->execute([
            $data['inv_name'],
            $data['amount'],
            $minimum,
            $data['unit'],
        ]);
    }

    public function validationError(array $data): ?string
    {
        if (!is_string($data['inv_name'] ?? null) || trim($data['inv_name']) === '' || strlen(trim($data['inv_name'])) > 150) return 'Enter an item name of up to 150 characters.';
        if (!is_string($data['unit'] ?? null) || trim($data['unit']) === '' || strlen(trim($data['unit'])) > 30) return 'Enter a unit of up to 30 characters.';
        $amount = $data['amount'] ?? null;
        if (!self::validQuantity($amount)) return 'Enter a nonnegative quantity with at most three decimal places.';
        if (array_key_exists('min_stock', $data) && !self::validQuantity($data['min_stock'])) return 'Enter a nonnegative minimum stock with at most three decimal places.';
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
             SET amount = ROUND(amount + ?, 3)
             WHERE inv_id = ? AND amount <= ROUND(999999999.999 - ?, 3)'
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
