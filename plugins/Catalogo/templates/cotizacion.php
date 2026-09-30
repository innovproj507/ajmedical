<?php
/**
 * @var array       $items      [['producto' => [...], 'cantidad' => int], ...]
 * @var string      $csrfToken
 * @var string|null $enviada    Código de la cotización recién enviada
 * @var string|null $error
 */
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;
use AJM\Plugins\Catalogo\Cotizacion;

$whatsapp = preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP'));

$bannerImage = '';
$bannerTitle = 'Mi cotización';
$breadcrumb  = [['label' => 'Inicio', 'href' => '/'], ['label' => 'Catálogo', 'href' => '/catalogo'], ['label' => 'Cotización']];
include ROOT_PATH . '/templates/public/partials/page-banner.php';

$inputClass = 'w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal';
?>
<section class="max-w-7xl mx-auto px-5 md:px-10 py-12">

    <?php if ($enviada): ?>
        <div class="max-w-2xl mx-auto text-center rounded-3xl border border-gray-200 p-10">
            <span class="inline-flex w-16 h-16 rounded-full bg-green-100 text-green-600 items-center justify-center mb-5"><?= ajm_icon('check-circle', 'w-8 h-8') ?></span>
            <h2 class="text-2xl font-extrabold text-gray-900">¡Solicitud enviada!</h2>
            <p class="text-gray-600 mt-3">Recibimos tu solicitud <strong class="text-aj-teal"><?= e($enviada) ?></strong>. Te responderemos a la brevedad al correo que indicaste.</p>
            <a href="/catalogo" class="inline-flex items-center gap-2 mt-8 bg-aj-teal hover:bg-aj-teal-dark text-white font-semibold px-6 py-3 rounded-full transition">
                Seguir explorando el catálogo <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>

    <?php elseif (!$items): ?>
        <div class="max-w-2xl mx-auto text-center rounded-3xl border border-dashed border-gray-300 p-10">
            <span class="inline-flex w-16 h-16 rounded-full bg-aj-teal-light text-aj-teal items-center justify-center mb-5"><?= ajm_icon('clipboard', 'w-8 h-8') ?></span>
            <h2 class="text-xl font-bold text-gray-900">Tu cotización está vacía</h2>
            <p class="text-gray-500 mt-2">Agrega productos desde el catálogo con el botón <strong>+</strong> o “Agregar a cotización”.</p>
            <a href="/catalogo" class="inline-flex items-center gap-2 mt-6 bg-aj-teal hover:bg-aj-teal-dark text-white font-semibold px-6 py-3 rounded-full transition">
                Ir al catálogo <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>

    <?php else: ?>
        <?php if ($error): ?>
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <!-- Productos -->
            <div class="lg:col-span-3">
                <form method="post" action="/catalogo/cotizacion/actualizar">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="rounded-2xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                        <?php foreach ($items as $item): $p = $item['producto']; ?>
                            <div class="flex items-center gap-4 p-4">
                                <a href="/catalogo/producto/<?= e($p['slug']) ?>" class="w-20 h-20 rounded-xl bg-aj-teal-light shrink-0 overflow-hidden flex items-center justify-center">
                                    <?php if (!empty($p['imagen_path'])): ?>
                                        <img src="/uploads/<?= e($p['imagen_path']) ?>" alt="" class="w-full h-full object-contain p-1.5">
                                    <?php else: ?>
                                        <?= ajm_icon('box', 'w-8 h-8 text-aj-teal/30') ?>
                                    <?php endif; ?>
                                </a>
                                <div class="flex-1 min-w-0">
                                    <a href="/catalogo/producto/<?= e($p['slug']) ?>" class="font-semibold text-gray-900 hover:text-aj-teal leading-snug line-clamp-2"><?= e($p['nombre']) ?></a>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        <?= !empty($p['sku']) ? 'Cód. ' . e($p['sku']) : '' ?><?= !empty($p['sku']) && !empty($p['presentacion']) ? ' · ' : '' ?><?= e($p['presentacion'] ?? '') ?>
                                    </p>
                                    <?php if (Cat::precioVisible($p)): ?>
                                        <p class="text-sm font-semibold text-aj-teal mt-1"><?= e(Cat::formatoPrecio($p['precio_oferta'] ?? $p['precio'])) ?> <span class="text-xs font-normal text-gray-400">c/u</span></p>
                                    <?php endif; ?>
                                </div>
                                <input type="number" name="cantidad[<?= (int) $p['id'] ?>]" value="<?= (int) $item['cantidad'] ?>" min="0" max="99999"
                                       aria-label="Cantidad" class="w-20 rounded-xl border border-gray-300 px-3 py-2 text-sm text-center">
                                <button type="submit" name="quitar" value="<?= (int) $p['id'] ?>" title="Quitar" class="w-9 h-9 rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 flex items-center justify-center shrink-0">
                                    <?= ajm_icon('trash', 'w-4 h-4') ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                        <a href="/catalogo" class="text-sm font-semibold text-aj-teal hover:underline">← Agregar más productos</a>
                        <div class="flex gap-2">
                            <button type="submit" name="vaciar" value="1" onclick="return confirm('¿Vaciar la cotización?')" class="text-sm text-gray-500 hover:text-red-600 px-4 py-2">Vaciar</button>
                            <button type="submit" class="text-sm font-semibold border border-aj-teal text-aj-teal hover:bg-aj-teal hover:text-white transition px-5 py-2 rounded-full">Actualizar cantidades</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Datos de contacto -->
            <div class="lg:col-span-2">
                <form method="post" action="/catalogo/cotizacion/enviar" class="rounded-2xl bg-aj-teal-light/70 border border-aj-teal/10 p-6 space-y-4 lg:sticky lg:top-28">
                    <h2 class="text-lg font-bold text-gray-900">Tus datos</h2>
                    <p class="text-sm text-gray-500 -mt-2">Te enviaremos la cotización por correo.</p>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

                    <input type="text" name="nombre" required placeholder="Nombre completo *" class="<?= $inputClass ?>">
                    <input type="text" name="empresa" placeholder="Empresa / institución" class="<?= $inputClass ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input type="email" name="correo" required placeholder="Correo *" class="<?= $inputClass ?>">
                        <input type="tel" name="telefono" placeholder="Teléfono" class="<?= $inputClass ?>">
                    </div>
                    <textarea name="mensaje" rows="3" placeholder="Comentarios (entrega, facturación, alternativas…)" class="<?= $inputClass ?>"></textarea>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold py-3 rounded-full">
                        <?= ajm_icon('mail', 'w-5 h-5') ?> Enviar solicitud (<?= count($items) ?> producto<?= count($items) === 1 ? '' : 's' ?>)
                    </button>
                    <?php if ($whatsapp !== ''): ?>
                        <a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode(Cotizacion::textoWhatsapp($items)) ?>" target="_blank" rel="noopener"
                           class="w-full inline-flex items-center justify-center gap-2 bg-[#25D366] hover:opacity-90 transition text-white font-semibold py-3 rounded-full">
                            <?= ajm_icon('whatsapp', 'w-5 h-5') ?> Enviar por WhatsApp
                        </a>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400">Al enviar aceptas nuestras <a href="/politicas-de-privacidad" class="underline">políticas de privacidad</a>.</p>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
