<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Plugins;
use AJM\Core\Repository;

$currentUser = Auth::requireAuth('viewer');
$repo = new Repository();
$db   = Database::getInstance();

// ─── Tarjetas de estadísticas ──────────────────────────────────────────────
$totalContent  = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM content_entries")['total'] ?? 0);
$published     = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM content_entries WHERE status = 'published'")['total'] ?? 0);
$draft         = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM content_entries WHERE status = 'draft'")['total'] ?? 0);
$activeUsers   = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM admin_users WHERE activo = 1")['total'] ?? 0);
$totalUsers    = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM admin_users")['total'] ?? 0);
$newThisMonth  = (int) ($db->fetchOne("SELECT COUNT(*) AS total FROM content_entries WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['total'] ?? 0);

$statCards = [
    ['label' => 'Contenido total', 'value' => $totalContent, 'hint' => "+{$newThisMonth} este mes", 'icon' => 'layers',        'tone' => 'azul'],
    ['label' => 'Publicados',      'value' => $published,    'hint' => 'visibles en el sitio',       'icon' => 'check-circle', 'tone' => 'good'],
    ['label' => 'Borradores',      'value' => $draft,        'hint' => 'pendientes de revisión',     'icon' => 'pencil',       'tone' => 'naranja'],
    ['label' => 'Usuarios activos','value' => $activeUsers,  'hint' => "de {$totalUsers} totales",   'icon' => 'user-check',   'tone' => 'azul'],
];

// Tarjetas de los plugins activos (productos del catálogo, cotizaciones nuevas, ...) primero.
$statCards = array_merge(Plugins::dashboardCards(), $statCards);

$toneClasses = [
    'azul'    => 'bg-aj-teal/10 text-aj-teal',
    'naranja' => 'bg-aj-olive/10 text-aj-olive',
    'good'    => 'bg-green-100 text-green-600',
];

// ─── Contenido publicado por mes (últimos 6 meses) ─────────────────────────
$monthNames = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-{$i} months"));
    $monthly[$ym] = ['label' => $monthNames[substr($ym, 5, 2)], 'count' => 0];
}
$rows = $db->fetchAll(
    "SELECT DATE_FORMAT(published_at, '%Y-%m') AS ym, COUNT(*) AS total
     FROM content_entries
     WHERE status = 'published' AND published_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
     GROUP BY ym"
);
foreach ($rows as $row) {
    if (isset($monthly[$row['ym']])) {
        $monthly[$row['ym']]['count'] = (int) $row['total'];
    }
}
$maxMonthly = max(1, max(array_column($monthly, 'count')));

// ─── Medidores ──────────────────────────────────────────────────────────────
$publishedRatio = $totalContent > 0 ? round(($published / $totalContent) * 100) : 0;
$activeRatio    = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;

// ─── Contenido reciente ─────────────────────────────────────────────────────
$recentContent = $db->fetchAll(
    "SELECT ce.id, ce.title, ce.status, ce.updated_at, ct.label AS type_label, au.nombre_completo AS author_name
     FROM content_entries ce
     JOIN content_types ct ON ct.id = ce.content_type_id
     LEFT JOIN admin_users au ON au.id = ce.author_id
     ORDER BY ce.updated_at DESC
     LIMIT 8"
);
$statusLabels = [
    'draft'     => ['label' => 'Borrador',   'class' => 'bg-gray-100 text-gray-600'],
    'published' => ['label' => 'Publicado',  'class' => 'bg-green-100 text-green-700'],
    'scheduled' => ['label' => 'Programado', 'class' => 'bg-amber-100 text-amber-700'],
    'archived'  => ['label' => 'Archivado',  'class' => 'bg-red-100 text-red-600'],
];

// ─── Actividad diaria (últimos 14 días) para el sparkline ──────────────────
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-{$i} days"))] = 0;
}
$activityRows = $db->fetchAll(
    "SELECT DATE(created_at) AS d, COUNT(*) AS total FROM content_entries WHERE created_at >= ? GROUP BY d",
    [array_key_first($days) . ' 00:00:00']
);
foreach ($activityRows as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']] = (int) $row['total'];
    }
}
$sparkMax = max(1, max($days));
$sparkPoints = [];
$i = 0;
$n = count($days);
foreach ($days as $count) {
    $x = $n > 1 ? round(($i / ($n - 1)) * 100, 1) : 0;
    $y = round(40 - (($count / $sparkMax) * 36), 1);
    $sparkPoints[] = "{$x},{$y}";
    $i++;
}
$sparkLine = implode(' ', $sparkPoints);

// ─── Tipos de contenido, ranqueados ─────────────────────────────────────────
$typeRanking = [];
foreach ($repo->listContentTypes() as $type) {
    $typeRanking[] = [
        'label' => $type['label_plural'],
        'total' => $repo->countEntries($type['key']),
        'href'  => '/admin/content/index.php?type=' . urlencode($type['key']),
    ];
}
usort($typeRanking, fn($a, $b) => $b['total'] <=> $a['total']);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<!-- Tarjetas de estadísticas -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
    <?php foreach ($statCards as $stat): $tag = !empty($stat['href']) ? 'a' : 'div'; ?>
        <<?= $tag ?> <?= $tag === 'a' ? 'href="' . e($stat['href']) . '"' : '' ?> class="block bg-white rounded-xl border border-gray-200 p-5 <?= $tag === 'a' ? 'hover:border-aj-teal/40 hover:shadow-sm transition' : '' ?>">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500"><?= e($stat['label']) ?></p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?= (int) $stat['value'] ?></p>
                </div>
                <span class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 <?= $toneClasses[$stat['tone']] ?>">
                    <?= ajm_icon($stat['icon'], 'w-5 h-5') ?>
                </span>
            </div>
            <p class="text-xs text-gray-400 mt-3"><?= e($stat['hint']) ?></p>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    <!-- Gráfico de barras: contenido publicado por mes -->
    <figure class="xl:col-span-2 bg-white rounded-xl border border-gray-200 p-5 m-0">
        <figcaption class="flex items-center justify-between mb-6">
            <div>
                <h2 class="font-semibold text-gray-800">Contenido publicado por mes</h2>
                <p class="text-xs text-gray-400">Últimos 6 meses</p>
            </div>
        </figcaption>
        <div class="flex items-end justify-between gap-3 h-40 border-b border-gray-100 pb-0">
            <?php foreach ($monthly as $m): $h = round(($m['count'] / $maxMonthly) * 100); ?>
                <div class="flex-1 flex flex-col items-center justify-end h-full gap-2">
                    <span class="text-[11px] font-semibold text-gray-500"><?= $m['count'] > 0 ? (int) $m['count'] : '' ?></span>
                    <div class="w-full max-w-[28px] mx-auto flex items-end h-full">
                        <div class="w-full bg-aj-teal rounded-t"
                             style="height: <?= max(3, $h) ?>%"
                             title="<?= e($m['label']) ?>: <?= (int) $m['count'] ?> publicados"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex items-center justify-between gap-3 mt-2">
            <?php foreach ($monthly as $m): ?>
                <span class="flex-1 text-center text-[11px] text-gray-400"><?= e($m['label']) ?></span>
            <?php endforeach; ?>
        </div>
    </figure>

    <!-- Medidores -->
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-gray-600">Contenido publicado</p>
                <p class="text-sm font-bold text-aj-teal"><?= (int) $publishedRatio ?>%</p>
            </div>
            <div class="h-2.5 rounded-full bg-aj-teal/10">
                <div class="h-2.5 rounded-full bg-aj-teal" style="width: <?= (int) $publishedRatio ?>%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2"><?= (int) $published ?> de <?= (int) $totalContent ?> entradas</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-gray-600">Usuarios activos</p>
                <p class="text-sm font-bold text-aj-olive"><?= (int) $activeRatio ?>%</p>
            </div>
            <div class="h-2.5 rounded-full bg-aj-olive/10">
                <div class="h-2.5 rounded-full bg-aj-olive" style="width: <?= (int) $activeRatio ?>%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2"><?= (int) $activeUsers ?> de <?= (int) $totalUsers ?> usuarios</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Contenido reciente -->
    <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">Contenido reciente</h2>
            <a href="/admin/content/index.php" class="text-xs font-semibold text-aj-teal hover:underline">Ver todo</a>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Título</th>
                    <th class="px-5 py-2.5 font-medium">Tipo</th>
                    <th class="px-5 py-2.5 font-medium">Estado</th>
                    <th class="px-5 py-2.5 font-medium">Autor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentContent as $item): $st = $statusLabels[$item['status']] ?? ['label' => $item['status'], 'class' => 'bg-gray-100 text-gray-600']; ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-gray-800"><?= e($item['title']) ?></td>
                        <td class="px-5 py-3 text-gray-500"><?= e($item['type_label']) ?></td>
                        <td class="px-5 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
                        <td class="px-5 py-3 text-gray-500"><?= e($item['author_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentContent): ?>
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">No hay contenido todavía.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="space-y-6">
        <!-- Tarjeta de actividad -->
        <div class="rounded-xl bg-aj-teal text-white p-5">
            <p class="text-sm text-white/70">Actividad reciente</p>
            <p class="text-2xl font-bold mt-1"><?= array_sum($days) ?> <span class="text-sm font-normal text-white/70">entradas / 14 días</span></p>
            <svg viewBox="0 0 100 40" class="w-full h-10 mt-3" preserveAspectRatio="none">
                <polyline points="<?= e($sparkLine) ?>" fill="none" stroke="#C9C79A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
            <a href="/admin/audit/index.php" class="inline-block mt-4 rounded-lg bg-white/15 hover:bg-white/25 transition text-sm font-semibold px-4 py-2">
                Ver auditoría completa
            </a>
        </div>

        <!-- Tipos de contenido -->
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="font-semibold text-gray-800">Tipos de contenido</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($typeRanking as $t): ?>
                    <li>
                        <a href="<?= e($t['href']) ?>" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                            <span class="text-sm text-gray-700"><?= e($t['label']) ?></span>
                            <span class="flex items-center gap-1 text-sm font-semibold text-gray-800">
                                <?= (int) $t['total'] ?>
                                <?= ajm_icon('chevron-right', 'w-4 h-4 text-gray-300') ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
