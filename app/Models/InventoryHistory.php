<?php
namespace Models;

use Core\Auth;
use Core\Model;

class InventoryHistory extends Model
{
    public function __construct(?\PDO $connection = null)
    {
        if ($connection === null) parent::__construct();
        else $this->db = $connection;
    }

    public function page(array $query): array
    {
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
        $count = $this->db->prepare('SELECT COUNT(*) FROM inventory_history' . $scope);
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
        $count = $this->db->prepare('SELECT COUNT(*) FROM inventory_history' . $where);
        $count->execute($values);
        $filtered = (int) $count->fetchColumn();
        // Ordering is fixed so a growing ledger always opens with the newest changes.
        $stmt = $this->db->prepare('SELECT history_id, inv_id, inv_name, unit, movement_type,
            amount_before, quantity_change, amount_after, min_stock, previous_inv_name,
            previous_unit, previous_min_stock, actor_id, actor_name, sale_id, created_at
            FROM inventory_history' . $where . ' ORDER BY created_at DESC, history_id DESC
            LIMIT ' . $length . ' OFFSET ' . $start);
        $stmt->execute($values);
        return ['draw'=>$draw, 'recordsTotal'=>$total, 'recordsFiltered'=>$filtered, 'data'=>$stmt->fetchAll()];
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

    public function record(array $movement): void
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
}
