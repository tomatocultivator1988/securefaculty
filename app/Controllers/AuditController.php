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

        // True database-driven pagination across all historical records
        $logData = AuditLogger::getPaginatedLogs($search, $actionFilter, $page, $perPage);
        $displayLogs = $logData['rows'];
        $totalLogs = $logData['totalRows'];
        $totalPages = $logData['totalPages'];
        $page = $logData['currentPage'];

        // Comprehensive statistical counters across full ledger
        $pdo = Database::getConnection();
        $statTotal = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
        $statLogins = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('LOGIN_SUCCESS', 'LOGOUT')")->fetchColumn();
        $statDocs = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('ARCHIVE_DOCUMENT','DELETE_DOCUMENT','VIEW_DOCUMENT','DOWNLOAD_DOCUMENT','OCR_EXTRACT','COMMIT_VAULT_RECORD','BULK_DELETE_DOCUMENTS')")->fetchColumn();
        $statAlerts = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('LOGIN_FAILED','UNAUTHORIZED_ACCESS_ATTEMPT','LOGIN_BLOCKED','CSRF_VIOLATION_BLOCKED')")->fetchColumn();

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
