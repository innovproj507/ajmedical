<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Repository;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('viewer');
$repo  = new Repository();
$types = $repo->listContentTypes();
$csrfToken = Security::generateCSRF();

$typeKey = $_GET['type'] ?? ($types[0]['key'] ?? null);
$type    = $typeKey ? $repo->getContentType($typeKey) : null;

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$status  = $_GET['status'] ?? '';

$entries = [];
$total   = 0;
if ($type) {
    $opts = ['limit' => $perPage, 'offset' => ($page - 1) * $perPage];
    if ($status !== '') {
        $opts['status'] = $status;
    }
    $entries = $repo->listEntries($type['key'], $opts);
    $total   = $repo->countEntries($type['key'], $status !== '' ? ['status' => $status] : []);
}
$totalPages = (int) max(1, ceil($total / $perPage));

$statusLabels = [
    'draft'     => ['label' => 'Borrador',  'class' => 'bg-gray-100 text-gray-600'],
    'published' => ['label' => 'Publicado', 'class' => 'bg-green-100 text-green-700'],
    'scheduled' => ['label' => 'Programado','class' => 'bg-amber-100 text-amber-700'],
    'archived'  => ['label' => 'Archivado', 'class' => 'bg-red-100 text-red-600'],
];

$pageTitle = $type['label_plural'] ?? 'Contenido';
$activeNav = 'content';
require __DIR__ . '/../partials/header.php';
?>

<div class="flex items-center justify-between mb-5">
    <div class="flex flex-wrap gap-2">
        <?php foreach ($types as $t): ?>
            <a href="/admin/content/index.php?type=<?= urlencode($t['key']) ?>"
               class="px-3 py-1.5 rounded-lg text-sm font-medium border <?= ($type && $type['key'] === $t['key']) ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200 hover:border-aj-teal' ?>">
                <?= e($t['label_plural']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($type): ?>
        <a href="/admin/content/edit.php?type=<?= urlencode($type['key']) ?>"
           class="rounded-lg bg-aj-olive text-white text-sm font-semibold px-4 py-2 hover:opacity-90">
            + Agregar <?= e($type['label']) ?>
        </a>
    <?php endif; ?>
</div>

<?php if (!$type): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">
        No hay tipos de contenido configurados todavía.
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="px-5 py-3 font-medium">Título</th>
                    <th class="px-5 py-3 font-medium">Estado</th>
                    <th class="px-5 py-3 font-medium">Publicado</th>
                    <th class="px-5 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-gray-800"><?= e($entry['title']) ?></td>
                        <td class="px-5 py-3">
                            <?php $st = $statusLabels[$entry['status']] ?? ['label' => $entry['status'], 'class' => 'bg-gray-100 text-gray-600']; ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $st['class'] ?>"><?= e($st['label']) ?></span>
                        </td>
                        <td class="px-5 py-3 text-gray-500"><?= e($entry['published_at'] ?? '—') ?></td>
                        <td class="px-5 py-3 text-right space-x-3">
                            <a href="/admin/content/edit.php?type=<?= urlencode($type['key']) ?>&id=<?= (int) $entry['id'] ?>" class="text-aj-teal font-medium hover:underline">Editar</a>
                            <form method="post" action="/admin/content/delete.php" class="inline"
                                  onsubmit="return confirm('¿Eliminar este contenido? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                                <input type="hidden" name="type" value="<?= e($type['key']) ?>">
                                <button type="submit" class="text-red-600 font-medium hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$entries): ?>
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">No hay contenido todavía.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-1 mt-5">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="/admin/content/index.php?type=<?= urlencode($type['key']) ?>&page=<?= $p ?>"
                   class="px-3 py-1.5 rounded-lg text-sm border <?= $p === $page ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
