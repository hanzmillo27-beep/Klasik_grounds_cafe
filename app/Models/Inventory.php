<?php

namespace Models;

use Core\Auth;
use Core\Model;

class Inventory extends Model
{
    public function __construct(?\PDO $connection = null)
    {
        if ($connection === null) parent::__construct();
        else $this->db = $connection;
    }

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
        return $this->mutate(function () use ($data): bool {
            $id = !empty($data['inv_id']) ? (int) $data['inv_id'] : null;
            $before = $id === null ? null : $this->lockedItem($id);
            if ($id !== null && !$before) return false;
            $after = (object) [
                'inv_id' => $id, 'inv_name' => $data['inv_name'], 'unit' => $data['unit'],
                'amount' => number_format((float) $data['amount'], 3, '.', ''),
                'min_stock' => number_format((float) ($data['min_stock'] ?? ($before->min_stock ?? 10)), 3, '.', ''),
            ];
            if ($before && $before->inv_name === $after->inv_name && $before->unit === $after->unit
                && self::units($before->amount) === self::units($after->amount)
                && self::units($before->min_stock) === self::units($after->min_stock)) return true;
            if ($before) {
                $stmt = $this->db->prepare('UPDATE inventory SET inv_name = ?, amount = ?, min_stock = ?, unit = ? WHERE inv_id = ?');
                $stmt->execute([$after->inv_name, $after->amount, $after->min_stock, $after->unit, $id]);
                if ($stmt->rowCount() !== 1) return false;
            } else {
                $stmt = $this->db->prepare('INSERT INTO inventory (inv_name, amount, min_stock, unit) VALUES (?, ?, ?, ?)');
                $stmt->execute([$after->inv_name, $after->amount, $after->min_stock, $after->unit]);
                $after->inv_id = (int) $this->db->lastInsertId();
            }
            $this->recordChange($before ? 'adjustment' : 'created', $before, $after);
            return true;
        });
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
        return $this->mutate(function () use ($id, $amount): bool {
            $before = $this->lockedItem($id);
            if (!$before) return false;
            $newUnits = self::units($before->amount) + self::units($amount);
            if ($newUnits > 999999999999) return false;
            $after = clone $before;
            $after->amount = number_format($newUnits / 1000, 3, '.', '');
            $stmt = $this->db->prepare('UPDATE inventory SET amount = ? WHERE inv_id = ? AND amount = ?');
            $stmt->execute([$after->amount, $id, $before->amount]);
            if ($stmt->rowCount() !== 1) return false;
            $this->recordChange('restock', $before, $after);
            return true;
        });
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
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) return false;
        return $this->mutate(function () use ($id): bool {
            $before = $this->lockedItem($id);
            if (!$before) return false;
            $stmt = $this->db->prepare('DELETE FROM inventory WHERE inv_id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) return false;
            $this->recordChange('deleted', $before, null);
            return true;
        });
    }

    public function history(array $query): array
    {
        $source = '(SELECT history_id, inv_id, inv_name, unit, movement_type,
                amount_before, quantity_change, amount_after, min_stock, previous_inv_name,
                previous_unit, previous_min_stock, actor_id, actor_name, sale_id, created_at,
                \'stock\' AS entry_kind, NULL AS sold_quantity
            FROM inventory_history
            UNION ALL
            SELECT si.sale_item_id AS history_id, NULL AS inv_id, si.product_name AS inv_name,
                NULL AS unit, \'sale\' AS movement_type, NULL AS amount_before,
                NULL AS quantity_change, NULL AS amount_after, NULL AS min_stock,
                NULL AS previous_inv_name, NULL AS previous_unit, NULL AS previous_min_stock,
                s.user_id AS actor_id, u.username AS actor_name, s.sale_id, s.sale_date AS created_at,
                \'sale_record\' AS entry_kind, si.quantity AS sold_quantity
            FROM sale_items si
            INNER JOIN sales s ON s.sale_id = si.sale_id
            LEFT JOIN users u ON u.id = s.user_id
            WHERE NOT EXISTS (SELECT 1 FROM inventory_history captured
                WHERE captured.sale_id = s.sale_id AND captured.movement_type = \'sale\')) history_entries';
        $draw = $this->pageInteger($query['draw'] ?? 0, 0, 2147483647);
        $start = $this->pageInteger($query['start'] ?? 0, 0, 2147483647);
        $length = $this->pageInteger($query['length'] ?? 10, 1, 100);
        $conditions = [];
        $values = [];
        if (isset($query['inv_id']) && $query['inv_id'] !== '') {
            $conditions[] = 'inv_id = ?';
            $values[] = $this->pageInteger($query['inv_id'], 1, PHP_INT_MAX);
        }
        $scope = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM ' . $source . $scope);
        $count->execute($values);
        $total = (int) $count->fetchColumn();

        $type = $query['movement_type'] ?? '';
        if (!is_string($type) || !in_array($type, ['', 'created', 'restock', 'adjustment', 'sale', 'deleted'], true)) {
            throw new \InvalidArgumentException('Choose a valid inventory history action.');
        }
        if ($type !== '') {
            $conditions[] = 'movement_type = ?';
            $values[] = $type;
        }
        $search = $query['search'] ?? [];
        if (!is_array($search) || !is_string($search['value'] ?? '')) {
            throw new \InvalidArgumentException('Enter valid inventory history search text.');
        }
        $term = trim($search['value'] ?? '');
        if (strlen($term) > 150) throw new \InvalidArgumentException('Search using at most 150 characters.');
        if ($term !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
            $matches = [];
            foreach (['inv_name', 'previous_inv_name', 'unit', 'actor_name', 'movement_type', 'CAST(sale_id AS TEXT)', 'CAST(inv_id AS TEXT)'] as $column) {
                $matches[] = 'LOWER(' . $column . ") LIKE LOWER(?) ESCAPE '!'";
                $values[] = $pattern;
            }
            $conditions[] = '(' . implode(' OR ', $matches) . ')';
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM ' . $source . $where);
        $count->execute($values);
        $filtered = (int) $count->fetchColumn();
        // Ordering is fixed so a growing ledger always opens with the newest changes.
        $stmt = $this->db->prepare('SELECT history_id, inv_id, inv_name, unit, movement_type,
            amount_before, quantity_change, amount_after, min_stock, previous_inv_name,
            previous_unit, previous_min_stock, actor_id, actor_name, sale_id, created_at, entry_kind, sold_quantity
            FROM ' . $source . $where . ' ORDER BY created_at DESC, entry_kind, history_id DESC
            LIMIT ' . $length . ' OFFSET ' . $start);
        $stmt->execute($values);
        return ['draw'=>$draw, 'recordsTotal'=>$total, 'recordsFiltered'=>$filtered, 'data'=>$stmt->fetchAll()];
    }

    public function recordHistory(array $movement): void
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Inventory history must be recorded within the stock transaction.');
        }
        $actor = Auth::user();
        $actorId = filter_var($actor['id'] ?? null, FILTER_VALIDATE_INT);
        $actorName = is_string($actor['username'] ?? null) ? $actor['username'] : null;
        $stmt = $this->db->prepare('INSERT INTO inventory_history
            (inv_id, inv_name, unit, movement_type, amount_before, quantity_change, amount_after,
             min_stock, previous_inv_name, previous_unit, previous_min_stock, actor_id, actor_name, sale_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $saved = $stmt->execute([
            $movement['inv_id'], $movement['inv_name'], $movement['unit'], $movement['movement_type'],
            number_format((float) $movement['amount_before'], 3, '.', ''),
            number_format((float) $movement['quantity_change'], 3, '.', ''),
            number_format((float) $movement['amount_after'], 3, '.', ''),
            number_format((float) $movement['min_stock'], 3, '.', ''),
            $movement['previous_inv_name'] ?? null, $movement['previous_unit'] ?? null,
            isset($movement['previous_min_stock']) ? number_format((float) $movement['previous_min_stock'], 3, '.', '') : null,
            $actorId && $actorId > 0 ? $actorId : null, $actorName, $movement['sale_id'] ?? null,
            (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'),
        ]);
        if (!$saved) throw new \RuntimeException('Unable to record inventory history.');
    }

    private function pageInteger($value, int $minimum, int $maximum): int
    {
        if ((!is_string($value) && !is_int($value))
            || !preg_match('/^\d+$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT) === false
            || $value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException('Invalid inventory history page or item.');
        }
        return (int) $value;
    }

    private function lockedItem(int $id): ?object
    {
        $stmt = $this->db->prepare('SELECT inv_id, inv_name, amount, min_stock, unit FROM inventory WHERE inv_id = ?' . $this->lockClause());
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private static function units($amount): int
    {
        return (int) round((float) $amount * 1000);
    }

    private function recordChange(string $type, ?object $before, ?object $after): void
    {
        $item = $after ?? $before;
        $beforeUnits = $before ? self::units($before->amount) : 0;
        $afterUnits = $after ? self::units($after->amount) : 0;
        $this->recordHistory([
            'inv_id' => $item->inv_id, 'inv_name' => $item->inv_name, 'unit' => $item->unit,
            'movement_type' => $type, 'amount_before' => $beforeUnits / 1000,
            'quantity_change' => ($afterUnits - $beforeUnits) / 1000, 'amount_after' => $afterUnits / 1000,
            'min_stock' => $item->min_stock, 'previous_inv_name' => $before->inv_name ?? null,
            'previous_unit' => $before->unit ?? null, 'previous_min_stock' => $before->min_stock ?? null,
        ]);
    }

    private function mutate(callable $operation): bool
    {
        $started = false;
        try {
            $this->db->beginTransaction();
            $started = true;
            $saved = $operation();
            if ($saved) $this->db->commit();
            else $this->db->rollBack();
            return $saved;
        } catch (\Throwable $error) {
            if ($started && $this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }
}
