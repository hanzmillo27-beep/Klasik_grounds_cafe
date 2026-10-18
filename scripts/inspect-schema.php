<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('session.save_path', sys_get_temp_dir());
require dirname(__DIR__) . '/app/config/init.php';
try {
    $db=new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';sslmode=require',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach (array_slice($argv,1) as $table) {
        if (!preg_match('/^[a-z_]+$/',$table)) throw new RuntimeException('Invalid table name.');
        $query=$db->prepare('SELECT column_name,data_type,udt_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position');
        $query->execute([$table]);
        echo $table, ': ', json_encode($query->fetchAll(PDO::FETCH_ASSOC)), PHP_EOL;
        $query=$db->prepare('SELECT conname,pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid=CAST(? AS regclass)');
        $query->execute([$table]);
        echo 'Constraints: ',json_encode($query->fetchAll(PDO::FETCH_ASSOC)),PHP_EOL;
    }
} catch (Throwable $e) { fwrite(STDERR,'Schema check failed.'.PHP_EOL); exit(1); }
