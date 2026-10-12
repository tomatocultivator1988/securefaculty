<?php
/**
 * CertificateService
 * Orchestrates business logic, cryptographic vault operations, and audit trails
 * Service Layer
 */

require_once __DIR__ . '/../DAO/CertificateDAO.php';
require_once __DIR__ . '/../Services/CryptoService.php';
require_once __DIR__ . '/../Services/AuditLogger.php';

class CertificateService {
    /**
     * Store and encrypt a staged certificate into the AES-256 Vault
     */
    public static function commitCertificate(array $input, int $requestingUserId, bool $isDean): array {
        $tempFilename = basename($input['temp_filename'] ?? '');
        $tempFilePath = TEMP_DIR . DIRECTORY_SEPARATOR . $tempFilename;

        if (empty($tempFilename)) {
            return ['success' => false, 'code' => 400, 'message' => 'Missing staged filename'];
        }

        if (!file_exists($tempFilePath) && str_starts_with($tempFilename, 'sample_demo')) {
            @file_put_contents($tempFilePath, "%PDF-1.4\n% Demo Certificate for SecureFaculty Smart Pipeline\n" . date('Y-m-d H:i:s'));
        }

        if (!file_exists($tempFilePath)) {
            return ['success' => false, 'code' => 404, 'message' => 'Temporary staged file not found or expired'];
        }

        $fileHash = hash_file('sha256', $tempFilePath);
        $assignedFacultyId = ($isDean && !empty($input['assigned_faculty_id']))
            ? (int)$input['assigned_faculty_id']
            : $requestingUserId;

        $vaultFilename = bin2hex(random_bytes(16)) . '.enc';
        $vaultFilePath = VAULT_DIR . DIRECTORY_SEPARATOR . $vaultFilename;

        if (!CryptoService::encryptFile($tempFilePath, $vaultFilePath)) {
            return ['success' => false, 'code' => 500, 'message' => 'Cryptographic Failure: AES-256 encryption operation failed'];
        }

        // Clean up temporary plaintext staged file (Zero plaintext persistence)
        @unlink($tempFilePath);

        $coAuthorIds = [];
        if (!empty($input['co_authors']) && is_array($input['co_authors'])) {
            $coAuthorIds = array_map('intval', $input['co_authors']);
        }

        $normalizedDate = CertificateModel::normalizeDate($input['date_issued'] ?? null);

        $certData = [
            'faculty_id' => $assignedFacultyId,
            'title' => trim($input['title'] ?? 'Untitled Document'),
            'issuer' => trim($input['issuer'] ?? 'Not Specified'),
            'date_issued' => $normalizedDate,
            'category' => $input['category'] ?? 'Seminar',
            'file_path' => $vaultFilename,
            'original_filename' => $input['original_name'] ?? 'certificate.pdf',
            'file_type' => strtolower(pathinfo($input['original_name'] ?? 'pdf', PATHINFO_EXTENSION)),
            'file_size' => filesize($vaultFilePath),
            'ocr_raw_text' => $input['raw_text'] ?? '',
            'ocr_confidence' => floatval($input['confidence'] ?? 0.95),
            'status' => 'approved',
            'file_hash' => $fileHash,
            'cert_number' => !empty($input['cert_number']) ? trim($input['cert_number']) : null,
            'processing_status' => 'completed',
            'batch_id' => $input['batch_id'] ?? null
        ];

        $certId = CertificateDAO::create($certData, $coAuthorIds);

        AuditLogger::log('COMMIT_VAULT_RECORD', "Approved and AES-256 encrypted certificate #{$certId}: '{$certData['title']}'", $certId);

        return [
            'success' => true,
            'certificate_id' => $certId,
            'file_hash' => $fileHash
        ];
    }

    /**
     * Authorize and decrypt document bytes in volatile RAM
     */
    public static function retrieveDocument(int $certId, int $requestingUserId, bool $isDean): array {
        $cert = CertificateDAO::find($certId);
        if (!$cert) {
            return ['success' => false, 'code' => 404, 'message' => 'Record not found.'];
        }

        $isAuthorized = false;
        if ($isDean || $cert['faculty_id'] == $requestingUserId || CertificateDAO::isAuthorOrCoAuthor($certId, $requestingUserId)) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            AuditLogger::log('UNAUTHORIZED_ACCESS_ATTEMPT', "Denied Just-in-Time decryption for certificate #{$certId}", $certId);
            return ['success' => false, 'code' => 403, 'message' => 'Access Denied: You do not possess cryptographic clearance for this faculty record.'];
        }

        $encryptedPath = VAULT_DIR . DIRECTORY_SEPARATOR . $cert['file_path'];
        $decryptedBytes = CryptoService::decryptFileToMemory($encryptedPath);

        if ($decryptedBytes === null) {
            return ['success' => false, 'code' => 500, 'message' => 'Decryption Failure: Corrupted cryptographic block or invalid master key.'];
        }

        return [
            'success' => true,
            'cert' => $cert,
            'bytes' => $decryptedBytes
        ];
    }

    /**
     * Purge stale staged uncommitted files older than given threshold (default 1 hour)
     * Enforces Zero-Plaintext persistence lifecycle
     */
    public static function purgeStaleTempFiles(int $maxAgeSeconds = 3600): int {
        $tempDir = TEMP_DIR;
        if (!is_dir($tempDir)) return 0;
        $now = time();
        $purged = 0;
        $files = glob($tempDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (basename($file) === '.gitkeep' || basename($file) === '.htaccess' || is_dir($file)) continue;
            if ($now - filemtime($file) > $maxAgeSeconds) {
                if (@unlink($file)) {
                    $purged++;
                }
            }
        }
        return $purged;
    }
}
