<?php
/**
 * AuditController
 * Immutable Compliance Audit Trail Viewer
 * Controller Layer
 */

require_once __DIR__ . '/BaseController.php';

class AuditController extends BaseController {
    public function index(): void {
        AuthService::requireDean();
        $currentUser = AuthService::user();

        $actionFilter = !empty($_GET['action']) ? $_GET['action'] : null;
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;

        // Get logs for stats and display
        $allLogs = AuditLogger::getRecentLogs(500, null);
        $filteredLogs = AuditLogger::getRecentLogs(500, $actionFilter);

        if (!empty($search)) {
            $filteredLogs = array_filter($filteredLogs, function($l) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($l['created_at']), $searchLower)
                    || str_contains(strtolower($l['username']), $searchLower)
                    || str_contains(strtolower($l['action']), $searchLower)
                    || str_contains(strtolower($l['details']), $searchLower)
                    || str_contains(strtolower($l['ip_address']), $searchLower);
            });
        }

        $totalLogs = count($filteredLogs);
        $totalPages = max(1, ceil($totalLogs / $perPage));
        $page = min($page, $totalPages);
        $displayLogs = array_slice($filteredLogs, ($page - 1) * $perPage, $perPage);

        $statTotal = count($allLogs);
        $statLogins = count(array_filter($allLogs, fn($l) => in_array($l['action'], ['LOGIN_SUCCESS', 'LOGOUT'])));
        $statDocs = count(array_filter($allLogs, fn($l) => in_array($l['action'], ['ARCHIVE_DOCUMENT','DELETE_DOCUMENT','VIEW_DOCUMENT','DOWNLOAD_DOCUMENT','OCR_EXTRACT'])));
        $statAlerts = count(array_filter($allLogs, fn($l) => $l['action'] === 'LOGIN_FAILED'));

        $facultyLookup = UserModel::getFacultyLookup();

        $this->renderView('audit/index.php', [
            'currentUser' => $currentUser,
            'actionFilter' => $actionFilter,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'displayLogs' => $displayLogs,
            'totalLogs' => $totalLogs,
            'statTotal' => $statTotal,
            'statLogins' => $statLogins,
            'statDocs' => $statDocs,
            'statAlerts' => $statAlerts,
            'facultyLookup' => $facultyLookup,
            'activeNav' => 'audit',
            'pageTitle' => 'Immutable Compliance Audit Ledger'
        ]);
    }
}
