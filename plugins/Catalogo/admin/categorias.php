<?php
/**
 * Admin — categorías del catálogo (jerárquicas: una categoría puede tener subcategorías).
 * @var callable $pluginUrl
 * @var string   $pageKey
 */
use AJM\Core\Auth;
use AJM\Core\Security;
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;
use AJM\Plugins\Catalogo\Uploads;

$currentUser = Auth::requireAuth('admin');
$repo = Cat::instance();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$form   = $editId ? $repo->categoriaPorId($editId) : null;
$form ??= ['activo' => 1, 'orden' => 0];
$error  = '';
$flash  = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $repo->eliminarCategoria((int) $_POST['id']);
        header('Location: ' . $pluginUrl('categorias', ['msg' => 'Categoría eliminada. Sus productos quedaron sin categoría.']));
        exit;
    } else {
        $form = array_merge($form, $_POST);
        try {
            $imagen = Uploads::single($_FILES['imagen'] ?? null, 'catalogo', 'image/');
            if ($imagen) {
                $form['imagen_id'] = $imagen;
            } elseif (!empty($_POST['quitar_imagen'])) {
                $form['imagen_id'] = null;
            }
            $form['activo'] = !empty($_POST['activo']);
            $repo->guardarCategoria($form, $editId);
            header('Location: ' . $pluginUrl('categorias', ['msg' => 'Categoría guardada.']));
            exit;
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$arbol     = $repo->categoriasArbol(false);
$csrfToken = Security::generateCSRF();
$excluir   = $editId ? $repo->descendientesIds($editId) : [];

$pageTitle = 'Catálogo — Categorías';
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
                    <th class="px-5 py-3 font-medium">Categoría</th>
                    <th class="px-5 py-3 font-medium">Productos</th>
                    <th class="px-5 py-3 font-medium">Orden</th>
                    <th class="px-5 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($arbol as $c): ?>
                    <tr class="<?= $editId === (int) $c['id'] ? 'bg-aj-teal/5' : '' ?>">
                        <td class="px-5 py-3" style="padding-left: <?= 1.25 + $c['depth'] * 1.5 ?>rem">
                            <div class="flex items-center gap-2">
                                <?php if ($c['depth'] > 0): ?><span class="text-gray-300">└</span><?php endif; ?>
                                <?php if ($c['imagen_path']): ?>
                                    <img src="/uploads/<?= e($c['imagen_path']) ?>" alt="" class="w-8 h-8 rounded object-cover">
                                <?php endif; ?>
                                <span class="<?= $c['depth'] === 0 ? 'font-medium text-gray-800' : 'text-gray-600' ?>"><?= e($c['nombre']) ?></span>
                                <?php if (!$c['activo']): ?><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">Oculta</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-500">
                            <a href="<?= e($pluginUrl('productos', ['categoria' => $c['id']])) ?>" class="hover:text-aj-teal"><?= (int) $c['total_arbol'] ?></a>
                        </td>
                        <td class="px-5 py-3 text-gray-500"><?= (int) $c['orden'] ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap space-x-3">
                            <a href="<?= e($pluginUrl('categorias', ['edit' => $c['id']])) ?>" class="text-aj-teal font-medium hover:underline">Editar</a>
                            <form method="post" class="inline" onsubmit="return confirm('¿Eliminar esta categoría? Sus productos quedarán sin categoría y sus subcategorías pasarán al nivel superior.');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button name="action" value="delete" class="text-red-600 font-medium hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$arbol): ?>
                    <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">Aún no hay categorías. Crea la primera con el formulario.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 self-start">
        <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-1"><?= $editId ? 'Editar categoría' : 'Nueva categoría' ?></h2>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre *</label>
            <input type="text" name="nombre" required value="<?= e($form['nombre'] ?? '') ?>" class="<?= $input ?>">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Categoría padre</label>
            <select name="parent_id" class="<?= $input ?>">
                <option value="">— Ninguna (nivel principal) —</option>
                <?php foreach ($arbol as $c): if (in_array((int) $c['id'], $excluir, true)) continue; ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) ($form['parent_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Descripción</label>
            <textarea name="descripcion" rows="3" class="<?= $input ?>"><?= e($form['descripcion'] ?? '') ?></textarea>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Imagen <span class="font-normal">(banner / tarjeta del inicio)</span></label>
            <?php if (!empty($form['imagen_id']) && ($img = (new \AJM\Core\Media())->find((int) $form['imagen_id']))): ?>
                <div class="flex items-center gap-2 mb-2">
                    <img src="/uploads/<?= e($img['path']) ?>" alt="" class="w-16 h-10 rounded object-cover">
                    <label class="text-xs text-red-600 flex items-center gap-1"><input type="checkbox" name="quitar_imagen" value="1"> Quitar</label>
                    <input type="hidden" name="imagen_id" value="<?= (int) $img['id'] ?>">
                </div>
            <?php endif; ?>
            <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp" class="text-sm w-full">
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
        <button type="submit" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90"><?= $editId ? 'Guardar cambios' : 'Crear categoría' ?></button>
        <?php if ($editId): ?>
            <a href="<?= e($pluginUrl('categorias')) ?>" class="block text-center text-sm text-gray-500 hover:underline">Cancelar</a>
        <?php endif; ?>
    </form>
</div>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
