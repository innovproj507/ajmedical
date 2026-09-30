<?php
/**
 * Admin — crear / editar producto (datos, galería con subida directa, ficha técnica PDF, precio, SEO).
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

$id       = isset($_GET['id']) ? (int) $_GET['id'] : null;
$producto = $id ? $repo->productoPorId($id) : null;
if ($id && !$producto) {
    header('Location: ' . $pluginUrl('productos', ['msg' => 'El producto ya no existe.']));
    exit;
}

$error = '';
$form  = $producto ?? ['estado' => 'published', 'disponibilidad' => 'disponible', 'mostrar_precio' => 1, 'especificaciones' => []];
$galeria = $id ? $repo->galeriaIds($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        try {
            // Galería: las existentes que no se marcaron para quitar + las recién subidas.
            $quitar  = array_map('intval', (array) ($_POST['quitar_imagen'] ?? []));
            $galeria = array_values(array_diff(array_map('intval', (array) ($_POST['galeria'] ?? [])), $quitar));
            $galeria = array_merge($galeria, Uploads::multiple($_FILES['imagenes'] ?? null, 'catalogo', 'image/'));

            $principal = (int) ($_POST['imagen_id'] ?? 0);
            if (!in_array($principal, $galeria, true)) {
                $principal = $galeria[0] ?? 0;
            }
            $form['imagen_id'] = $principal ?: null;

            // Ficha técnica: nueva subida > mantener la actual > quitar.
            $ficha = Uploads::single($_FILES['ficha_tecnica'] ?? null, 'catalogo/fichas', 'application/pdf');
            if ($ficha) {
                $form['ficha_tecnica_id'] = $ficha;
            } elseif (!empty($_POST['quitar_ficha'])) {
                $form['ficha_tecnica_id'] = null;
            } else {
                $form['ficha_tecnica_id'] = $producto['ficha_tecnica_id'] ?? null;
            }

            $form['mostrar_precio'] = !empty($_POST['mostrar_precio']);
            $form['destacado']      = !empty($_POST['destacado']);

            Database::getInstance()->beginTransaction();
            $savedId = $repo->guardarProducto($form, $id);
            $repo->setImagenes($savedId, $galeria);
            Database::getInstance()->commit();

            $next = ($_POST['after'] ?? '') === 'new' ? $pluginUrl('producto', ['msg' => 'Producto guardado. Puedes cargar el siguiente.'])
                : $pluginUrl('producto', ['id' => $savedId, 'msg' => 'Producto guardado.']);
            header('Location: ' . $next);
            exit;
        } catch (\InvalidArgumentException $e) {
            if (Database::getInstance()->getPdo()->inTransaction()) {
                Database::getInstance()->rollback();
            }
            $error = $e->getMessage();
        }
    }
}

$especificacionesTexto = is_array($form['especificaciones'] ?? null)
    ? Cat::especificacionesATexto($form['especificaciones'])
    : (string) ($form['especificaciones'] ?? '');

$mediaGaleria = [];
if ($galeria) {
    $rows = Database::getInstance()->fetchAll('SELECT * FROM media WHERE id IN (' . implode(',', array_map('intval', $galeria)) . ')');
    $byId = array_column($rows, null, 'id');
    foreach ($galeria as $mid) {
        if (isset($byId[$mid])) {
            $mediaGaleria[] = $byId[$mid];
        }
    }
}

$arbol     = $repo->categoriasArbol(false);
$marcas    = $repo->marcas(false);
$csrfToken = Security::generateCSRF();

$pageTitle = $producto ? 'Editar producto' : 'Nuevo producto';
require ADMIN_PARTIALS . '/header.php';
include __DIR__ . '/_tabs.php';

$input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal';
$label = 'block text-sm font-medium text-gray-700 mb-1';
$h2    = 'text-sm font-semibold text-aj-teal uppercase tracking-wide mb-4';
?>

<?php if (!empty($_GET['msg'])): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 flex flex-wrap items-center justify-between gap-2">
        <span><?= e($_GET['msg']) ?></span>
        <?php if ($producto && $producto['estado'] === 'published'): ?>
            <a href="/catalogo/producto/<?= e($producto['slug']) ?>" target="_blank" class="font-semibold underline">Ver en el sitio</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="xl:col-span-2 space-y-5">
        <!-- Datos básicos -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="<?= $h2 ?>">Datos del producto</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="<?= $label ?>">Nombre *</label>
                    <input type="text" name="nombre" required value="<?= e($form['nombre'] ?? '') ?>" class="<?= $input ?>" placeholder="Ej. Guantes de nitrilo sin polvo">
                </div>
                <div>
                    <label class="<?= $label ?>">Código / SKU</label>
                    <input type="text" name="sku" value="<?= e($form['sku'] ?? '') ?>" class="<?= $input ?>" placeholder="Ej. GN-100-M">
                </div>
                <div>
                    <label class="<?= $label ?>">Categoría</label>
                    <select name="categoria_id" class="<?= $input ?>">
                        <option value="">— Sin categoría —</option>
                        <?php foreach ($arbol as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) ($form['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= e($pluginUrl('categorias')) ?>" class="text-xs text-aj-teal hover:underline">Administrar categorías</a>
                </div>
                <div>
                    <label class="<?= $label ?>">Marca</label>
                    <select name="marca_id" class="<?= $input ?>">
                        <option value="">— Sin marca —</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (string) ($form['marca_id'] ?? '') === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= e($pluginUrl('marcas')) ?>" class="text-xs text-aj-teal hover:underline">Administrar marcas</a>
                </div>
                <div>
                    <label class="<?= $label ?>">Presentación</label>
                    <input type="text" name="presentacion" value="<?= e($form['presentacion'] ?? '') ?>" class="<?= $input ?>" placeholder="Ej. Caja x 100 unidades">
                </div>
                <div class="md:col-span-3">
                    <label class="<?= $label ?>">Resumen <span class="text-gray-400 font-normal">(1-2 líneas, se ve en el listado)</span></label>
                    <textarea name="resumen" rows="2" maxlength="500" class="<?= $input ?>"><?= e($form['resumen'] ?? '') ?></textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="<?= $label ?>">Descripción <span class="text-gray-400 font-normal">(texto libre; también admite HTML)</span></label>
                    <textarea name="descripcion" rows="7" class="<?= $input ?>"><?= e($form['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="<?= $label ?>">Especificaciones <span class="text-gray-400 font-normal">(una por línea: <code>Etiqueta: valor</code>)</span></label>
                    <textarea name="especificaciones" rows="6" class="<?= $input ?> font-mono" placeholder="Material: Nitrilo&#10;Tallas: S, M, L, XL&#10;Color: Azul&#10;Estéril: No"><?= e($especificacionesTexto) ?></textarea>
                </div>
                <div>
                    <label class="<?= $label ?>">Registro sanitario</label>
                    <input type="text" name="registro_sanitario" value="<?= e($form['registro_sanitario'] ?? '') ?>" class="<?= $input ?>">
                </div>
            </div>
        </div>

        <!-- Imágenes -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="<?= $h2 ?>">Imágenes</h2>
            <?php if ($mediaGaleria): ?>
                <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-4">
                    <?php foreach ($mediaGaleria as $img): $esPrincipal = (int) ($form['imagen_id'] ?? 0) === (int) $img['id']; ?>
                        <div class="rounded-lg border <?= $esPrincipal ? 'border-aj-teal ring-2 ring-aj-teal/30' : 'border-gray-200' ?> p-1.5">
                            <input type="hidden" name="galeria[]" value="<?= (int) $img['id'] ?>">
                            <img src="/uploads/<?= e($img['path']) ?>" alt="" class="w-full aspect-square object-contain rounded bg-gray-50">
                            <label class="flex items-center gap-1.5 text-[11px] text-gray-600 mt-1.5">
                                <input type="radio" name="imagen_id" value="<?= (int) $img['id'] ?>" <?= $esPrincipal ? 'checked' : '' ?>> Principal
                            </label>
                            <label class="flex items-center gap-1.5 text-[11px] text-red-600">
                                <input type="checkbox" name="quitar_imagen[]" value="<?= (int) $img['id'] ?>"> Quitar
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 hover:border-aj-teal px-4 py-6 text-center cursor-pointer transition">
                <?= ajm_icon('upload', 'w-6 h-6 text-aj-teal') ?>
                <span class="text-sm font-medium text-gray-700">Subir imágenes</span>
                <span class="text-xs text-gray-400">JPG, PNG o WEBP · puedes elegir varias · fondo blanco recomendado</span>
                <input type="file" name="imagenes[]" multiple accept=".jpg,.jpeg,.png,.webp" class="text-xs mt-1" data-preview>
            </label>
            <div class="grid grid-cols-4 sm:grid-cols-6 gap-2 mt-3" data-preview-list></div>
        </div>

        <!-- SEO -->
        <details class="bg-white rounded-xl border border-gray-200 p-5" <?= !empty($form['seo_title']) || !empty($form['seo_description']) ? 'open' : '' ?>>
            <summary class="text-sm font-semibold text-aj-teal uppercase tracking-wide cursor-pointer">SEO y URL</summary>
            <div class="grid grid-cols-1 gap-4 mt-4">
                <div>
                    <label class="<?= $label ?>">Slug (URL) — se genera del nombre si se deja vacío</label>
                    <input type="text" name="slug" value="<?= e($form['slug'] ?? '') ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label class="<?= $label ?>">Título SEO</label>
                    <input type="text" name="seo_title" value="<?= e($form['seo_title'] ?? '') ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label class="<?= $label ?>">Descripción SEO</label>
                    <textarea name="seo_description" rows="2" class="<?= $input ?>"><?= e($form['seo_description'] ?? '') ?></textarea>
                </div>
            </div>
        </details>
    </div>

    <!-- Columna lateral -->
    <div class="space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5 xl:sticky xl:top-6">
            <label class="<?= $label ?>">Estado</label>
            <select name="estado" class="<?= $input ?> mb-4">
                <option value="published" <?= ($form['estado'] ?? '') === 'published' ? 'selected' : '' ?>>Publicado (visible)</option>
                <option value="draft" <?= ($form['estado'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador (oculto)</option>
            </select>

            <label class="<?= $label ?>">Disponibilidad</label>
            <select name="disponibilidad" class="<?= $input ?> mb-4">
                <?php foreach (Cat::DISPONIBILIDAD as $k => $d): ?>
                    <option value="<?= e($k) ?>" <?= ($form['disponibilidad'] ?? '') === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="flex items-center gap-2 text-sm text-gray-700 mb-4">
                <input type="checkbox" name="destacado" value="1" <?= !empty($form['destacado']) ? 'checked' : '' ?> class="rounded border-gray-300">
                Producto destacado <span class="text-gray-400 text-xs">(aparece en el inicio)</span>
            </label>

            <label class="<?= $label ?>">Orden <span class="text-gray-400 font-normal">(menor = primero)</span></label>
            <input type="number" name="orden" value="<?= (int) ($form['orden'] ?? 0) ?>" class="<?= $input ?>">

            <div class="flex flex-col gap-2 mt-5">
                <button type="submit" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90">Guardar</button>
                <?php if (!$producto): ?>
                    <button type="submit" name="after" value="new" class="w-full rounded-lg border border-aj-teal text-aj-teal font-semibold py-2 text-sm hover:bg-aj-teal/5">Guardar y crear otro</button>
                <?php endif; ?>
                <a href="<?= e($pluginUrl('productos')) ?>" class="block text-center text-sm text-gray-500 hover:underline">Volver al listado</a>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="<?= $h2 ?>">Precio</h2>
            <?php if (!Cat::preciosActivos()): ?>
                <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-4">
                    Los precios están ocultos en todo el sitio. Se pueden cargar igual y activarlos luego en
                    <?= Auth::hasRole('super_admin') ? '<a href="/admin/settings/index.php#catalogo" class="underline">Configuración</a>' : 'Configuración' ?>.
                </p>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="<?= $label ?>">Precio</label>
                    <input type="text" inputmode="decimal" name="precio" value="<?= e($form['precio'] ?? '') ?>" class="<?= $input ?>" placeholder="0.00">
                </div>
                <div>
                    <label class="<?= $label ?>">Oferta</label>
                    <input type="text" inputmode="decimal" name="precio_oferta" value="<?= e($form['precio_oferta'] ?? '') ?>" class="<?= $input ?>" placeholder="opcional">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 mt-3">
                <input type="checkbox" name="mostrar_precio" value="1" <?= !empty($form['mostrar_precio']) ? 'checked' : '' ?> class="rounded border-gray-300">
                Mostrar el precio de este producto
            </label>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="<?= $h2 ?>">Ficha técnica (PDF)</h2>
            <?php if (!empty($producto['ficha_path'])): ?>
                <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 mb-3 text-sm">
                    <a href="/uploads/<?= e($producto['ficha_path']) ?>" target="_blank" class="text-aj-teal font-medium truncate hover:underline"><?= e($producto['ficha_nombre'] ?? 'Ver PDF') ?></a>
                    <label class="flex items-center gap-1 text-xs text-red-600 shrink-0"><input type="checkbox" name="quitar_ficha" value="1"> Quitar</label>
                </div>
            <?php endif; ?>
            <input type="file" name="ficha_tecnica" accept=".pdf" class="text-sm w-full">
            <p class="text-xs text-gray-400 mt-1"><?= !empty($producto['ficha_path']) ? 'Sube otro PDF para reemplazarla.' : 'Opcional.' ?></p>
        </div>
    </div>
</form>

<script>
// Vista previa de las imágenes elegidas antes de guardar.
(function () {
    var input = document.querySelector('[data-preview]');
    var list = document.querySelector('[data-preview-list]');
    if (!input || !list || !window.URL) return;
    input.addEventListener('change', function () {
        list.innerHTML = '';
        Array.prototype.forEach.call(input.files, function (f) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.className = 'w-full aspect-square object-contain rounded-lg border border-gray-200 bg-gray-50';
            list.appendChild(img);
        });
    });
})();
</script>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
