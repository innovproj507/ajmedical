<?php

namespace AJM\Core;

use finfo;

/**
 * Security — CSRF, rate limiting, headers, sanitización.
 * Generalizado de diplomado-adolescencia/includes/Security.php.
 */
class Security
{
    // ─── CSRF ─────────────────────────────────────────────────────────────

    public static function generateCSRF(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || (time() - ($_SESSION['csrf_token_ts'] ?? 0)) > 7200) {
            $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_ts'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRF(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $stored = $_SESSION['csrf_token'] ?? '';
        $ts     = $_SESSION['csrf_token_ts'] ?? 0;

        if (empty($stored) || empty($token) || (time() - $ts) > 7200) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    // ─── RATE LIMITING ────────────────────────────────────────────────────

    public static function checkRateLimit(string $action, int $maxTries = 5, int $window = 600): bool
    {
        $ip = self::getClientIP();
        $db = Database::getInstance();

        try {
            $db->execute(
                "DELETE FROM rate_limits WHERE ip_address = ? AND action = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$ip, $action, $window]
            );

            $row = $db->fetchOne(
                "SELECT COUNT(*) AS total FROM rate_limits WHERE ip_address = ? AND action = ?",
                [$ip, $action]
            );

            if ((int) ($row['total'] ?? 0) >= $maxTries) {
                return false;
            }

            $db->execute("INSERT INTO rate_limits (ip_address, action) VALUES (?, ?)", [$ip, $action]);
        } catch (\Exception $e) {
            error_log('[Security] checkRateLimit error: ' . $e->getMessage());
        }

        return true;
    }

    public static function resetRateLimit(string $action): void
    {
        $ip = self::getClientIP();
        Database::getInstance()->execute(
            "DELETE FROM rate_limits WHERE ip_address = ? AND action = ?",
            [$ip, $action]
        );
    }

    // ─── HEADERS ──────────────────────────────────────────────────────────

    public static function setSecurityHeaders(bool $isAdmin = false): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');

        if ($isAdmin) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none';");
        }
    }

    // ─── SANITIZACIÓN ─────────────────────────────────────────────────────

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeArray(array $data): array
    {
        return array_map(fn($v) => is_string($v) ? self::sanitize($v) : $v, $data);
    }

    public static function validateEmail(string $email): bool
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validateUploadedImage(array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($mimeType, $allowed, true);
    }

    public static function validateUploadedDocument(array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        return in_array($mimeType, $allowed, true);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────

    public static function getClientIP(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function jsonResponse(bool $success, string $message, array $data = []): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }
}
