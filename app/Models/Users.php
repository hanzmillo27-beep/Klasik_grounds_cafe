<?php

namespace Models;

use Core\Model;

class Users extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT id, username, role, is_active
             FROM users
             ORDER BY username'
        );

        $users = $stmt->fetchAll();
        foreach ($users as $user) $user->is_active = self::isActive($user);
        return $users;
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, role, is_active
             FROM users
             WHERE id = ?'
        );

        $stmt->execute([$id]);

        $user = $stmt->fetch();
        if ($user) $user->is_active = self::isActive($user);
        return $user ?: null;
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare(
            'SELECT id, username,password, role, is_active
             FROM users
             WHERE username = ?'
        );

        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public function usernameExists($username, $excludeId = null)
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $params = [$username];
        if ($excludeId !== null) { $sql .= ' AND id <> ?'; $params[] = $excludeId; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function save($data)
    {
        $now = date('Y-m-d H:i:s');

        if (!empty($data['id'])) {
            if (!empty($data['password'])) {

                $stmt = $this->db->prepare(
                    'UPDATE users
                     SET username = ?, password = ?, role = ?, modified = ?
                     WHERE id = ?'
                );
                return $stmt->execute([
                    $data['username'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['role'],
                    $now,
                    $data['id'],
                ]);
            }

            $stmt = $this->db->prepare(
                'UPDATE users
                 SET username = ?, role = ?, modified = ?
                 WHERE id = ?'
            );

            return $stmt->execute([
                $data['username'],
                $data['role'],
                $now,
                $data['id'],
            ]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users
             (username, password, role, created, modified)
             VALUES (?, ?, ?, ?, ?)'
        );

        return $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            $now,
            $now,
        ]);
    }

    public function delete($id): bool
    {
        return $this->setActive($id, false);
    }

    public static function isActive($user): bool {
        return in_array($user->is_active ?? true, [true, 1, '1', 't', 'true'], true);
    }

    public function setActive($id, bool $active): bool {
        $stmt = $this->db->prepare('UPDATE users SET is_active = ?, modified = ? WHERE id = ?');
        $stmt->bindValue(1, $active, \PDO::PARAM_BOOL);
        $stmt->bindValue(2, date('Y-m-d H:i:s'));
        $stmt->bindValue(3, (int) $id, \PDO::PARAM_INT);
        return $stmt->execute();
    }
}
