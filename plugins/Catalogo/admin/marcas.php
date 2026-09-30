<?php
/**
 * Admin — marcas / fabricantes del catálogo.
 * @var callable $pluginUrl
 * @var string   $pageKey
 */
use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Security;
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;
use AJM\Plugins\Catalogo\Uploads;

$currentUser = Auth::requireAuth('admin');
$repo = Cat::instance();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$form   = $editId ? Database::getInstance()->fetchOne('SELECT * FROM cat_marcas WHERE id = ?', [$editId]) : null;
$form ??= ['activo' => 1];
$error  = '';
$flash  = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $repo->eliminarMarca((int) $_POST['id']);
        header('Location: ' . $pluginUrl('marcas', ['msg' => 'Marca eliminada.']));
        exit;
    } else {
        $form = array_merge($form, $_POST);
        try {
            $logo = Uploads::single($_FILES['logo'] ?? null, 'catalogo/marcas', 'image/');
            if ($logo) {
                $form['logo_id'] = $logo;
            } elseif (!empty($_POST['quitar_logo'])) {
                $form['logo_id'] = null;
            }
            $form['activo'] = !empty($_POST['activo']);
            $repo->guardarMarca($form, $editId);
            header('Location: ' . $pluginUrl('marcas', ['msg' => 'Marca guardada.']));
            exit;
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$marcas    = $repo->marcas(false);
$csrfToken = Security::generateCSRF();

$pageTitle = 'Catálogo — Marcas';
require ADMIN_PARTIALS . '/header.php';
include __DIR__ . '/_tabs.php';

$input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal';
?>

<?php if ($flash): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="px-5 py-3 font-medium">Marca / fabricante</th>
                    <th class="px-5 py-3 font-medium">Productos</th>
                    <th class="px-5 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($marcas as $m): ?>
                    <tr class="<?= $editId === (int) $m['id'] ? 'bg-aj-teal/5' : '' ?>">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-12 h-8 rounded bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                    <?php if ($m['logo_path']): ?>
                                        <img src="/uploads/<?= e($m['logo_path']) ?>" alt="" class="max-w-full max-h-full object-contain">
                                    <?php else: ?>
                                        <?= ajm_icon('tag', 'w-4 h-4 text-gray-300') ?>
                                    <?php endif; ?>
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-800"><?= e($m['nombre']) ?></span>
                                    <?php if ($m['razon_social']): ?><span class="block text-xs text-gray-400 truncate"><?= e($m['razon_social']) ?></span><?php endif; ?>
                                </span>
                                <?php if (!$m['activo']): ?><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Oculta</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-500"><?= (int) $m['total'] ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-3">
                            <a href="<?= e($pluginUrl('marcas', ['edit' => $m['id']])) ?>" class="text-aj-teal font-medium hover:underline">Editar</a>
                            <form method="post" class="inline" onsubmit="return confirm('¿Eliminar esta marca? Sus productos quedarán sin marca.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button name="action" value="delete" class="text-red-600 font-medium hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$marcas): ?>
                    <tr><td colspan="3" class="px-5 py-10 text-center text-gray-400">Aún no hay marcas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 self-start">
        <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-1"><?= $editId ? 'Editar marca' : 'Nueva marca' ?></h2>
        <p class="text-xs text-gray-400 -mt-1">Las marcas con logo aparecen en "Fabricantes que representamos" del inicio.</p>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre *</label>
            <input type="text" name="nombre" required value="<?= e($form['nombre'] ?? '') ?>" class="<?= $input ?>">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Razón social del fabricante</label>
            <input type="text" name="razon_social" value="<?= e($form['razon_social'] ?? '') ?>" placeholder="Ej. ZHEJIANG HUAFU MEDICAL EQUIPMENT CO., LTD" class="<?= $input ?>">
            <p class="text-[11px] text-gray-400 mt-1">Se muestra en la ficha del producto. El nombre corto es el que se ve en filtros y tarjetas.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Logo</label>
            <?php if (!empty($form['logo_id']) && ($img = (new \AJM\Core\Media())->find((int) $form['logo_id']))): ?>
                <div class="flex items-center gap-2 mb-2">
                    <img src="/uploads/<?= e($img['path']) ?>" alt="" class="h-10 max-w-[120px] object-contain">
                    <label class="text-xs text-red-600 flex items-center gap-1"><input type="checkbox" name="quitar_logo" value="1"> Quitar</label>
                    <input type="hidden" name="logo_id" value="<?= (int) $img['id'] ?>">
                </div>
            <?php endif; ?>
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="text-sm w-full">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Orden</label>
                <input type="number" name="orden" value="<?= (int) ($form['orden'] ?? 0) ?>" class="<?= $input ?>">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Slug</label>
                <input type="text" name="slug" value="<?= e($form['slug'] ?? '') ?>" placeholder="automático" class="<?= $input ?>">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="activo" value="1" <?= !empty($form['activo']) ? 'checked' : '' ?> class="rounded border-gray-300"> Visible en el sitio
        </label>
        <button type="submit" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90"><?= $editId ? 'Guardar cambios' : 'Crear marca' ?></button>
        <?php if ($editId): ?>
            <a href="<?= e($pluginUrl('marcas')) ?>" class="block text-center text-sm text-gray-500 hover:underline">Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
