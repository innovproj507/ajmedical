<?php

namespace AJM\Core;

/**
 * Auth — Autenticación de administradores. Sesiones en BD, lockout, roles.
 * Generalizado de diplomado-adolescencia/includes/Auth.php.
 */
class Auth
{
    private const SESSION_LIFETIME   = 28800; // 8 horas
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES    = 15;
    private const SESSION_COOKIE     = 'ajm_cms_admin_sess';

    private static ?array $currentUser = null;

    // ─── LOGIN / LOGOUT ───────────────────────────────────────────────────

    public static function login(string $username, string $password): bool
    {
        $db   = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM admin_users WHERE username = ? AND activo = 1 LIMIT 1",
            [trim($username)]
        );

        if (!$user) {
            password_verify($password, '$2y$12$dummyhashfordummyuser123456789012345');
            return false;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt((int) $user['id'], $db);
            return false;
        }

        $db->execute(
            "UPDATE admin_users SET login_attempts = 0, locked_until = NULL, ultimo_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        self::createSession((int) $user['id'], $db);
        Security::resetRateLimit('admin_login');

        return true;
    }

    public static function logout(): void
    {
        $token = $_COOKIE[self::SESSION_COOKIE] ?? '';
        if ($token) {
            Database::getInstance()->execute(
                "DELETE FROM admin_sessions WHERE id = ?",
                [hash('sha256', $token)]
            );
        }
        setcookie(self::SESSION_COOKIE, '', time() - 3600, '/', '', false, true);
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
        self::$currentUser = null;
    }

    // ─── VERIFICACIÓN DE SESIÓN ───────────────────────────────────────────

    public static function check(): bool
    {
        if (self::$currentUser !== null) {
            return true;
        }

        $token = $_COOKIE[self::SESSION_COOKIE] ?? '';
        if (empty($token)) {
            return false;
        }

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT u.id, u.username, u.email, u.nombre_completo, u.rol, u.activo, s.expires_at
             FROM admin_sessions s
             JOIN admin_users u ON u.id = s.user_id
             WHERE s.id = ? AND s.expires_at > NOW() AND u.activo = 1
             LIMIT 1",
            [hash('sha256', $token)]
        );

        if (!$row) {
            return false;
        }

        $expiresAt    = strtotime($row['expires_at']);
        $halfLifetime = self::SESSION_LIFETIME / 2;
        if (($expiresAt - time()) < $halfLifetime) {
            $db->execute(
                "UPDATE admin_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?",
                [self::SESSION_LIFETIME, hash('sha256', $token)]
            );
        }

        self::$currentUser = $row;
        return true;
    }

    public static function requireAuth(string $minRole = 'viewer'): array
    {
        if (!self::check()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . self::adminBasePath() . "/index.php?redirect={$redirect}");
            exit;
        }

        $user  = self::$currentUser;
        $roles = ['viewer' => 1, 'admin' => 2, 'super_admin' => 3];

        if (($roles[$user['rol']] ?? 0) < ($roles[$minRole] ?? 0)) {
            http_response_code(403);
            include __DIR__ . '/../public/admin/partials/403.php';
            exit;
        }

        return $user;
    }

    public static function user(): ?array
    {
        return self::$currentUser;
    }

    public static function hasRole(string $role): bool
    {
        $user  = self::$currentUser;
        $roles = ['viewer' => 1, 'admin' => 2, 'super_admin' => 3];
        return ($roles[$user['rol'] ?? ''] ?? 0) >= ($roles[$role] ?? 0);
    }

    // ─── INTERNOS ─────────────────────────────────────────────────────────

    private static function adminBasePath(): string
    {
        // admin/ vive junto a este archivo (core/../admin) — se calcula en vez de
        // hardcodear para que funcione tanto en spp/public/... como en spp/admin/...
        return '/admin';
    }

    private static function createSession(int $userId, Database $db): void
    {
        $token     = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $token);
        $ip        = Security::getClientIP();
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME);

        // Máximo 3 sesiones activas por usuario (la nueva + 2 anteriores más recientes)
        $db->execute(
            "DELETE FROM admin_sessions WHERE user_id = ? AND id NOT IN (
                SELECT id FROM (SELECT id FROM admin_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 2) t
             )",
            [$userId, $userId]
        );

        $db->execute(
            "INSERT INTO admin_sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)",
            [$tokenHash, $userId, $ip, $ua, $expiresAt]
        );

        setcookie(self::SESSION_COOKIE, $token, [
            'expires'  => time() + self::SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function recordFailedAttempt(int $userId, Database $db): void
    {
        $db->execute(
            "UPDATE admin_users
             SET login_attempts = login_attempts + 1,
                 locked_until = CASE
                     WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     ELSE NULL
                 END
             WHERE id = ?",
            [self::MAX_LOGIN_ATTEMPTS, self::LOCKOUT_MINUTES, $userId]
        );
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function cleanExpiredSessions(): void
    {
        Database::getInstance()->execute("DELETE FROM admin_sessions WHERE expires_at < NOW()");
    }
}
