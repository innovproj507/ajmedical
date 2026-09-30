<?php
/**
 * @var array  $producto
 * @var array  $imagenes      Filas de media (principal primero)
 * @var array  $ruta          Ancestros de la categoría
 * @var array  $relacionados
 * @var int    $enCotizacion  Cantidad ya agregada a la cotización
 * @var string $csrfToken
 */
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$p    = $producto;
$disp = Cat::DISPONIBILIDAD[$p['disponibilidad']] ?? Cat::DISPONIBILIDAD['disponible'];
$whatsapp = preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP'));
$shareUrl = rtrim(SITE_URL, '/') . '/catalogo/producto/' . $p['slug'];

$breadcrumb = [['label' => 'Inicio', 'href' => '/'], ['label' => 'Catálogo', 'href' => '/catalogo']];
foreach ($ruta as $r) {
    $breadcrumb[] = ['label' => $r['nombre'], 'href' => '/catalogo/categoria/' . $r['slug']];
}
$breadcrumb[] = ['label' => $p['nombre']];

// La descripción admite HTML; si el admin escribió texto plano, se respetan los saltos de línea.
$descripcion = (string) ($p['descripcion'] ?? '');
if ($descripcion !== '' && $descripcion === strip_tags($descripcion)) {
    $descripcion = '<p>' . nl2br(e($descripcion)) . '</p>';
}
?>
<section class="bg-aj-teal-light/60 border-b border-gray-100">
    <nav class="max-w-7xl mx-auto px-5 md:px-10 py-4 flex flex-wrap items-center gap-2 text-sm text-gray-500" aria-label="Ruta">
        <?php foreach ($breadcrumb as $i => $crumb): ?>
            <?php if ($i > 0): ?><?= ajm_icon('chevron-right', 'w-3.5 h-3.5 text-gray-400') ?><?php endif; ?>
            <?php if (!empty($crumb['href'])): ?>
                <a href="<?= e($crumb['href']) ?>" class="text-aj-teal font-medium hover:underline"><?= e($crumb['label']) ?></a>
            <?php else: ?>
                <span class="text-gray-700 font-medium truncate max-w-[260px]"><?= e($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</section>

<section class="max-w-7xl mx-auto px-5 md:px-10 py-10 lg:py-14">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14">

        <!-- Galería -->
        <div>
            <div class="relative aspect-square rounded-3xl border border-gray-200 bg-white overflow-hidden">
                <?php if ($imagenes): ?>
                    <?php foreach ($imagenes as $i => $img): ?>
                        <a href="/uploads/<?= e($img['path']) ?>" data-lightbox="producto" data-gallery-slide="<?= $i ?>"
                           class="<?= $i === 0 ? '' : 'hidden' ?> absolute inset-0 cursor-zoom-in">
                            <img src="/uploads/<?= e($img['path']) ?>" alt="<?= e($img['alt_text'] ?: $p['nombre']) ?>" class="w-full h-full object-contain p-6">
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="absolute inset-0 flex items-center justify-center bg-aj-teal-light text-aj-teal/25"><?= ajm_icon('box', 'w-28 h-28') ?></span>
                <?php endif; ?>
                <?php if (!empty($p['destacado'])): ?>
                    <span class="absolute top-4 left-4 bg-aj-olive text-white text-xs font-bold uppercase tracking-wide px-3 py-1.5 rounded-full">Destacado</span>
                <?php endif; ?>
            </div>
            <?php if (count($imagenes) > 1): ?>
                <div class="grid grid-cols-5 gap-3 mt-4">
                    <?php foreach ($imagenes as $i => $img): ?>
                        <button type="button" data-gallery-thumb="<?= $i ?>"
                                class="aspect-square rounded-xl border-2 bg-white overflow-hidden transition <?= $i === 0 ? 'border-aj-teal' : 'border-gray-200 hover:border-aj-teal/50' ?>">
                            <img src="/uploads/<?= e($img['path']) ?>" alt="" class="w-full h-full object-contain p-1.5">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Información -->
        <div>
            <?php if (!empty($p['categoria_nombre']) || !empty($p['marca_nombre'])): ?>
                <p class="text-xs font-semibold uppercase tracking-wider text-aj-olive-dark mb-2">
                    <?php if (!empty($p['categoria_nombre'])): ?>
                        <a href="/catalogo/categoria/<?= e($p['categoria_slug']) ?>" class="hover:underline"><?= e($p['categoria_nombre']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($p['marca_nombre'])): ?>
                        <?= !empty($p['categoria_nombre']) ? ' · ' : '' ?><a href="/catalogo?marca=<?= e($p['marca_slug']) ?>" class="hover:underline"><?= e($p['marca_nombre']) ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 leading-tight"><?= e($p['nombre']) ?></h1>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full <?= $disp['class'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span><?= e($disp['label']) ?>
                </span>
                <?php if (!empty($p['sku'])): ?>
                    <span class="text-sm text-gray-500">Código: <span class="font-semibold text-gray-700"><?= e($p['sku']) ?></span></span>
                <?php endif; ?>
            </div>

            <?php if (Cat::precioVisible($p)): ?>
                <div class="mt-6">
                    <?php if ($p['precio_oferta'] !== null && (float) $p['precio_oferta'] < (float) $p['precio']): ?>
                        <span class="text-lg text-gray-400 line-through mr-2"><?= e(Cat::formatoPrecio($p['precio'])) ?></span>
                        <span class="text-3xl font-extrabold text-aj-teal"><?= e(Cat::formatoPrecio($p['precio_oferta'])) ?></span>
                    <?php else: ?>
                        <span class="text-3xl font-extrabold text-aj-teal"><?= e(Cat::formatoPrecio($p['precio'])) ?></span>
                    <?php endif; ?>
                    <?php if (setting('CATALOGO_NOTA_PRECIOS')): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= e((string) setting('CATALOGO_NOTA_PRECIOS')) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($p['resumen'])): ?>
                <p class="text-gray-600 leading-relaxed mt-6"><?= e($p['resumen']) ?></p>
            <?php endif; ?>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                <?php if (!empty($p['presentacion'])): ?>
                    <div class="rounded-xl bg-gray-50 px-4 py-3">
                        <dt class="text-xs text-gray-400">Presentación</dt>
                        <dd class="text-sm font-semibold text-gray-800"><?= e($p['presentacion']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($p['registro_sanitario'])): ?>
                    <div class="rounded-xl bg-gray-50 px-4 py-3">
                        <dt class="text-xs text-gray-400">Registro sanitario</dt>
                        <dd class="text-sm font-semibold text-gray-800"><?= e($p['registro_sanitario']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if (!empty($p['marca_nombre'])): ?>
                <a href="/catalogo?marca=<?= e($p['marca_slug']) ?>" class="mt-3 flex items-center gap-4 rounded-xl border border-gray-100 px-4 py-3 hover:border-aj-teal/30 transition group">
                    <?php if (!empty($p['marca_logo_path'])): ?>
                        <img src="/uploads/<?= e($p['marca_logo_path']) ?>" alt="<?= e($p['marca_nombre']) ?>" class="h-10 w-20 object-contain shrink-0">
                    <?php else: ?>
                        <span class="h-10 w-10 rounded-lg bg-aj-teal-light text-aj-teal flex items-center justify-center shrink-0"><?= ajm_icon('tag', 'w-5 h-5') ?></span>
                    <?php endif; ?>
                    <span class="min-w-0">
                        <span class="block text-xs text-gray-400">Fabricante</span>
                        <span class="block text-sm font-semibold text-gray-800 group-hover:text-aj-teal"><?= e($p['marca_razon_social'] ?: $p['marca_nombre']) ?></span>
                    </span>
                </a>
            <?php endif; ?>

            <!-- Acciones -->
            <div class="mt-8 p-5 rounded-2xl border border-gray-200">
                <?php if ($p['disponibilidad'] !== 'agotado'): ?>
                    <form method="post" action="/catalogo/cotizacion/agregar" class="js-add-quote flex flex-wrap items-center gap-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="producto_id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="back" value="/catalogo/cotizacion">
                        <label class="flex items-center rounded-full border border-gray-300 overflow-hidden">
                            <span class="sr-only">Cantidad</span>
                            <button type="button" data-qty="-1" class="w-10 h-11 text-gray-500 hover:text-aj-teal flex items-center justify-center"><?= ajm_icon('minus', 'w-4 h-4') ?></button>
                            <input type="number" name="cantidad" value="1" min="1" max="99999" class="w-16 h-11 text-center border-0 focus:outline-none text-sm font-semibold [appearance:textfield]">
                            <button type="button" data-qty="1" class="w-10 h-11 text-gray-500 hover:text-aj-teal flex items-center justify-center"><?= ajm_icon('plus', 'w-4 h-4') ?></button>
                        </label>
                        <button type="submit" class="flex-1 min-w-[200px] inline-flex items-center justify-center gap-2 bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold px-6 h-11 rounded-full">
                            <?= ajm_icon('clipboard', 'w-5 h-5') ?> Agregar a cotización
                        </button>
                    </form>
                    <?php if ($enCotizacion > 0): ?>
                        <p class="text-xs text-gray-500 mt-3">Ya tienes <?= (int) $enCotizacion ?> en tu <a href="/catalogo/cotizacion" class="text-aj-teal font-semibold hover:underline">cotización</a>.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-600">Este producto está agotado por el momento. Escríbenos para conocer la fecha de reposición o alternativas.</p>
                <?php endif; ?>

                <div class="flex flex-wrap gap-3 mt-4">
                    <?php if ($whatsapp !== ''): ?>
                        <a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Hola, quisiera información sobre: ' . $p['nombre'] . ($p['sku'] ? " (Cód. {$p['sku']})" : '') . "\n" . $shareUrl) ?>"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 bg-[#25D366] hover:opacity-90 transition text-white text-sm font-semibold px-5 py-2.5 rounded-full">
                            <?= ajm_icon('whatsapp', 'w-5 h-5') ?> Consultar por WhatsApp
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($p['ficha_path'])): ?>
                        <a href="/uploads/<?= e($p['ficha_path']) ?>" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 border border-aj-teal text-aj-teal hover:bg-aj-teal hover:text-white transition text-sm font-semibold px-5 py-2.5 rounded-full">
                            <?= ajm_icon('download', 'w-4 h-4') ?> Ficha técnica (PDF)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Descripción y especificaciones -->
    <?php if ($descripcion !== '' || $p['especificaciones']): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 mt-14">
            <?php if ($descripcion !== ''): ?>
                <div class="<?= $p['especificaciones'] ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
                    <h2 class="text-xl font-bold text-aj-teal mb-4">Descripción</h2>
                    <div class="prose max-w-none prose-headings:text-aj-teal prose-a:text-aj-teal">
                        <?= $descripcion ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($p['especificaciones']): ?>
                <div class="<?= $descripcion === '' ? 'lg:col-span-2' : '' ?>">
                    <h2 class="text-xl font-bold text-aj-teal mb-4">Especificaciones</h2>
                    <table class="w-full text-sm rounded-2xl overflow-hidden border border-gray-200">
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($p['especificaciones'] as $i => $spec): ?>
                                <tr class="<?= $i % 2 ? 'bg-white' : 'bg-gray-50' ?>">
                                    <th class="text-left font-medium text-gray-500 px-4 py-2.5 w-2/5 align-top"><?= e($spec[0] ?? '') ?></th>
                                    <td class="px-4 py-2.5 text-gray-800"><?= e($spec[1] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Relacionados -->
    <?php if ($relacionados): ?>
        <div class="mt-16">
            <div class="flex items-end justify-between gap-4 mb-6">
                <h2 class="text-2xl font-extrabold"><span class="text-gray-900">Productos</span> <span class="text-aj-teal">relacionados</span></h2>
                <?php if (!empty($p['categoria_slug'])): ?>
                    <a href="/catalogo/categoria/<?= e($p['categoria_slug']) ?>" class="text-sm font-semibold text-aj-teal hover:underline whitespace-nowrap">Ver todos</a>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
                <?php foreach ($relacionados as $rel): $p = $rel; ?>
                    <?php include __DIR__ . '/partials/producto-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
(function () {
    // Miniaturas de la galería
    var slides = document.querySelectorAll('[data-gallery-slide]');
    var thumbs = document.querySelectorAll('[data-gallery-thumb]');
    thumbs.forEach(function (t) {
        t.addEventListener('click', function () {
            var i = t.getAttribute('data-gallery-thumb');
            slides.forEach(function (s) { s.classList.toggle('hidden', s.getAttribute('data-gallery-slide') !== i); });
            thumbs.forEach(function (o) {
                var on = o === t;
                o.classList.toggle('border-aj-teal', on);
                o.classList.toggle('border-gray-200', !on);
            });
        });
    });
    // Botones +/- de cantidad
    document.querySelectorAll('[data-qty]').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = b.parentNode.querySelector('input[name=cantidad]');
            input.value = Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(b.getAttribute('data-qty'), 10));
        });
    });
})();
</script>

<?php include __DIR__ . '/partials/quote-js.php'; ?>
