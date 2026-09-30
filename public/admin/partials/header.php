<?php
/** @var array $currentUser */
/** @var string $pageTitle */
/** @var string $activeNav */
use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Plugins;

$currentUser ??= Auth::user();
$pageTitle   ??= 'Panel';
$activeNav   ??= '';

$navItems = [
    'dashboard' => ['label' => 'Dashboard',     'href' => '/admin/dashboard.php',        'icon' => 'home'],
];
// Secciones que agregan los plugins activos (Catálogo, Cotizaciones, ...) van justo después del Dashboard.
$navItems += Plugins::adminNav();
$navItems += [
    'content'   => ['label' => 'Contenido',     'href' => '/admin/content/index.php',    'icon' => 'file-text'],
    'media'     => ['label' => 'Media',         'href' => '/admin/media/index.php',      'icon' => 'image'],
    'menus'     => ['label' => 'Menús',         'href' => '/admin/menus/index.php',      'icon' => 'list'],
    'users'     => ['label' => 'Usuarios',      'href' => '/admin/users/index.php',      'icon' => 'users',    'min_role' => 'super_admin'],
    'plugins'   => ['label' => 'Plugins',       'href' => '/admin/plugins/index.php',    'icon' => 'plug',     'min_role' => 'super_admin'],
    'settings'  => ['label' => 'Configuración', 'href' => '/admin/settings/index.php',   'icon' => 'settings', 'min_role' => 'super_admin'],
    'audit'     => ['label' => 'Auditoría',     'href' => '/admin/audit/index.php',      'icon' => 'clock',    'min_role' => 'admin'],
];
$navItems = array_filter($navItems, fn($item) => Auth::hasRole($item['min_role'] ?? 'viewer'));

$initials = '';
foreach (explode(' ', trim($currentUser['nombre_completo'] ?? '')) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2) ?: 'AJ';

try {
    $pendingDrafts = (int) (Database::getInstance()
        ->fetchOne("SELECT COUNT(*) AS total FROM content_entries WHERE status = 'draft'")['total'] ?? 0);
} catch (\Throwable $e) {
    $pendingDrafts = 0;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — AJ Medical CMS</title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dist/css/admin.min.css">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased font-sans">
<div class="flex min-h-screen">

    <aside class="w-64 shrink-0 bg-white border-r border-gray-200 flex flex-col">
        <div class="px-5 py-5 border-b border-gray-100">
            <a href="/admin/dashboard.php" class="block">
                <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>" class="h-10 w-auto">
            </a>
            <p class="text-[11px] text-gray-400 leading-tight mt-2">Panel de administración</p>
        </div>

        <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
            <?php foreach ($navItems as $key => $item): $isActive = $activeNav === $key; ?>
                <a href="<?= e($item['href']) ?>"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition <?= $isActive ? 'bg-aj-teal/10 text-aj-teal' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800' ?>">
                    <?= ajm_icon($item['icon'], 'w-5 h-5 shrink-0 ' . ($isActive ? 'text-aj-teal' : 'text-gray-400')) ?>
                    <span class="truncate flex-1"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="min-w-[20px] h-5 px-1.5 rounded-full bg-aj-olive text-white text-[11px] font-bold flex items-center justify-center"><?= (int) $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-3 border-t border-gray-100">
            <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-3 py-2.5">
                <span class="w-9 h-9 rounded-full bg-aj-teal text-white flex items-center justify-center font-semibold text-xs shrink-0"><?= e($initials) ?></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= e($currentUser['nombre_completo'] ?? '') ?></p>
                    <p class="text-[11px] text-gray-400 capitalize truncate"><?= e($currentUser['rol'] ?? '') ?></p>
                </div>
                <a href="/admin/logout.php" title="Cerrar sesión" class="text-gray-400 hover:text-aj-olive shrink-0">
                    <?= ajm_icon('logout', 'w-4 h-4') ?>
                </a>
            </div>
        </div>
    </aside>

    <main class="flex-1 min-w-0 flex flex-col">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between gap-6">
            <h1 class="text-xl font-bold text-gray-800 shrink-0"><?= e($pageTitle) ?></h1>

            <div class="hidden md:flex flex-1 max-w-sm">
                <label class="relative w-full">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400"><?= ajm_icon('search', 'w-4 h-4') ?></span>
                    <input type="text" placeholder="Buscar en el panel…" disabled
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-10 pr-3 py-2 text-sm text-gray-500 placeholder:text-gray-400 cursor-not-allowed">
                </label>
            </div>

            <div class="flex items-center gap-4 shrink-0">
                <?php if (setting('MAINTENANCE_MODE', false) === true): ?>
                    <a href="/admin/settings/index.php" title="El público ve la página de mantenimiento; tú ves el sitio porque tienes sesión iniciada"
                       class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Sitio en mantenimiento
                    </a>
                <?php endif; ?>
                <a href="/" target="_blank" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-aj-teal">
                    <?= ajm_icon('eye', 'w-4 h-4') ?> Ver sitio
                </a>
                <a href="/admin/content/index.php?status=draft" title="<?= (int) $pendingDrafts ?> borradores pendientes" class="relative text-gray-400 hover:text-aj-teal">
                    <?= ajm_icon('bell', 'w-5 h-5') ?>
                    <?php if ($pendingDrafts > 0): ?>
                        <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-aj-olive text-white text-[10px] font-bold flex items-center justify-center"><?= min(9, $pendingDrafts) ?></span>
                    <?php endif; ?>
                </a>
                <span class="w-9 h-9 rounded-full bg-aj-teal/10 text-aj-teal flex items-center justify-center font-semibold text-xs"><?= e($initials) ?></span>
            </div>
        </header>
        <div class="p-8 flex-1">
