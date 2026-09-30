<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Plugins;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('super_admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        $key = (string) ($_POST['plugin'] ?? '');
        try {
            if (($_POST['action'] ?? '') === 'enable') {
                Plugins::enable($key);
                $success = 'Plugin activado.';
            } elseif (($_POST['action'] ?? '') === 'disable') {
                Plugins::disable($key);
                $success = 'Plugin desactivado. Sus datos se conservan.';
            }
        } catch (\Throwable $e) {
            $error = 'No se pudo cambiar el estado del plugin: ' . $e->getMessage();
        }
    }
}

$plugins   = Plugins::all();
$csrfToken = Security::generateCSRF();

$pageTitle = 'Plugins';
$activeNav = 'plugins';
require __DIR__ . '/../partials/header.php';
?>

<?php if ($success): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<p class="text-sm text-gray-500 mb-5">
    Los plugins agregan secciones al sitio y al panel. Viven en la carpeta <code class="bg-gray-100 px-1 rounded">plugins/</code>;
    al activarlos por primera vez se crean sus tablas. Desactivar un plugin oculta sus páginas pero no borra sus datos.
</p>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <?php foreach ($plugins as $plugin): $state = Plugins::stateOf($plugin['key']); $on = !empty($state['enabled']); ?>
        <div class="bg-white rounded-xl border <?= $on ? 'border-aj-teal/40' : 'border-gray-200' ?> p-5 flex gap-4">
            <span class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 <?= $on ? 'bg-aj-teal/10 text-aj-teal' : 'bg-gray-100 text-gray-400' ?>">
                <?= ajm_icon($plugin['admin_nav'][0]['icon'] ?? 'plug', 'w-6 h-6') ?>
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-gray-800"><?= e($plugin['name']) ?></h2>
                    <span class="text-xs text-gray-400">v<?= e($plugin['version'] ?? '1.0.0') ?></span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $on ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>"><?= $on ? 'Activo' : 'Inactivo' ?></span>
                </div>
                <p class="text-sm text-gray-500 mt-1"><?= e($plugin['description'] ?? '') ?></p>
                <form method="post" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="plugin" value="<?= e($plugin['key']) ?>">
                    <?php if ($on): ?>
                        <input type="hidden" name="action" value="disable">
                        <button type="submit" onclick="return confirm('¿Desactivar este plugin? Sus páginas dejarán de verse en el sitio.')"
                                class="rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2 hover:bg-gray-50">Desactivar</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="enable">
                        <button type="submit" class="rounded-lg bg-aj-teal text-white text-sm font-semibold px-4 py-2 hover:opacity-90">Activar</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$plugins): ?>
        <p class="text-gray-400 text-sm">No hay plugins en la carpeta plugins/.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
