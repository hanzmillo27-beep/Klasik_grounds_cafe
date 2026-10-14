<?php

namespace Core;

class Auth{

    /** Role permissions are checked by the router for every page and API request. */
    private const ROLE_ACCESS = [
        'owner' => ['*'],
        'admin' => ['Users'],
        'cashier' => ['POS'],
        'user' => ['Kiosk'],
        'loyal_customer' => ['Loyalty', 'Kiosk'],
    ];

    public static function check()
    {
        return !empty($_SESSION['user']);
    }

    public static function user()
    {
        return $_SESSION['user'] ?? null;
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
        $action = strtolower($action);

        // Authentication and first account registration must be reachable without a session.
        if ($area === 'Controllers' && $resource === 'Auth'
            && in_array($action, ['index', 'login', 'logout'], true)) {
            return;
        }
        if ($area === 'Controllers' && $resource === 'Users'
            && $action === 'register' && !self::check()) {
            return;
        }

        // Guests may order from the public kiosk. Signed-in users still follow their role map.
        if ($area === 'Api' && $resource === 'Kiosk'
            && in_array($action, ['products', 'submit'], true)) {
            if (!self::check()) {
                return;
            }
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

        $role = (string) (self::user()['role'] ?? '');
        $allowedAreas = self::ROLE_ACCESS[$role] ?? [];
        $modules = array_map('strtolower', $allowedAreas);
        $module = strtolower($resource);

        // Claiming an order is part of the POS workflow, though its endpoint is under Kiosk.
        if ($area === 'Api' && $module === 'kiosk' && $action === 'claim') {
            $module = 'pos';
        }

        // Owners retain system-wide access except to the customer-facing Kiosk and Loyalty areas.
        $ownerRestrictedModule = $role === 'owner'
            && in_array($module, ['kiosk', 'loyalty'], true);

        if ($ownerRestrictedModule || (!in_array('*', $allowedAreas, true) && !in_array($module, $modules, true))) {
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
