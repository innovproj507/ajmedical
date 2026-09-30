<?php
/**
 * Tarjeta de producto (listado, relacionados, destacados del inicio).
 * @var array  $p          Fila de cat_productos con imagen_path, categoria_nombre, marca_nombre
 * @var string $csrfToken  Para el botón "Agregar a cotización" (vacío = sin botón)
 */
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$disp = Cat::DISPONIBILIDAD[$p['disponibilidad']] ?? Cat::DISPONIBILIDAD['disponible'];
$url  = '/catalogo/producto/' . $p['slug'];
?>
<article class="group relative flex flex-col bg-white rounded-2xl border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-aj-teal/30">
    <a href="<?= e($url) ?>" class="relative block aspect-square bg-aj-teal-light overflow-hidden">
        <?php if (!empty($p['imagen_path'])): ?>
            <img src="/uploads/<?= e($p['imagen_path']) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy"
                 class="absolute inset-0 w-full h-full object-contain p-4 transition-transform duration-500 group-hover:scale-105">
        <?php else: ?>
            <span class="absolute inset-0 flex items-center justify-center text-aj-teal/25">
                <?= ajm_icon('box', 'w-16 h-16') ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($p['destacado'])): ?>
            <span class="absolute top-3 left-3 bg-aj-olive text-white text-[11px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full">Destacado</span>
        <?php endif; ?>
        <?php if ($p['disponibilidad'] !== 'disponible'): ?>
            <span class="absolute top-3 right-3 text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $disp['class'] ?>"><?= e($disp['label']) ?></span>
        <?php endif; ?>
    </a>

    <div class="flex-1 flex flex-col p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-aj-olive-dark mb-1 truncate">
            <?= e($p['categoria_nombre'] ?? '') ?><?= !empty($p['marca_nombre']) ? ' · ' . e($p['marca_nombre']) : '' ?>
        </p>
        <h3 class="font-semibold text-gray-900 leading-snug mb-1 line-clamp-2">
            <a href="<?= e($url) ?>" class="hover:text-aj-teal"><?= e($p['nombre']) ?></a>
        </h3>
        <?php if (!empty($p['presentacion'])): ?>
            <p class="text-xs text-gray-500 mb-1 line-clamp-1"><?= e($p['presentacion']) ?></p>
        <?php endif; ?>
        <?php if (!empty($p['sku'])): ?>
            <p class="text-[11px] text-gray-400 mb-2">Cód. <?= e($p['sku']) ?></p>
        <?php endif; ?>

        <div class="mt-auto pt-3 flex items-end justify-between gap-2">
            <div class="min-w-0">
                <?php if (Cat::precioVisible($p)): ?>
                    <?php if ($p['precio_oferta'] !== null && (float) $p['precio_oferta'] < (float) $p['precio']): ?>
                        <span class="block text-xs text-gray-400 line-through"><?= e(Cat::formatoPrecio($p['precio'])) ?></span>
                        <span class="block text-lg font-bold text-aj-teal"><?= e(Cat::formatoPrecio($p['precio_oferta'])) ?></span>
                    <?php else: ?>
                        <span class="block text-lg font-bold text-aj-teal"><?= e(Cat::formatoPrecio($p['precio'])) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= e($url) ?>" class="text-sm font-semibold text-aj-teal hover:underline">Ver detalle</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($csrfToken) && $p['disponibilidad'] !== 'agotado'): ?>
                <form method="post" action="/catalogo/cotizacion/agregar" class="js-add-quote shrink-0">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="producto_id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/catalogo') ?>">
                    <button type="submit" title="Agregar a cotización"
                            class="w-10 h-10 rounded-full bg-aj-teal hover:bg-aj-teal-dark text-white flex items-center justify-center transition">
                        <?= ajm_icon('plus', 'w-5 h-5') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
