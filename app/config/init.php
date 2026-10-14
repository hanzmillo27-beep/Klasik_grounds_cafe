<?php

session_start();

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');


define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/sample');

define('DB_PORT', '6543');
define('DB_HOST', 'aws-0-ap-northeast-2.pooler.supabase.com');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.nwvbeowsjqqlbeajcpsz');
define('DB_PASS', 'A*DsJnZ*2kWk@Ez');
define('DB_CHARSET', 'utf8mb4');


/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'klasik_grounds_cafe');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
*/
date_default_timezone_set('Asia/Manila');
