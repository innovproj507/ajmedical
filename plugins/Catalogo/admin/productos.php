<?php
/**
 * Admin — listado de productos del catálogo.
 * @var callable $pluginUrl
 * @var string   $pageKey
 */
use AJM\Core\Auth;
use AJM\Core\Security;
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$currentUser = Auth::requireAuth('viewer');
$repo = Cat::instance();
$puedeEditar = Auth::hasRole('admin');

$flash = $_GET['msg'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditar) {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            switch ($_POST['action'] ?? '') {
                case 'delete':
                    $repo->eliminarProducto($id);
                    $flash = 'Producto eliminado.';
                    break;
                case 'duplicate':
                    $newId = $repo->duplicarProducto($id);
                    header('Location: ' . $pluginUrl('producto', ['id' => $newId, 'msg' => 'Copia creada como borrador.']));
                    exit;
                case 'toggle':
                    $p = $repo->productoPorId($id);
                    if ($p) {
                        $p['estado'] = $p['estado'] === 'published' ? 'draft' : 'published';
                        $repo->guardarProducto($p, $id);
                        $flash = $p['estado'] === 'published' ? 'Producto publicado.' : 'Producto pasado a borrador.';
                    }
                    break;
            }
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$q        = trim((string) ($_GET['q'] ?? ''));
$catId    = (string) ($_GET['categoria'] ?? '');
$estado   = (string) ($_GET['estado'] ?? '');
$page     = max(1, (int) ($_GET['pg'] ?? 1));
$perPage  = 25;

$filtros = ['q' => $q, 'estado' => in_array($estado, ['published', 'draft'], true) ? $estado : null, 'orden' => 'admin'];
if ($catId === 'none') {
    $filtros['sin_categoria'] = true;
} elseif ($catId !== '') {
    $filtros['categoria_id'] = (int) $catId;
}

$total      = $repo->contarProductos($filtros);
$productos  = $repo->buscarProductos($filtros, $perPage, ($page - 1) * $perPage);
$totalPages = (int) max(1, ceil($total / $perPage));
$arbol      = $repo->categoriasArbol(false);
$csrfToken  = Security::generateCSRF();

$pageTitle = 'Catálogo — Productos';
require ADMIN_PARTIALS . '/header.php';
include __DIR__ . '/_tabs.php';
?>

<?php if ($flash): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <form method="get" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="p" value="catalogo">
        <input type="hidden" name="page" value="productos">
        <label class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400"><?= ajm_icon('search', 'w-4 h-4') ?></span>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nombre o código…"
                   class="w-64 rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
        </label>
        <select name="categoria" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Todas las categorías</option>
            <option value="none" <?= $catId === 'none' ? 'selected' : '' ?>>— Sin categoría —</option>
            <?php foreach ($arbol as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $catId === (string) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            <option value="published" <?= $estado === 'published' ? 'selected' : '' ?>>Publicados</option>
            <option value="draft" <?= $estado === 'draft' ? 'selected' : '' ?>>Borradores</option>
        </select>
        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:border-aj-teal">Filtrar</button>
    </form>
    <?php if ($puedeEditar): ?>
        <a href="<?= e($pluginUrl('producto')) ?>" class="rounded-lg bg-aj-olive text-white text-sm font-semibold px-4 py-2 hover:opacity-90">+ Nuevo producto</a>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
            <tr>
                <th class="px-4 py-3 font-medium">Producto</th>
                <th class="px-4 py-3 font-medium">Categoría</th>
                <th class="px-4 py-3 font-medium">Precio</th>
                <th class="px-4 py-3 font-medium">Disponibilidad</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($productos as $p): $disp = Cat::DISPONIBILIDAD[$p['disponibilidad']]; ?>
                <tr class="align-middle">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                                <?php if ($p['imagen_path']): ?>
                                    <img src="/uploads/<?= e($p['imagen_path']) ?>" alt="" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <?= ajm_icon('box', 'w-5 h-5 text-gray-300') ?>
                                <?php endif; ?>
                            </span>
                            <div class="min-w-0">
                                <a href="<?= e($pluginUrl('producto', ['id' => $p['id']])) ?>" class="font-medium text-gray-800 hover:text-aj-teal">
                                    <?= e($p['nombre']) ?>
                                </a>
                                <?php if ($p['destacado']): ?><span class="ml-1 text-aj-olive" title="Destacado">★</span><?php endif; ?>
                                <p class="text-xs text-gray-400"><?= $p['sku'] ? 'Cód. ' . e($p['sku']) : 'Sin código' ?><?= $p['marca_nombre'] ? ' · ' . e($p['marca_nombre']) : '' ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?= e($p['categoria_nombre'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        <?= $p['precio'] !== null ? e(Cat::formatoPrecio($p['precio'])) : '<span class="text-gray-300">—</span>' ?>
                        <?php if ($p['precio'] !== null && !$p['mostrar_precio']): ?><span class="text-[11px] text-gray-400" title="Oculto en el sitio">(oculto)</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $disp['class'] ?>"><?= e($disp['label']) ?></span></td>
                    <td class="px-4 py-3">
                        <?php if ($p['estado'] === 'published'): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Publicado</span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Borrador</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <?php if ($p['estado'] === 'published'): ?>
                            <a href="/catalogo/producto/<?= e($p['slug']) ?>" target="_blank" class="text-gray-500 hover:text-aj-teal font-medium">Ver</a>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <a href="<?= e($pluginUrl('producto', ['id' => $p['id']])) ?>" class="text-aj-teal font-medium hover:underline">Editar</a>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button name="action" value="toggle" class="text-gray-500 font-medium hover:underline"><?= $p['estado'] === 'published' ? 'Despublicar' : 'Publicar' ?></button>
                                <button name="action" value="duplicate" class="text-gray-500 font-medium hover:underline">Duplicar</button>
                                <button name="action" value="delete" onclick="return confirm('¿Eliminar «<?= e(addslashes($p['nombre'])) ?>»? Esta acción no se puede deshacer.')" class="text-red-600 font-medium hover:underline">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productos): ?>
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">
                    No hay productos<?= $q !== '' || $catId !== '' || $estado !== '' ? ' con esos filtros' : ' todavía' ?>.
                    <?php if ($puedeEditar && $q === ''): ?><a href="<?= e($pluginUrl('producto')) ?>" class="text-aj-teal hover:underline">Crear el primero</a> o <a href="<?= e($pluginUrl('importar')) ?>" class="text-aj-teal hover:underline">importar desde Excel/CSV</a>.<?php endif; ?>
                </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mt-5 text-sm text-gray-500">
    <span><?= (int) $total ?> producto<?= $total === 1 ? '' : 's' ?></span>
    <?php if ($totalPages > 1): ?>
        <div class="flex flex-wrap gap-1">
            <?php for ($n = 1; $n <= $totalPages; $n++): ?>
                <a href="<?= e($pluginUrl('productos', array_filter(['q' => $q, 'categoria' => $catId, 'estado' => $estado, 'pg' => $n]))) ?>"
                   class="px-3 py-1.5 rounded-lg border <?= $n === $page ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200' ?>"><?= $n ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
