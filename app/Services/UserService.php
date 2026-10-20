<?php
/**
 * UserService
 * Business logic and governance for User Accounts and Faculty Profiles
 * Service Layer
 */

require_once __DIR__ . '/../DAO/UserDAO.php';
require_once __DIR__ . '/../Services/AuditLogger.php';

class UserService {
    public static function create(array $input): array {
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $fullName = trim($input['full_name'] ?? '');

        if (empty($username) || empty($password) || empty($fullName)) {
            return ['success' => false, 'code' => 400, 'message' => 'Username, full name, and initial password are required.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'code' => 400, 'message' => 'Password must be at least 6 characters.'];
        }

        if (UserDAO::findByUsername($username)) {
            return ['success' => false, 'code' => 409, 'message' => 'Username already registered.'];
        }

        $newId = UserDAO::create($input);
        $role = $input['role'] ?? 'faculty';
        AuditLogger::log('USER_CREATED', "New user account created: {$username} ({$role})", $newId);

        return ['success' => true, 'user_id' => $newId];
    }

    public static function toggleStatus(int $userId, int $currentUserId): array {
        $res = UserDAO::toggleStatus($userId, $currentUserId);
        if (!$res['success']) {
            return ['success' => false, 'code' => 400, 'message' => $res['message']];
        }

        AuditLogger::log('USER_STATUS_TOGGLED', "User {$res['username']} status set to " . ($res['is_active'] ? 'Active' : 'Deactivated'), $userId);
        return ['success' => true, 'is_active' => $res['is_active']];
    }

    public static function resetPassword(int $userId, string $newPassword): array {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'code' => 400, 'message' => 'Password must be at least 6 characters.'];
        }

        UserDAO::resetPassword($userId, $newPassword);
        AuditLogger::log('USER_PASSWORD_RESET', "Password reset for user ID #{$userId}", $userId);
        return ['success' => true, 'message' => 'Password reset successfully'];
    }

    public static function delete(int $userId, int $currentUserId): array {
        $res = UserDAO::delete($userId, $currentUserId);
        if (!$res['success']) {
            return ['success' => false, 'code' => 400, 'message' => $res['message']];
        }

        AuditLogger::log('USER_DELETED', "User account {$res['username']} deleted", $userId);
        return ['success' => true, 'message' => 'User deleted successfully'];
    }
}
