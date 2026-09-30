<?php
/**
 * @var array      $productos
 * @var int        $total
 * @var int        $page
 * @var int        $totalPages
 * @var array      $filtros
 * @var array      $arbol       Categorías (orden jerárquico, con depth y total_arbol)
 * @var array      $marcas
 * @var array|null $categoria   Categoría actual (null = todo el catálogo)
 * @var array|null $marca
 * @var array      $ruta        Ancestros de la categoría actual
 * @var string     $pageTitle
 * @var string     $csrfToken
 */
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$ruta ??= [];
$marca ??= null;
$baseUrl = $categoria ? '/catalogo/categoria/' . $categoria['slug'] : '/catalogo';

// Construye la URL del listado conservando los filtros actuales y cambiando solo $cambios.
$urlCon = function (array $cambios = []) use ($baseUrl, $filtros, $marca): string {
    $q = array_filter([
        'q'              => $filtros['q'] ?? '',
        'marca'          => $marca['slug'] ?? '',
        'disponibilidad' => $filtros['disponibilidad'] ?? '',
        'orden'          => ($filtros['orden'] ?? 'relevancia') !== 'relevancia' ? $filtros['orden'] : '',
    ]);
    $q = array_filter(array_merge($q, $cambios), fn($v) => $v !== '' && $v !== null);
    return $baseUrl . ($q ? '?' . http_build_query($q) : '');
};

$bannerImage = !empty($categoria['imagen_id']) ? (($img = (new \AJM\Core\Media())->find((int) $categoria['imagen_id'])) ? \AJM\Core\Media::url($img) : '') : '';
$bannerTitle = $pageTitle;
$breadcrumb  = [['label' => 'Inicio', 'href' => '/'], ['label' => 'Catálogo', 'href' => $categoria ? '/catalogo' : null]];
foreach ($ruta as $i => $r) {
    $breadcrumb[] = ['label' => $r['nombre'], 'href' => $i < count($ruta) - 1 ? '/catalogo/categoria/' . $r['slug'] : null];
}
include ROOT_PATH . '/templates/public/partials/page-banner.php';

$hayFiltros = ($filtros['q'] ?? '') !== '' || $marca || ($filtros['disponibilidad'] ?? '') !== '';
?>

<section class="max-w-7xl mx-auto px-5 md:px-10 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- Filtros -->
        <aside class="lg:col-span-1">
            <details class="lg:hidden mb-4 rounded-2xl border border-gray-200" <?= $hayFiltros ? 'open' : '' ?>>
                <summary class="flex items-center gap-2 px-5 py-3 font-semibold text-gray-700 cursor-pointer"><?= ajm_icon('filter', 'w-4 h-4') ?> Filtrar productos</summary>
                <div class="px-5 pb-5" data-filtros-movil></div>
            </details>

            <div class="hidden lg:block space-y-6 lg:sticky lg:top-28" data-filtros>
                <div class="rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide mb-3">Buscar</h3>
                    <span class="block w-10 h-1 rounded-full bg-aj-olive mb-4"></span>
                    <form method="get" action="<?= e($baseUrl) ?>" class="flex rounded-full border border-gray-300 focus-within:ring-2 focus-within:ring-aj-teal overflow-hidden">
                        <?php if ($marca): ?><input type="hidden" name="marca" value="<?= e($marca['slug']) ?>"><?php endif; ?>
                        <input type="text" name="q" value="<?= e($filtros['q'] ?? '') ?>" placeholder="Producto o código…"
                               class="flex-1 min-w-0 border-0 px-4 py-2.5 text-sm focus:outline-none">
                        <button type="submit" aria-label="Buscar" class="shrink-0 rounded-full m-1 w-9 h-9 bg-aj-teal hover:bg-aj-teal-dark transition text-white flex items-center justify-center">
                            <?= ajm_icon('search', 'w-4 h-4') ?>
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide mb-3">Categorías</h3>
                    <span class="block w-10 h-1 rounded-full bg-aj-olive mb-4"></span>
                    <ul class="space-y-0.5 text-sm">
                        <li>
                            <a href="/catalogo" class="flex items-center justify-between rounded-lg px-3 py-2 <?= !$categoria ? 'bg-aj-teal-light text-aj-teal font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>">
                                Todos los productos
                            </a>
                        </li>
                        <?php foreach ($arbol as $c): if ((int) $c['total_arbol'] === 0 && !($categoria && (int) $categoria['id'] === (int) $c['id'])) continue; $activa = $categoria && (int) $categoria['id'] === (int) $c['id']; ?>
                            <li>
                                <a href="/catalogo/categoria/<?= e($c['slug']) ?>"
                                   class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 <?= $activa ? 'bg-aj-teal-light text-aj-teal font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>"
                                   style="padding-left: <?= 0.75 + $c['depth'] * 1 ?>rem">
                                    <span class="truncate"><?= $c['depth'] > 0 ? '<span class="text-gray-300">└</span> ' : '' ?><?= e($c['nombre']) ?></span>
                                    <span class="text-xs text-gray-400 shrink-0"><?= (int) $c['total_arbol'] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (array_filter($marcas, fn($m) => (int) $m['total'] > 0)): ?>
                    <div class="rounded-2xl border border-gray-200 p-5">
                        <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide mb-3">Fabricantes</h3>
                        <span class="block w-10 h-1 rounded-full bg-aj-olive mb-4"></span>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($marcas as $m): if ((int) $m['total'] === 0) continue; $activa = $marca && $marca['id'] === $m['id']; ?>
                                <a href="<?= e($urlCon(['marca' => $activa ? '' : $m['slug'], 'page' => ''])) ?>"
                                   class="px-3 py-1.5 rounded-full text-xs font-medium border transition <?= $activa ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200 hover:border-aj-teal' ?>">
                                    <?= e($m['nombre']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="rounded-2xl bg-aj-teal text-white p-5 relative overflow-hidden">
                    <span class="absolute -top-6 -right-6 w-24 h-16 rounded-tl-2xl rounded-br-2xl bg-aj-olive/40"></span>
                    <p class="relative font-bold text-lg leading-snug">¿Necesitas un producto que no ves aquí?</p>
                    <p class="relative text-white/75 text-sm mt-2 mb-4">Escríbenos: conseguimos insumos bajo pedido.</p>
                    <a href="/contacto" class="relative inline-flex items-center gap-2 bg-white text-aj-teal text-sm font-semibold px-4 py-2 rounded-full hover:bg-white/90">
                        Contáctanos <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Resultados -->
        <div class="lg:col-span-3">
            <?php if ($categoria && !empty($categoria['descripcion'])): ?>
                <p class="text-gray-600 mb-6"><?= e($categoria['descripcion']) ?></p>
            <?php elseif (!$categoria && setting('CATALOGO_INTRO')): ?>
                <p class="text-gray-600 mb-6"><?= e((string) setting('CATALOGO_INTRO')) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-gray-100">
                <p class="text-sm text-gray-500">
                    <span class="font-semibold text-gray-800"><?= (int) $total ?></span> producto<?= $total === 1 ? '' : 's' ?>
                    <?php if (($filtros['q'] ?? '') !== ''): ?> para "<span class="font-semibold text-gray-700"><?= e($filtros['q']) ?></span>"<?php endif; ?>
                    <?php if ($marca): ?> de <span class="font-semibold text-gray-700"><?= e($marca['nombre']) ?></span><?php endif; ?>
                    <?php if ($hayFiltros): ?> · <a href="<?= e($baseUrl) ?>" class="text-aj-teal hover:underline">Quitar filtros</a><?php endif; ?>
                </p>
                <form method="get" action="<?= e($baseUrl) ?>" class="flex flex-wrap items-center gap-2">
                    <?php foreach (['q' => $filtros['q'] ?? '', 'marca' => $marca['slug'] ?? ''] as $k => $v): if ($v === '') continue; ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                    <?php endforeach; ?>
                    <select name="disponibilidad" onchange="this.form.submit()" class="rounded-full border border-gray-300 px-4 py-2 text-sm bg-white">
                        <option value="">Toda disponibilidad</option>
                        <?php foreach (Cat::DISPONIBILIDAD as $k => $d): ?>
                            <option value="<?= e($k) ?>" <?= ($filtros['disponibilidad'] ?? '') === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="orden" onchange="this.form.submit()" class="rounded-full border border-gray-300 px-4 py-2 text-sm bg-white">
                        <?php foreach (Cat::ORDENES as $k => $label): if (str_starts_with($k, 'precio') && !Cat::preciosActivos()) continue; ?>
                            <option value="<?= e($k) ?>" <?= ($filtros['orden'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="rounded-full bg-aj-teal text-white text-sm px-4 py-2">Aplicar</button></noscript>
                </form>
            </div>

            <?php if ($productos): ?>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
                    <?php foreach ($productos as $p): ?>
                        <?php include __DIR__ . '/partials/producto-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16 rounded-2xl border border-dashed border-gray-300">
                    <span class="inline-flex w-16 h-16 rounded-full bg-aj-teal-light text-aj-teal items-center justify-center mb-4"><?= ajm_icon('search', 'w-7 h-7') ?></span>
                    <p class="font-semibold text-gray-800">No encontramos productos con esos filtros.</p>
                    <p class="text-sm text-gray-500 mt-1">Prueba con otra búsqueda o <a href="/contacto" class="text-aj-teal hover:underline">consúltanos directamente</a>.</p>
                </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <nav class="flex flex-wrap justify-center gap-1 mt-10" aria-label="Paginación">
                    <?php for ($n = 1; $n <= $totalPages; $n++): ?>
                        <a href="<?= e($urlCon(['page' => $n > 1 ? $n : ''])) ?>"
                           class="min-w-[40px] text-center px-3 py-2 rounded-full text-sm border <?= $n === $page ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200 hover:border-aj-teal' ?>"><?= $n ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>

            <p class="mt-10 text-sm text-gray-500 flex flex-wrap items-center gap-2">
                <?= ajm_icon('download', 'w-4 h-4 text-aj-teal') ?>
                <a href="/catalogo/imprimir<?= $categoria ? '?categoria=' . e($categoria['slug']) : '' ?>" target="_blank" class="text-aj-teal font-medium hover:underline">
                    Descargar <?= $categoria ? 'esta categoría' : 'el catálogo completo' ?> en PDF
                </a>
                <?php if (Cat::preciosActivos() && setting('CATALOGO_NOTA_PRECIOS')): ?>
                    <span class="text-gray-400">· <?= e((string) setting('CATALOGO_NOTA_PRECIOS')) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<script>
// En móvil, los filtros se muestran dentro de un <details> plegable (mismo HTML, movido).
(function () {
    var mq = window.matchMedia('(max-width: 1023px)');
    var src = document.querySelector('[data-filtros]');
    var dst = document.querySelector('[data-filtros-movil]');
    if (!src || !dst) return;
    var home = src.parentNode;
    function place() {
        if (mq.matches) { dst.appendChild(src); src.classList.remove('hidden'); }
        else { home.appendChild(src); src.classList.add('hidden'); }
        src.classList.toggle('lg:block', true);
    }
    mq.addEventListener ? mq.addEventListener('change', place) : mq.addListener(place);
    place();
})();
</script>

<?php include __DIR__ . '/partials/quote-js.php'; ?>
