<?php

declare(strict_types=1);

/**
 * Punto de entrada de las páginas admin de los plugins:
 *   /admin/plugin.php?p=<plugin>&page=<página>
 * Cada página es un .php del propio plugin (manifiesto: admin_pages) que hace su propio
 * Auth::requireAuth() con el rol que necesite e incluye ADMIN_PARTIALS/header.php.
 */

require_once __DIR__ . '/bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Plugins;

Auth::requireAuth('viewer');

$pluginKey = (string) ($_GET['p'] ?? '');
$pageKey   = (string) ($_GET['page'] ?? '');
$plugin    = Plugins::enabled()[$pluginKey] ?? null;
$file      = $plugin['admin_pages'][$pageKey] ?? null;

if (!$plugin || !$file || !is_file($file)) {
    http_response_code(404);
    $pageTitle = 'No encontrado';
    require __DIR__ . '/partials/header.php';
    echo '<div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">Esta sección no existe o el plugin está desactivado.</div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

// URL base de las páginas del plugin, para que sus enlaces no tengan que repetir ?p=...
$pluginUrl = fn(string $page, array $query = []): string
    => '/admin/plugin.php?' . http_build_query(['p' => $pluginKey, 'page' => $page] + $query);

// Marca como activa la entrada del menú lateral del plugin que declara esta página.
foreach ($plugin['admin_nav'] ?? [] as $nav) {
    if (in_array($pageKey, $nav['pages'] ?? [], true)) {
        $activeNav = $nav['key'];
        break;
    }
}

require $file;
