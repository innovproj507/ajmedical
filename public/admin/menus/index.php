<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\AuditLogger;
use AJM\Core\Database;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('admin');
$db = Database::getInstance();

$menu = $db->fetchOne("SELECT * FROM menus WHERE `key` = 'principal'");
if (!$menu) {
    $db->execute("INSERT INTO menus (`key`, label) VALUES ('principal', 'Menú Principal')");
    $menu = $db->fetchOne("SELECT * FROM menus WHERE `key` = 'principal'");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $label = trim($_POST['label'] ?? '');
        $url   = trim($_POST['url'] ?? '');
        if ($label === '') {
            $error = 'El texto del enlace es obligatorio.';
        } else {
            $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
            $db->execute(
                "INSERT INTO menu_items (menu_id, parent_id, label, url, target_type, sort_order) VALUES (?,?,?,?,'url',?)",
                [$menu['id'], $parentId, $label, $url ?: '#', (int) ($_POST['sort_order'] ?? 0)]
            );
            AuditLogger::log('menu_items', (string) $db->lastInsertId(), 'insert', null, ['label' => $label, 'url' => $url]);
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->execute('DELETE FROM menu_items WHERE id = ? AND menu_id = ?', [$id, $menu['id']]);
        AuditLogger::log('menu_items', (string) $id, 'delete', null, null);
    }
}

$items = $db->fetchAll('SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC', [$menu['id']]);
$topLevel = array_filter($items, fn($i) => empty($i['parent_id']));
$csrfToken = Security::generateCSRF();

$pageTitle = 'Menú Principal';
$activeNav = 'menus';
require __DIR__ . '/../partials/header.php';
?>

<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="px-5 py-3 font-medium">Texto</th>
                    <th class="px-5 py-3 font-medium">URL</th>
                    <th class="px-5 py-3 font-medium">Orden</th>
                    <th class="px-5 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-5 py-3 <?= $item['parent_id'] ? 'pl-10 text-gray-500' : 'font-medium text-gray-800' ?>">
                            <?= $item['parent_id'] ? '↳ ' : '' ?><?= e($item['label']) ?>
                        </td>
                        <td class="px-5 py-3 text-gray-500"><?= e($item['url'] ?? '') ?></td>
                        <td class="px-5 py-3 text-gray-500"><?= (int) $item['sort_order'] ?></td>
                        <td class="px-5 py-3 text-right">
                            <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este enlace?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="text-red-600 font-medium hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">El menú está vacío.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">Agregar enlace</h2>
        <form method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Texto</label>
                <input type="text" name="label" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">URL</label>
                <input type="text" name="url" placeholder="/catalogo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Enlace padre (submenú)</label>
                <select name="parent_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">— Ninguno (nivel superior) —</option>
                    <?php foreach ($topLevel as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Orden</label>
                <input type="number" name="sort_order" value="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90">Agregar</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
