<?php

require '../app/config/init.php';
require '../app/autoload.php';

use Core\Router;

$router = new Router();
$router->dispatch();