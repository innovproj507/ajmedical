<?php
/**
 * AJ Medical CMS — Configuration bootstrap.
 * Secrets vienen de .env; el resto son constantes de aplicación o vienen
 * de la tabla `settings` (editable desde admin/settings/index.php).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

// 1. .env loader (mismo patrón que diplomado-adolescencia/api/config.php)
if (!function_exists('ajm_load_env')) {
    function ajm_load_env(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}
ajm_load_env(__DIR__ . '/../.env');

// 2. Credenciales de base de datos
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'ajmedical_cms');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// 3. Rutas de aplicación
if (!defined('SITE_URL'))    define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'http://localhost/', '/'));
if (!defined('APP_ENV'))     define('APP_ENV', getenv('APP_ENV') ?: 'production');
if (!defined('APP_DEBUG'))   define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
if (!defined('ROOT_PATH'))   define('ROOT_PATH', dirname(__DIR__));
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', ROOT_PATH . '/storage');
// Los archivos subidos deben ser descargables por URL (PDFs de documentos/revista/eventos),
// así que viven dentro de public/ — a diferencia de storage/logs y storage/cache, que no.
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// 4. Configuración dinámica desde la tabla `settings` (editable en admin sin redeploy)
if (!function_exists('ajm_load_dynamic_settings')) {
    function ajm_load_dynamic_settings(): void
    {
        try {
            $db = \AJM\Core\Database::getInstance();
            $rows = $db->fetchAll('SELECT `key`, `value` FROM settings');
            foreach ($rows as $row) {
                $key = $row['key'];
                if (defined($key)) {
                    continue;
                }
                $value = $row['value'];
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                define($key, $value);
            }
        } catch (\Throwable $e) {
            error_log('[Config] No se pudieron cargar los settings dinámicos: ' . $e->getMessage());
        }
    }
}
ajm_load_dynamic_settings();

// 5. Claves sensibles — SIEMPRE desde .env, nunca desde la tabla `settings`
foreach (['SMTP_USER', 'SMTP_PASS'] as $key) {
    if (!defined($key)) {
        define($key, getenv($key) ?: '');
    }
}

// 6. Fallbacks de aplicación por si la tabla `settings` aún no tiene una fila
$appDefaults = [
    'SITE_NAME'        => 'AJ Medical Supply',
    'SITE_TAGLINE'     => 'Insumos médicos y hospitalarios',
    'MAINTENANCE_MODE' => false,
    'EMAIL_FROM'       => 'noreply@ajmedicalsupply.com',
    'EMAIL_FROM_NAME'  => 'AJ Medical Supply',
    'EMAIL_ADMIN'      => 'admin@ajmedicalsupply.com',
    'SMTP_HOST'        => 'smtp.gmail.com',
    'SMTP_PORT'        => 465,
    'SMTP_SECURITY'    => 'ssl',
    'SMTP_ENABLED'     => false,
    // Datos de contacto y redes (layout público) — editables en /admin/settings. Vacío = no se muestra.
    'CONTACT_PHONE'    => '',
    'CONTACT_WHATSAPP' => '',
    'CONTACT_EMAIL'    => '',
    'CONTACT_ADDRESS'  => '',
    'CONTACT_HOURS'    => '',
    'SOCIAL_FACEBOOK'  => '',
    'SOCIAL_INSTAGRAM' => '',
    'SOCIAL_LINKEDIN'  => '',
    // Hero de inicio (templates/public/home.php) — categoría "hero" en /admin/settings.
    'HERO_EYEBROW'      => 'Distribuidores de insumos médicos',
    'HERO_TITLE'        => 'Insumos médicos confiables para cuidar mejor',
    'HERO_SUBTITLE'     => '',
    'HERO_STAT_1_VALUE' => '',
    'HERO_STAT_1_LABEL' => '',
    'HERO_STAT_2_VALUE' => '',
    'HERO_STAT_2_LABEL' => '',
];
foreach ($appDefaults as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}
