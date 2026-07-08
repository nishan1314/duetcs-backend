<?php
/**
 * Role Guard Middleware
 * Protects routes based on authentication and role requirements
 */

require_once __DIR__ . '/Auth.php';

class RoleGuard {
    
    /**
     * Require authentication - returns 401 if not authenticated
     */
    public static function requireAuth(): bool {
        $auth = Auth::getInstance();
        
        if (!$auth->check()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication required. Please log in.'
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Require specific role - returns 403 if user doesn't have role
     */
    public static function requireRole(string $role): bool {
        self::requireAuth();
        $auth = Auth::getInstance();
        
        if (!$auth->hasRole($role)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
                'required_role' => $role,
                'your_role' => $auth->role()
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Require any of the specified roles
     */
    public static function requireAnyRole(array $roles): bool {
        self::requireAuth();
        $auth = Auth::getInstance();
        
        $hasRole = false;
        foreach ($roles as $role) {
            if ($auth->hasRole($role)) {
                $hasRole = true;
                break;
            }
        }
        
        if (!$hasRole) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
                'required_roles' => $roles,
                'your_role' => $auth->role()
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Require Super Admin role
     */
    public static function requireSuperAdmin(): bool {
        return self::requireRole('SUPER_ADMIN');
    }
    
    /**
     * Require Admin or Super Admin role
     */
    public static function requireAdmin(): bool {
        return self::requireAnyRole(['SUPER_ADMIN', 'ADMIN']);
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission(string $permission): bool {
        self::requireAuth();
        $auth = Auth::getInstance();
        
        if (!$auth->can($permission)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'FORBIDDEN',
                'message' => 'You do not have the required permission for this action.',
                'required_permission' => $permission
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Require any of the specified permissions
     */
    public static function requireAnyPermission(array $permissions): bool {
        self::requireAuth();
        $auth = Auth::getInstance();
        
        if (!$auth->canAny($permissions)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'FORBIDDEN',
                'message' => 'You do not have the required permission for this action.',
                'required_permissions' => $permissions
            ]);
            exit;
        }
        return true;
    }
    
    /**
     * Check permission without exiting (for conditional logic)
     */
    public static function checkPermission(string $permission): bool {
        $auth = Auth::getInstance();
        return $auth->check() && $auth->can($permission);
    }
    
    /**
     * Check role without exiting
     */
    public static function checkRole(string $role): bool {
        $auth = Auth::getInstance();
        return $auth->check() && $auth->hasRole($role);
    }
}
