<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\AuditLogger;
use AJM\Core\Database;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('super_admin');
$db = Database::getInstance();

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    $values = $_POST['settings'] ?? [];
    foreach ($values as $key => $value) {
        $before = $db->fetchOne('SELECT value FROM settings WHERE `key` = ?', [$key]);
        $db->execute('UPDATE settings SET value = ? WHERE `key` = ?', [$value, $key]);
        AuditLogger::log('settings', $key, 'update', $before, ['value' => $value]);
    }
    $success = true;
}

$settings = $db->fetchAll('SELECT * FROM settings ORDER BY category, id');
$grouped = [];
foreach ($settings as $s) {
    $grouped[$s['category']][] = $s;
}

// Orden y nombre visible de cada categoría de settings (las no listadas van al final con su clave).
$categoryLabels = [
    'general'  => 'General',
    'contacto' => 'Datos de contacto',
    'redes'    => 'Redes sociales',
    'hero'     => 'Portada (inicio)',
    'catalogo' => 'Catálogo',
    'email'    => 'Correo',
    'smtp'     => 'Servidor SMTP',
];
uksort($grouped, function ($a, $b) use ($categoryLabels) {
    $keys = array_keys($categoryLabels);
    $ia = array_search($a, $keys, true);
    $ib = array_search($b, $keys, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a, $b);
});

$csrfToken = Security::generateCSRF();
$pageTitle = 'Configuración';
$activeNav = 'settings';
require __DIR__ . '/../partials/header.php';
?>

<?php if ($success): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">Configuración actualizada.</div>
<?php endif; ?>

<p class="text-sm text-gray-500 mb-5">Las credenciales sensibles (usuario/contraseña SMTP) se configuran en el archivo <code class="bg-gray-100 px-1 rounded">.env</code> del servidor, no aquí.</p>

<form method="post" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <?php foreach ($grouped as $category => $items): ?>
        <div id="<?= e($category) ?>" class="bg-white rounded-xl border border-gray-200 p-5 scroll-mt-6">
            <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-4"><?= e($categoryLabels[$category] ?? $category) ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($items as $item): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e($item['label'] ?? $item['key']) ?></label>
                        <?php if ($item['type'] === 'boolean'): ?>
                            <select name="settings[<?= e($item['key']) ?>]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="true" <?= $item['value'] === 'true' ? 'selected' : '' ?>>Sí</option>
                                <option value="false" <?= $item['value'] === 'false' ? 'selected' : '' ?>>No</option>
                            </select>
                        <?php elseif ($item['type'] === 'textarea'): ?>
                            <textarea name="settings[<?= e($item['key']) ?>]" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?= e($item['value'] ?? '') ?></textarea>
                        <?php else: ?>
                            <input type="<?= $item['type'] === 'number' ? 'number' : ($item['type'] === 'email' ? 'email' : 'text') ?>"
                                   name="settings[<?= e($item['key']) ?>]" value="<?= e($item['value'] ?? '') ?>"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="rounded-lg bg-aj-teal text-white font-semibold px-6 py-2.5 text-sm hover:opacity-90">Guardar configuración</button>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
