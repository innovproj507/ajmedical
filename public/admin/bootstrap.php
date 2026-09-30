<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);
// Las páginas admin de los plugins viven fuera de public/admin y cargan el layout desde aquí.
define('ADMIN_PARTIALS', __DIR__ . '/partials');

require_once __DIR__ . '/../../config/config.php';

use AJM\Core\Auth;
use AJM\Core\Security;

Security::setSecurityHeaders(true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Migraciones pendientes de los plugins (p. ej. columnas nuevas tras actualizar el código).
try {
    \AJM\Core\Plugins::migrate();
} catch (\Throwable $e) {
    error_log('[Plugins] Migración fallida: ' . $e->getMessage());
}

// Limpieza aleatoria de sesiones expiradas (~2% de los requests admin)
if (random_int(1, 50) === 1) {
    Auth::cleanExpiredSessions();
}
