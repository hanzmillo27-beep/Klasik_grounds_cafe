<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    public static function connect()
    {
        try {
            $dsn = 'pgsql:host=' . DB_HOST .
                    ';port=' . DB_PORT .
                   ';dbname=' . DB_NAME .
                   ';sslmode=require';

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            ]);

            return $pdo;

        } catch (PDOException $e) {
            die(
            '<pre>' .
            'Database connection failed:' . PHP_EOL .
            $e->getMessage() .
            '</pre>'
            );
        }
    }
}
