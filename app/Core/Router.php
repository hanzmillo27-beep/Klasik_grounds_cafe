<?php

namespace Core;

class Router
{
    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = parse_url(BASE_URL, PHP_URL_PATH);

        $uri = str_replace($base, '', $uri);
        $uri = trim($uri, '/');

        $parts = $uri ? explode('/', $uri) : [];

        if (!$parts) {
            $controllerName = 'Controllers\\DashboardController';
            $action = 'index';
            $params = [];
        } else {
            $folder = ucfirst($parts[0]);
            $resource = $parts[1] ?? null;

            $folderPath = dirname(__DIR__) . '/' . $folder;

            if (is_dir($folderPath) && $resource) {
                $controllerName =
                    $folder . '\\' .
                    ucfirst($resource) . '\\' .
                    ucfirst($resource) . 'Controller';

                $action = $parts[2] ?? 'index';
                $params = array_slice($parts, 3);
            } else {
                $controllerName =
                    'Controllers\\' .
                    $folder . 'Controller';

                $action = $parts[1] ?? 'index';
                $params = array_slice($parts, 2);
            }
        }

        if (!class_exists($controllerName)) {
            http_response_code(404);
            exit('Controller not found.');
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            http_response_code(404);
            exit('Page not found.');
        }

        $area = str_starts_with($controllerName, 'Api\\') ? 'Api' : 'Controllers';
        $resource = $area === 'Api' ? ($parts[1] ?? '') : ($parts[0] ?? 'Dashboard');
        \Core\Auth::authorizeRoute($area, $resource, $action);

        $controller->$action(...$params);
    }
}
