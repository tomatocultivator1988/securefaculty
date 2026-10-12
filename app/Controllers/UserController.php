<?php
/**
 * UserController
 * Faculty & Administrative User Management
 * Controller Layer
 */

require_once __DIR__ . '/BaseController.php';

class UserController extends BaseController {
    public function index(): void {
        AuthService::requireDean();
        $currentUser = AuthService::user();

        $users = UserModel::getAllWithProfiles();
        $stats = UserModel::getStats();

        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? 'all';

        if ($search !== '' || $statusFilter !== 'all') {
            $searchLower = strtolower($search);
            $users = array_values(array_filter($users, function($u) use ($searchLower, $statusFilter) {
                $matchStatus = true;
                if ($statusFilter === 'active') {
                    $matchStatus = ((int)$u['is_active'] === 1);
                } elseif ($statusFilter === 'inactive') {
                    $matchStatus = ((int)$u['is_active'] === 0);
                }

                $matchSearch = true;
                if ($searchLower !== '') {
                    $matchSearch = (
                        str_contains(strtolower($u['full_name'] ?? ''), $searchLower) ||
                        str_contains(strtolower($u['username'] ?? ''), $searchLower) ||
                        str_contains(strtolower($u['email'] ?? ''), $searchLower) ||
                        str_contains(strtolower($u['employee_id'] ?? ''), $searchLower) ||
                        str_contains(strtolower($u['academic_rank'] ?? ''), $searchLower) ||
                        str_contains(strtolower($u['specialization'] ?? ''), $searchLower)
                    );
                }

                return $matchStatus && $matchSearch;
            }));
        }

        $this->renderView('users/index.php', [
            'currentUser' => $currentUser,
            'users' => $users,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'totalUsers' => $stats['totalUsers'],
            'activeUsers' => $stats['activeUsers'],
            'deanCount' => $stats['deanCount'],
            'inactiveUsers' => $stats['inactiveUsers'],
            'activeNav' => 'users',
            'pageTitle' => 'Faculty & User Management'
        ]);
    }

    public function handleApi(): void {
        header('Content-Type: application/json');
        AuthService::initSession();

        if (!AuthService::check() || !AuthService::isDean()) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Access Denied: Administrative privileges required.'], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!AuthService::verifyCsrfToken($csrfToken)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'CSRF Security Violation: Invalid or expired token.'], 403);
        }

        $action = $input['action'] ?? '';
        $currentUser = AuthService::user();

        switch ($action) {
            case 'create':
                $username = trim($input['username'] ?? '');
                $password = trim($input['password'] ?? '');
                $fullName = trim($input['full_name'] ?? '');

                if (empty($username) || empty($password) || empty($fullName)) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Username, full name, and initial password are required.'], 400);
                }

                if (strlen($password) < 6) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters.'], 400);
                }

                if (UserModel::findByUsername($username)) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Username already registered.'], 409);
                }

                $newId = UserModel::create($input);
                AuditLogger::log('USER_CREATED', "New user account created: {$username} ({$input['role']})", $newId);
                $this->jsonResponse(['status' => 'success', 'message' => 'User created successfully', 'user_id' => $newId]);

            case 'toggle_status':
                $userId = (int)($input['user_id'] ?? 0);
                $res = UserModel::toggleStatus($userId, $currentUser['id']);
                if (!$res['success']) {
                    $this->jsonResponse(['status' => 'error', 'message' => $res['message']], 400);
                }
                AuditLogger::log('USER_STATUS_TOGGLED', "User {$res['username']} status set to " . ($res['is_active'] ? 'Active' : 'Deactivated'), $userId);
                $this->jsonResponse(['status' => 'success', 'is_active' => $res['is_active']]);

            case 'update_profile':
                $userId = (int)($input['user_id'] ?? 0);
                if ($userId <= 0) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Invalid user ID'], 400);
                }
                $ok = UserModel::updateProfile($userId, $input);
                if (!$ok) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Failed to update user profile'], 500);
                }
                AuditLogger::log('USER_PROFILE_UPDATED', "Updated profile for user ID #{$userId}", $userId);
                $this->jsonResponse(['status' => 'success', 'message' => 'User profile updated successfully']);

            case 'reset_password':
                $userId = (int)($input['user_id'] ?? 0);
                $newPassword = trim($input['new_password'] ?? '');
                if (strlen($newPassword) < 6) {
                    $this->jsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters.'], 400);
                }
                UserModel::resetPassword($userId, $newPassword);
                AuditLogger::log('USER_PASSWORD_RESET', "Password reset for user ID #{$userId}", $userId);
                $this->jsonResponse(['status' => 'success', 'message' => 'Password reset successfully']);

            case 'delete':
                $userId = (int)($input['user_id'] ?? 0);
                $res = UserModel::delete($userId, $currentUser['id']);
                if (!$res['success']) {
                    $this->jsonResponse(['status' => 'error', 'message' => $res['message']], 400);
                }
                AuditLogger::log('USER_DELETED', "User account {$res['username']} deleted", $userId);
                $this->jsonResponse(['status' => 'success', 'message' => 'User deleted successfully']);

            default:
                $this->jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }
}
