<?php

namespace AJM\Core;

/**
 * Plugins — descubre y carga los módulos opcionales de plugins/<key>/plugin.php.
 *
 * Cada plugin.php devuelve un array con su manifiesto:
 *   key, name, version, description
 *   install        ruta a un .sql que crea sus tablas (se ejecuta al activarlo por primera vez)
 *   routes         fn(Router $router, array $ctx): void — rutas públicas; $ctx trae renderPage y templates
 *   admin_nav      [['key','label','href','icon','min_role','badge' => fn(): int], ...] — entradas del menú lateral
 *   admin_pages    ['slug' => '/ruta/al/archivo.php'] — servidas por /admin/plugin.php?p=<key>&page=<slug>
 *   dashboard      fn(): array — tarjetas extra para el dashboard (mismo formato que $statCards)
 *
 * El estado (activo / versión instalada) vive en la tabla `plugins`, editable en /admin/plugins.
 */
class Plugins
{
    private static ?array $manifests = null;
    private static ?array $state = null;

    /** Todos los plugins presentes en disco, activos o no. */
    public static function all(): array
    {
        if (self::$manifests !== null) {
            return self::$manifests;
        }

        self::$manifests = [];
        foreach (glob(ROOT_PATH . '/plugins/*/plugin.php') ?: [] as $file) {
            $manifest = require $file;
            if (is_array($manifest) && !empty($manifest['key'])) {
                $manifest['path'] = dirname($file);
                self::$manifests[$manifest['key']] = $manifest;
            }
        }
        ksort(self::$manifests);
        return self::$manifests;
    }

    /** Solo los plugins activos (instalados y habilitados). */
    public static function enabled(): array
    {
        $state = self::state();
        return array_filter(self::all(), fn($p) => !empty($state[$p['key']]['enabled']));
    }

    public static function isEnabled(string $key): bool
    {
        return isset(self::enabled()[$key]);
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function stateOf(string $key): ?array
    {
        return self::state()[$key] ?? null;
    }

    // ─── CICLO DE VIDA ────────────────────────────────────────────────────

    public static function enable(string $key): void
    {
        $plugin = self::get($key);
        if (!$plugin) {
            throw new \InvalidArgumentException("Plugin desconocido: {$key}");
        }

        $db    = Database::getInstance();
        $state = self::stateOf($key);

        if (!$state || $state['installed_version'] === null) {
            self::runSqlFile($plugin['install'] ?? null);
        }

        $db->execute(
            "INSERT INTO plugins (`key`, enabled, installed_version) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE enabled = 1, installed_version = VALUES(installed_version)",
            [$key, $plugin['version'] ?? '1.0.0']
        );
        AuditLogger::log('plugins', $key, 'update', $state, ['enabled' => 1]);
        self::$state = null;
    }

    /**
     * Aplica las migraciones pendientes de los plugins activos: cada manifiesto puede declarar
     * 'migrations' => ['1.1.0' => '/ruta/1.1.0.sql', ...]; se ejecutan en orden las que sean
     * más nuevas que la versión instalada. Se llama al entrar al admin (costo: una comparación de versiones).
     */
    public static function migrate(): void
    {
        foreach (self::enabled() as $plugin) {
            $instalada = self::stateOf($plugin['key'])['installed_version'] ?? '0';
            $objetivo  = $plugin['version'] ?? '1.0.0';
            if (version_compare($instalada, $objetivo, '>=')) {
                continue;
            }
            $migraciones = $plugin['migrations'] ?? [];
            uksort($migraciones, 'version_compare');
            foreach ($migraciones as $version => $archivo) {
                if (version_compare($version, $instalada, '>') && version_compare($version, $objetivo, '<=')) {
                    self::runSqlFile($archivo);
                }
            }
            Database::getInstance()->execute('UPDATE plugins SET installed_version = ? WHERE `key` = ?', [$objetivo, $plugin['key']]);
            AuditLogger::log('plugins', $plugin['key'], 'update', ['installed_version' => $instalada], ['installed_version' => $objetivo]);
        }
        self::$state = null;
    }

    public static function disable(string $key): void
    {
        $before = self::stateOf($key);
        Database::getInstance()->execute('UPDATE plugins SET enabled = 0 WHERE `key` = ?', [$key]);
        AuditLogger::log('plugins', $key, 'update', $before, ['enabled' => 0]);
        self::$state = null;
    }

    // ─── INTEGRACIÓN ──────────────────────────────────────────────────────

    public static function registerRoutes(Router $router, array $ctx): void
    {
        foreach (self::enabled() as $plugin) {
            if (isset($plugin['routes']) && is_callable($plugin['routes'])) {
                $plugin['routes']($router, $ctx);
            }
        }
    }

    public static function adminNav(): array
    {
        $items = [];
        foreach (self::enabled() as $plugin) {
            foreach ($plugin['admin_nav'] ?? [] as $item) {
                if (!Auth::hasRole($item['min_role'] ?? 'viewer')) {
                    continue;
                }
                if (isset($item['badge']) && is_callable($item['badge'])) {
                    try {
                        $item['badge'] = (int) $item['badge']();
                    } catch (\Throwable $e) {
                        $item['badge'] = 0;
                    }
                }
                $items[$item['key']] = $item;
            }
        }
        return $items;
    }

    public static function dashboardCards(): array
    {
        $cards = [];
        foreach (self::enabled() as $plugin) {
            if (isset($plugin['dashboard']) && is_callable($plugin['dashboard'])) {
                try {
                    $cards = array_merge($cards, $plugin['dashboard']());
                } catch (\Throwable $e) {
                    error_log('[Plugins] dashboard ' . $plugin['key'] . ': ' . $e->getMessage());
                }
            }
        }
        return $cards;
    }

    // ─── INTERNOS ─────────────────────────────────────────────────────────

    private static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }
        self::$state = [];
        try {
            foreach (Database::getInstance()->fetchAll('SELECT * FROM plugins') as $row) {
                self::$state[$row['key']] = $row;
            }
        } catch (\Throwable $e) {
            error_log('[Plugins] No se pudo leer la tabla plugins: ' . $e->getMessage());
        }
        return self::$state;
    }

    /**
     * Ejecuta un .sql sentencia por sentencia. Los archivos de instalación de los plugins
     * son simples (CREATE TABLE / INSERT), sin procedimientos ni delimitadores custom.
     */
    public static function runSqlFile(?string $path): void
    {
        if (!$path || !is_file($path)) {
            return;
        }
        $sql = file_get_contents($path);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $pdo = Database::getInstance()->getPdo();
        foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql)))) as $statement) {
            $pdo->exec($statement);
        }
    }
}
