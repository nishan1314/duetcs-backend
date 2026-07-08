<?php
/**
 * Authentication Middleware for Admin System
 * Works with existing system_admin table using 'designation' field
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    private static $instance = null;
    private $db;
    private $sessionStarted = false;
    
    // Role mapping from database designation to internal role names
    private const ROLE_MAP = [
        'Super Admin' => 'SUPER_ADMIN',
        'Admin' => 'ADMIN',
        'Moderator' => 'MODERATOR'
    ];
    
    // Reverse mapping for database operations
    private const DESIGNATION_MAP = [
        'SUPER_ADMIN' => 'Super Admin',
        'ADMIN' => 'Admin',
        'MODERATOR' => 'Moderator'
    ];
    
    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureSession();
    }
    
    public static function getInstance(): Auth {
        if (self::$instance === null) {
            self::$instance = new Auth();
        }
        return self::$instance;
    }
    
    private function ensureSession(): void {
        if ($this->sessionStarted) return;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
        $this->sessionStarted = true;
    }
    
    /**
     * Check if admin is authenticated
     */
    public function check(): bool {
        $this->ensureSession();
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
    }
    
    /**
     * Get current admin user data
     */
    public function user(): ?array {
        if (!$this->check()) return null;
        
        $stmt = $this->db->prepare("
            SELECT id, name, email, designation, phone_number, is_active, last_login, created_at
            FROM system_admin 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $this->logout();
            return null;
        }
        
        $admin = $result->fetch_assoc();
        $admin['role'] = self::ROLE_MAP[$admin['designation']] ?? 'ADMIN';
        $admin['permissions'] = $this->getPermissions($admin['role']);
        
        return $admin;
    }
    
    /**
     * Get current admin ID
     */
    public function id(): ?int {
        return $this->check() ? $_SESSION['admin_id'] : null;
    }
    
    /**
     * Get current admin role (mapped from designation)
     */
    public function role(): ?string {
        return $this->check() ? $_SESSION['admin_role'] : null;
    }
    
    /**
     * Get designation for database (reverse map from role)
     */
    public static function getDesignation(string $role): string {
        return self::DESIGNATION_MAP[$role] ?? 'Admin';
    }
    
    /**
     * Get role from designation
     */
    public static function getRole(string $designation): string {
        return self::ROLE_MAP[$designation] ?? 'ADMIN';
    }
    
    /**
     * Check if admin has specific role
     */
    public function hasRole(string $role): bool {
        $currentRole = $this->role();
        if (!$currentRole) return false;
        
        if ($currentRole === 'SUPER_ADMIN') return true;
        
        return $currentRole === $role;
    }
    
    /**
     * Check if admin has specific permission
     */
    public function can(string $permission): bool {
        $role = $this->role();
        if (!$role) return false;
        
        if ($role === 'SUPER_ADMIN') return true;
        
        $permissions = $this->getPermissions($role);
        return in_array($permission, $permissions);
    }
    
    /**
     * Check if admin has any of the specified permissions
     */
    public function canAny(array $permissions): bool {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) return true;
        }
        return false;
    }
    
    /**
     * Get permissions for a role
     */
    public function getPermissions(string $role): array {
        $rolePermissions = [
            'SUPER_ADMIN' => [
                'admins.view', 'admins.create', 'admins.edit', 'admins.delete',
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'content.view', 'content.create', 'content.edit', 'content.delete',
                'notices.view', 'notices.create', 'notices.edit', 'notices.delete',
                'events.view', 'events.create', 'events.edit', 'events.delete',
                'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                'settings.view', 'settings.edit',
                'audit.view'
            ],
            'ADMIN' => [
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'content.view', 'content.create', 'content.edit', 'content.delete',
                'notices.view', 'notices.create', 'notices.edit', 'notices.delete',
                'events.view', 'events.create', 'events.edit', 'events.delete',
                'payments.view', 'payments.create', 'payments.edit', 'payments.delete',
                'settings.view', 'settings.edit',
                'audit.view'
            ]
        ];
        
        return $rolePermissions[$role] ?? [];
    }
    
    /**
     * Login admin
     */
    public function login(int $adminId, string $role): void {
        $this->ensureSession();
        
        session_regenerate_id(true);
        
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_role'] = $role;
        $_SESSION['login_time'] = time();
        
        $stmt = $this->db->prepare("UPDATE system_admin SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
    }
    
    /**
     * Logout admin
     */
    public function logout(): void {
        $this->ensureSession();
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        $this->sessionStarted = false;
    }
    
    /**
     * Verify password for admin
     */
    public function verifyPassword(string $email, string $password): ?array {
        $stmt = $this->db->prepare("
            SELECT id, name, email, password, designation, is_active 
            FROM system_admin 
            WHERE email = ?
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $admin = $result->fetch_assoc();
        
        if (!$admin['is_active']) {
            return null;
        }
        
        if (!password_verify($password, $admin['password'])) {
            return null;
        }
        
        $admin['role'] = self::ROLE_MAP[$admin['designation']] ?? 'ADMIN';
        unset($admin['password']);
        
        return $admin;
    }
}
