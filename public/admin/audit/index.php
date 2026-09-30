<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Database;

$currentUser = Auth::requireAuth('admin');
$db = Database::getInstance();

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

$logs  = $db->fetchAll('SELECT * FROM audit_log ORDER BY fecha_accion DESC LIMIT ? OFFSET ?', [$perPage, ($page - 1) * $perPage]);
$total = (int) ($db->fetchOne('SELECT COUNT(*) AS total FROM audit_log')['total'] ?? 0);
$totalPages = (int) max(1, ceil($total / $perPage));

$pageTitle = 'Auditoría';
$activeNav = 'audit';
require __DIR__ . '/../partials/header.php';
?>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
            <tr>
                <th class="px-5 py-3 font-medium">Fecha</th>
                <th class="px-5 py-3 font-medium">Usuario</th>
                <th class="px-5 py-3 font-medium">IP</th>
                <th class="px-5 py-3 font-medium">Acción</th>
                <th class="px-5 py-3 font-medium">Tabla</th>
                <th class="px-5 py-3 font-medium">Registro</th>
                <th class="px-5 py-3 font-medium">Detalle</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($logs as $log): ?>
                <tr class="align-top">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= e($log['fecha_accion']) ?></td>
                    <td class="px-5 py-3"><?= e($log['usuario'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-gray-500"><?= e($log['ip_address'] ?? '—') ?></td>
                    <td class="px-5 py-3 capitalize"><?= e($log['accion']) ?></td>
                    <td class="px-5 py-3"><?= e($log['tabla']) ?></td>
                    <td class="px-5 py-3 text-gray-500">#<?= e($log['registro_id']) ?></td>
                    <td class="px-5 py-3">
                        <details class="text-xs">
                            <summary class="cursor-pointer text-aj-teal">Ver</summary>
                            <pre class="mt-1 max-w-xs overflow-auto whitespace-pre-wrap text-gray-500"><?= e($log['datos_nuevos'] ?? $log['datos_anteriores'] ?? '') ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">Sin registros todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-1 mt-5">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="/admin/audit/index.php?page=<?= $p ?>"
               class="px-3 py-1.5 rounded-lg text-sm border <?= $p === $page ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
