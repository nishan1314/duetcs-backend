<?php
/**
 * Admin Authentication and Authorization Utilities
 * Supports both user-based roles and system_admin authentication
 */

require_once __DIR__ . '/../config/database.php';

class AdminAuth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check if user is authenticated (has valid session)
     * Supports both user login and system_admin login
     */
    public function isAuthenticated() {
        // Check for system_admin session first (new auth system)
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role'])) {
            return $this->validateAdminSession($_SESSION['admin_id']);
        }
        
        // Fallback to user session (old auth system)
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
            return false;
        }
        
        return $this->validateSession($_SESSION['user_id']);
    }
    
    /**
     * Validate system_admin session
     */
    private function validateAdminSession($adminId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, is_active 
                FROM system_admin 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Admin session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate user session in database
     */
    private function validateSession($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, is_verified 
                FROM users 
                WHERE id = ? AND is_verified = 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has admin privileges (any admin role)
     * Supports both system_admin and user_roles
     */
    public function isAdmin($userId = null) {
        // Check if using system_admin authentication
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role'])) {
            return true; // System admins are always admins
        }
        
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_roles ur
                INNER JOIN admin_roles ar ON ur.role_id = ar.id
                WHERE ur.user_id = ? 
                AND ar.is_active = 1
                AND ar.role_name IN ('Super Admin', 'Admin', 'Moderator', 'Event Manager', 'Content Manager')
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] > 0;
        } catch (Exception $e) {
            error_log("Admin check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's roles
     */
    public function getUserRoles($userId = null) {
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return [];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT ar.id, ar.role_name, ar.role_description
                FROM user_roles ur
                INNER JOIN admin_roles ar ON ur.role_id = ar.id
                WHERE ur.user_id = ? AND ar.is_active = 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $roles = [];
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row;
            }
            
            return $roles;
        } catch (Exception $e) {
            error_log("Get roles error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user has a specific permission
     * Supports both system_admin and user_roles
     */
    public function hasPermission($permissionKey, $userId = null) {
        // Check if using system_admin authentication
        if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role'])) {
            // Super Admin has all permissions
            if ($_SESSION['admin_role'] === 'SUPER_ADMIN') {
                return true;
            }
            // Check permission mapping for other admin roles
            return $this->checkAdminRolePermission($_SESSION['admin_role'], $permissionKey);
        }
        
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_roles ur
                INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
                INNER JOIN admin_permissions ap ON rp.permission_id = ap.id
                WHERE ur.user_id = ? AND ap.permission_key = ?
            ");
            $stmt->bind_param("is", $userId, $permissionKey);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['count'] > 0;
        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check permission for system_admin role
     */
    private function checkAdminRolePermission($role, $permissionKey) {
        // Permission mapping for admin roles
        $rolePermissions = [
            'SUPER_ADMIN' => ['*'], // All permissions
            'ADMIN' => [
                'notices.view', 'notices.create', 'notices.edit', 'notices.delete',
                'users.view', 'users.edit',
                'content.view', 'content.create', 'content.edit',
                'events.view', 'events.create', 'events.edit',
                'achievements.manage'
            ],
            'MODERATOR' => [
                'notices.view', 'notices.create',
                'users.view',
                'content.view',
                'events.view'
            ]
        ];
        
        $permissions = $rolePermissions[$role] ?? [];
        
        // Check for wildcard or specific permission
        if (in_array('*', $permissions) || in_array($permissionKey, $permissions)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId = null) {
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return [];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT ap.permission_key, ap.permission_name, ap.module
                FROM user_roles ur
                INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
                INNER JOIN admin_permissions ap ON rp.permission_id = ap.id
                INNER JOIN admin_roles ar ON ur.role_id = ar.id
                WHERE ur.user_id = ? AND ar.is_active = 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row;
            }
            
            return $permissions;
        } catch (Exception $e) {
            error_log("Get permissions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Require authentication - send 401 if not authenticated
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required'
            ]);
            exit;
        }
    }
    
    /**
     * Require admin privileges - send 403 if not admin
     */
    public function requireAdmin() {
        $this->requireAuth();
        
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Admin privileges required'
            ]);
            exit;
        }
    }
    
    /**
     * Require specific permission - send 403 if permission denied
     */
    public function requirePermission($permissionKey) {
        $this->requireAuth();
        
        if (!$this->hasPermission($permissionKey)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
                'required_permission' => $permissionKey
            ]);
            exit;
        }
    }
    
    /**
     * Get current user details with roles and permissions
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, full_name, email, student_id, department, 
                       year_semester, profile_image, is_verified, created_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return null;
            }
            
            $user = $result->fetch_assoc();
            $user['roles'] = $this->getUserRoles($userId);
            $user['permissions'] = $this->getUserPermissions($userId);
            $user['is_admin'] = $this->isAdmin($userId);
            
            return $user;
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
}
