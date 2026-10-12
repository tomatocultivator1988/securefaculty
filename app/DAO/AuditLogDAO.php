<?php
/**
 * AuditLogDAO
 * Concrete Data Access Object for Immutable Compliance Audit Logs
 * Implements AuditLogDAOInterface
 * DAO Layer
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Interfaces/AuditLogDAOInterface.php';

class AuditLogDAO implements AuditLogDAOInterface {
    public static function insertLog(?int $userId, string $username, string $role, string $action, ?int $targetId, string $details, string $ip, string $userAgent): void {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, target_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $username, $role, $action, $targetId, $details, $ip, $userAgent]);
        } catch (Exception $e) {
            error_log('AuditLogDAO Insert Error: ' . $e->getMessage());
        }
    }

    public static function getRecentLogs(int $limit = 50, ?string $actionFilter = null): array {
        $pdo = Database::getConnection();
        if ($actionFilter) {
            $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $actionFilter, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public static function getPaginatedLogs(?string $search = null, ?string $actionFilter = null, int $page = 1, int $perPage = 10): array {
        $pdo = Database::getConnection();
        $logQuery = "
            SELECT a.*, COALESCE(u.full_name, 'Departmental Audit') as faculty_display
            FROM audit_logs a
            LEFT JOIN users u ON a.target_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($search) {
            $logQuery .= " AND (a.details LIKE :s OR a.username LIKE :s OR a.ip_address LIKE :s OR a.action LIKE :s)";
            $params[':s'] = "%$search%";
        }
        if ($actionFilter) {
            $logQuery .= " AND a.action = :act";
            $params[':act'] = $actionFilter;
        }
        $logQuery .= " ORDER BY a.created_at DESC";
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute($params);
        $allRows = $stmt->fetchAll();

        $totalRows = count($allRows);
        $totalPages = max(1, ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $displayRows = !empty($allRows) ? array_slice($allRows, ($page - 1) * $perPage, $perPage) : [];

        return [
            'rows' => $displayRows,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
    }
}
