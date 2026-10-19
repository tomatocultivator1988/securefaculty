<?php
/**
 * UserDAO
 * Concrete Data Access Object for User accounts and Faculty profiles
 * Implements UserDAOInterface
 * DAO Layer
 */

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Interfaces/UserDAOInterface.php';

class UserDAO implements UserDAOInterface {
    public static function getAllWithProfiles(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.role, u.full_name, u.email, u.department, u.created_at,
                   COALESCE(u.is_active, 1) as is_active,
                   fp.employee_id, fp.academic_rank, fp.specialization,
                   (SELECT COUNT(DISTINCT c.id) FROM certificates c LEFT JOIN certificate_authors ca ON c.id = ca.certificate_id WHERE c.faculty_id = u.id OR ca.user_id = u.id) as cert_count,
                   (SELECT COUNT(DISTINCT c.id) FROM certificates c LEFT JOIN certificate_authors ca ON c.id = ca.certificate_id WHERE c.faculty_id = u.id OR ca.user_id = u.id) as certificate_count
            FROM users u
            LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
            ORDER BY u.role DESC, u.full_name ASC
        ");
        return $stmt->fetchAll();
    }

    public static function getFacultyList(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, full_name, username, department FROM users ORDER BY full_name ASC");
        return $stmt->fetchAll();
    }

    public static function getFacultyOnly(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'faculty' ORDER BY full_name ASC");
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, fp.employee_id, fp.academic_rank, fp.specialization
            FROM users u
            LEFT JOIN faculty_profiles fp ON u.id = fp.user_id
            WHERE u.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByUsername(string $username): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function getProfile(int $userId): ?array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT academic_rank, employee_id, specialization FROM faculty_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function getStats(): array {
        $pdo = Database::getConnection();
        $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 OR is_active IS NULL")->fetchColumn();
        $deanCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dean'")->fetchColumn();
        $inactiveUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'deanCount' => $deanCount,
            'inactiveUsers' => $inactiveUsers
        ];
    }

    public static function getFacultyLookup(): array {
        $pdo = Database::getConnection();
        $lookup = [];
        $stmt = $pdo->query("SELECT id, full_name FROM users");
        while ($row = $stmt->fetch()) {
            $lookup[$row['id']] = $row['full_name'];
        }
        return $lookup;
    }

    public static function create(array $data): int {
        $pdo = Database::getConnection();
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = in_array($data['role'] ?? '', ['dean', 'faculty']) ? $data['role'] : 'faculty';

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, role, full_name, email, department, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $data['username'],
            $hash,
            $role,
            $data['full_name'],
            $data['email'] ?? null,
            $data['department'] ?? 'CICI'
        ]);

        $userId = (int)$pdo->lastInsertId();

        $pStmt = $pdo->prepare("
            INSERT INTO faculty_profiles (user_id, employee_id, academic_rank, specialization)
            VALUES (?, ?, ?, ?)
        ");
        $pStmt->execute([
            $userId,
            $data['employee_id'] ?? ('ISUFST-' . rand(1000, 9999)),
            $data['academic_rank'] ?? 'Instructor I',
            $data['specialization'] ?? ''
        ]);

        return $userId;
    }

    public static function toggleStatus(int $id, int $currentUserId): array {
        $pdo = Database::getConnection();
        $user = self::find($id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if ($id === $currentUserId) {
            return ['success' => false, 'message' => 'Cannot deactivate your own active session.'];
        }

        $newStatus = (isset($user['is_active']) && (int)$user['is_active'] === 1) ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        return [
            'success' => true,
            'is_active' => $newStatus,
            'username' => $user['username']
        ];
    }

    public static function resetPassword(int $id, string $newPassword): bool {
        $pdo = Database::getConnection();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$hash, $id]);
    }

    public static function updateProfile(int $id, array $data): bool {
        $pdo = Database::getConnection();
        $user = self::find($id);
        if (!$user) {
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, role = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $data['full_name'] ?? $user['full_name'],
            $data['email'] ?? $user['email'],
            $data['role'] ?? $user['role'],
            $id
        ]);

        $pCheck = $pdo->prepare("SELECT id FROM faculty_profiles WHERE user_id = ? LIMIT 1");
        $pCheck->execute([$id]);
        $hasProfile = $pCheck->fetchColumn();

        if ($hasProfile) {
            $pStmt = $pdo->prepare("
                UPDATE faculty_profiles 
                SET employee_id = ?, academic_rank = ?, specialization = ? 
                WHERE user_id = ?
            ");
            $pStmt->execute([
                $data['employee_id'] ?? '',
                $data['academic_rank'] ?? '',
                $data['specialization'] ?? '',
                $id
            ]);
        } else {
            $pStmt = $pdo->prepare("
                INSERT INTO faculty_profiles (user_id, employee_id, academic_rank, specialization)
                VALUES (?, ?, ?, ?)
            ");
            $pStmt->execute([
                $id,
                $data['employee_id'] ?? ('ISUFST-' . rand(1000, 9999)),
                $data['academic_rank'] ?? 'Instructor I',
                $data['specialization'] ?? ''
            ]);
        }

        return true;
    }

    public static function delete(int $id, int $currentUserId): array {
        $pdo = Database::getConnection();
        $user = self::find($id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if ($id === $currentUserId) {
            return ['success' => false, 'message' => 'Cannot delete currently logged-in account.'];
        }

        // Clean up associated vault files on disk before cascade deletion
        $cStmt = $pdo->prepare("SELECT file_path FROM certificates WHERE faculty_id = ?");
        $cStmt->execute([$id]);
        $vaultFiles = $cStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($vaultFiles as $filePath) {
            if ($filePath) {
                $refCheck = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE file_path = ? AND faculty_id != ?");
                $refCheck->execute([$filePath, $id]);
                if ((int)$refCheck->fetchColumn() === 0) {
                    $fullPath = VAULT_DIR . DIRECTORY_SEPARATOR . $filePath;
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        return ['success' => true, 'username' => $user['username']];
    }
}
