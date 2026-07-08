<?php
/**
 * Audit Logger
 * Logs all admin actions for accountability
 */

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../config/database.php';

class AuditLog {
    
    /**
     * Log an action
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        ?array $beforeData = null,
        ?array $afterData = null
    ): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $auth = Auth::getInstance();
            
            $actorId = $auth->id() ?? 0;
            $actorType = $auth->role() ?? 'SYSTEM';
            $user = $auth->user();
            $actorName = $user['name'] ?? 'System';
            $ipAddress = self::getClientIP();
            $userAgent = self::getUserAgent();
            
            $beforeJson = $beforeData ? json_encode($beforeData) : null;
            $afterJson = $afterData ? json_encode($afterData) : null;
            
            $stmt = $db->prepare("
                INSERT INTO audit_logs 
                (actor_id, actor_type, actor_name, action, entity_type, entity_id, entity_name, before_data, after_data, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "issssssssss",
                $actorId,
                $actorType,
                $actorName,
                $action,
                $entityType,
                $entityId,
                $entityName,
                $beforeJson,
                $afterJson,
                $ipAddress,
                $userAgent
            );
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("AuditLog Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a CREATE action
     */
    public static function logCreate(string $entityType, int $entityId, string $entityName, array $data): bool {
        return self::log('CREATE', $entityType, $entityId, $entityName, null, $data);
    }
    
    /**
     * Log an UPDATE action
     */
    public static function logUpdate(string $entityType, int $entityId, string $entityName, array $beforeData, array $afterData): bool {
        return self::log('UPDATE', $entityType, $entityId, $entityName, $beforeData, $afterData);
    }
    
    /**
     * Log a DELETE action
     */
    public static function logDelete(string $entityType, int $entityId, string $entityName, ?array $deletedData = null): bool {
        return self::log('DELETE', $entityType, $entityId, $entityName, $deletedData, null);
    }
    
    /**
     * Log a LOGIN action
     */
    public static function logLogin(int $adminId, string $adminName, string $role): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $ipAddress = self::getClientIP();
            $userAgent = self::getUserAgent();
            
            $stmt = $db->prepare("
                INSERT INTO audit_logs 
                (actor_id, actor_type, actor_name, action, entity_type, entity_id, entity_name, ip_address, user_agent)
                VALUES (?, ?, ?, 'LOGIN', 'session', ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "ississs",
                $adminId,
                $role,
                $adminName,
                $adminId,
                $adminName,
                $ipAddress,
                $userAgent
            );
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("AuditLog Login Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a LOGOUT action
     */
    public static function logLogout(): bool {
        $auth = Auth::getInstance();
        $user = $auth->user();
        
        if (!$user) {
            return false;
        }
        
        return self::log('LOGOUT', 'session', $user['id'], $user['name'], null, null);
    }
    
    /**
     * Log a PROMOTE action (role upgrade)
     */
    public static function logPromote(int $userId, string $userName, string $oldRole, string $newRole): bool {
        return self::log('PROMOTE', 'admin', $userId, $userName, ['role' => $oldRole], ['role' => $newRole]);
    }
    
    /**
     * Log a DEMOTE action (role downgrade)
     */
    public static function logDemote(int $userId, string $userName, string $oldRole, string $newRole): bool {
        return self::log('DEMOTE', 'admin', $userId, $userName, ['role' => $oldRole], ['role' => $newRole]);
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
    
    /**
     * Get user agent string
     */
    public static function getUserAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Get audit logs with filters
     */
    public static function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array {
        try {
            $db = Database::getInstance()->getConnection();
            
            $where = [];
            $params = [];
            $types = "";
            
            if (!empty($filters['actor_id'])) {
                $where[] = "actor_id = ?";
                $params[] = $filters['actor_id'];
                $types .= "i";
            }
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
                $types .= "s";
            }
            if (!empty($filters['entity_type'])) {
                $where[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
                $types .= "s";
            }
            if (!empty($filters['from_date'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['from_date'];
                $types .= "s";
            }
            if (!empty($filters['to_date'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['to_date'];
                $types .= "s";
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $sql = "SELECT * FROM audit_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $db->prepare($sql);
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $row['before_data'] = $row['before_data'] ? json_decode($row['before_data'], true) : null;
                $row['after_data'] = $row['after_data'] ? json_decode($row['after_data'], true) : null;
                $logs[] = $row;
            }
            
            return $logs;
        } catch (Exception $e) {
            error_log("AuditLog GetLogs Error: " . $e->getMessage());
            return [];
        }
    }
}
