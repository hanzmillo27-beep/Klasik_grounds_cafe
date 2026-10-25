<?php

namespace Core;

class Auth{

    /** Role permissions are checked by the router for every page and API request. */
    private const ROLE_ACCESS = [
        'owner' => ['*'],
        'admin' => ['Users'],
        'cashier' => ['POS', 'Dashboard', 'Products', 'Inventory'],
        'staff' => ['Dashboard', 'Products', 'Inventory', 'Reports'],
        'user' => ['Kiosk'],
        'loyal_customer' => ['Loyalty', 'Kiosk'],
    ];

    public static function check()
    {
        return !empty($_SESSION['user']);
    }

    public static function roles(): array { return array_keys(self::ROLE_ACCESS); }

    public static function user()
    {
        return $_SESSION['user'] ?? null;
    }

    public static function refresh(): void
    {
        if (!self::check()) return;
        $user = (new \Models\Users())->findByID($_SESSION['user']['id'] ?? 0);
        if (!$user || !\Models\Users::isActive($user)) { unset($_SESSION['user']); return; }
        $_SESSION['user'] = ['id'=>$user->id, 'username'=>$user->username, 'role'=>$user->role];
    }

    public static function canAccess(string $resource, string $action = 'index', string $area = 'Controllers'): bool
    {
        $role = self::user()['role'] ?? '';
        $module = strtolower($resource);
        $action = strtolower($action);
        if (strtolower($area) === 'api' && $module === 'kiosk' && $action === 'claim') $module = 'pos';
        $allowed = array_map('strtolower', self::ROLE_ACCESS[$role] ?? []);
        if ($role === 'owner') return !in_array($module, ['kiosk', 'loyalty'], true);
        if (!in_array($module, $allowed, true)) return false;
        if ($module === 'reports' && $role === 'staff') {
            return (strtolower($area) === 'controllers' && $action === 'index')
                || (strtolower($area) === 'api' && $action === 'inventory');
        }
        if (strtolower($area) === 'api' && in_array($module, ['products', 'inventory'], true)) {
            if ($role === 'staff' && $module === 'inventory' && $action === 'restock') return true;
            return in_array($action, ['all', 'categories', 'inventory', 'products', 'product'], true)
                || ($module === 'inventory' && $action === 'history')
                || ($action === 'edit' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET');
        }
        return true;
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/Auth');
            exit;
        }
    }

    public static function requireAdmin()
    {
        self::requireLogin();

        if (!in_array(self::user()['role'] ?? '', ['owner', 'admin'], true)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::user()['role'] ?? '', $roles, true)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    public static function authorizeRoute(string $area, string $resource, string $action): void
    {
        $area = ucfirst(strtolower($area));
        $resource = ucfirst(strtolower($resource));
        $action = strtolower($action);
        self::refresh();

        // Authentication and first account registration must be reachable without a session.
        if ($area === 'Controllers' && $resource === 'Auth'
            && in_array($action, ['index', 'login', 'logout'], true)) {
            return;
        }
        if ($area === 'Controllers' && $resource === 'Users'
            && $action === 'register' && !self::check()) {
            return;
        }

        // Menu browsing is public, even when a browser has a stale PHP session cookie.
        if ($area === 'Api' && $resource === 'Kiosk' && $action === 'products') {
            return;
        }

        // Guests may place kiosk orders; signed-in users still follow their role map.
        if ($area === 'Api' && $resource === 'Kiosk'
            && $action === 'submit' && !self::check()) {
            return;
        }
        if ($area === 'Controllers' && $resource === 'Kiosk' && !self::check()) {
            return;
        }

        if (!self::check()) {
            if ($area === 'Api') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
                exit;
            }
            self::requireLogin();
        }

        if (!self::canAccess($resource, $action, $area)) {
            http_response_code(403);
            if ($area === 'Api') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'You are not authorized to perform this action.']);
            } else {
                echo 'Forbidden';
            }
            exit;
        }
    }
}
