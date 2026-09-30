<?php
/** @var string $pageTitle */
/** @var string $content */
/** @var string|null $seoDescription */
use AJM\Core\Menu;
use AJM\Core\Plugins;
use AJM\Plugins\Catalogo\Cotizacion;

$menuItems = (new Menu())->tree('principal');

$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

// Contacto y redes — editables en /admin/settings (categorías "contacto" y "redes"). Vacío = no se muestra.
$contactEmail   = (string) setting('CONTACT_EMAIL');
$contactPhone   = (string) setting('CONTACT_PHONE');
$contactAddress = (string) setting('CONTACT_ADDRESS');
$contactHours   = (string) setting('CONTACT_HOURS');
$whatsapp       = preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP'));

$social = array_filter([
    ['label' => 'Facebook',  'href' => (string) setting('SOCIAL_FACEBOOK'),  'icon' => 'facebook',  'brand' => '#1877F2'],
    ['label' => 'Instagram', 'href' => (string) setting('SOCIAL_INSTAGRAM'), 'icon' => 'instagram', 'brand' => '#E1306C'],
    ['label' => 'LinkedIn',  'href' => (string) setting('SOCIAL_LINKEDIN'),  'icon' => 'linkedin',  'brand' => '#0A66C2'],
], fn($s) => $s['href'] !== '');

$socialIcons = [
    'facebook'  => '<path d="M14 9h2.5V6H14c-1.7 0-3 1.3-3 3v2H9v3h2v7h3v-7h2.3l.7-3H14V9.2c0-.1.1-.2.3-.2Z" fill="currentColor" stroke="none"/>',
    'instagram' => '<rect x="4.5" y="4.5" width="15" height="15" rx="4" fill="none" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="12" r="3.4" fill="none" stroke="currentColor" stroke-width="1.75"/><circle cx="16.2" cy="7.8" r="1" fill="currentColor" stroke="none"/>',
    'linkedin'  => '<path d="M6.9 8.5H4.2V19h2.7V8.5ZM5.6 4.5a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2ZM19.8 19h-2.7v-5.5c0-1.3-.5-2.2-1.6-2.2-.9 0-1.4.6-1.6 1.2-.1.2-.1.5-.1.8V19H11s0-9.6 0-10.6h2.7v1.5c.4-.6 1-1.5 2.6-1.5 1.9 0 3.4 1.3 3.4 4V19Z" fill="currentColor" stroke="none"/>',
];

// Carrito de cotización (plugin catálogo) — ícono con contador en el encabezado.
$catalogoActivo = Plugins::isEnabled('catalogo');
$quoteCount     = $catalogoActivo ? Cotizacion::count() : 0;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pageTitle ? e($pageTitle) . ' — ' : '' ?><?= e(SITE_NAME) ?><?= !$pageTitle ? ' — ' . e((string) setting('SITE_TAGLINE')) : '' ?></title>
    <?php if (!empty($seoDescription)): ?>
        <meta name="description" content="<?= e($seoDescription) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/dist/css/site.min.css">
</head>
<body class="min-h-screen flex flex-col bg-white text-gray-800 font-sans">

<!-- Barra superior: contacto rápido -->
<?php if ($contactPhone !== '' || $contactEmail !== '' || $contactHours !== ''): ?>
    <div class="hidden md:block bg-aj-teal-dark text-white/80 text-xs">
        <div class="max-w-7xl mx-auto px-5 md:px-10 h-9 flex items-center justify-between gap-6">
            <span class="flex items-center gap-2"><?= ajm_icon('clock', 'w-3.5 h-3.5 text-aj-olive-light') ?> <?= e($contactHours) ?></span>
            <div class="flex items-center gap-6">
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= e($contactEmail) ?>" class="flex items-center gap-2 hover:text-white"><?= ajm_icon('mail', 'w-3.5 h-3.5 text-aj-olive-light') ?> <?= e($contactEmail) ?></a>
                <?php endif; ?>
                <?php if ($contactPhone !== ''): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>" class="flex items-center gap-2 hover:text-white"><?= ajm_icon('phone', 'w-3.5 h-3.5 text-aj-olive-light') ?> <?= e($contactPhone) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Encabezado: blanco y fijo arriba (el logo es verde/oliva y necesita fondo claro) -->
<header id="aj-header" class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-gray-100 transition-shadow duration-300">
    <div class="max-w-7xl mx-auto px-5 md:px-10 flex items-center justify-between gap-6 h-20">
        <a href="/" class="flex items-center shrink-0">
            <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>" class="h-12 md:h-14 w-auto shrink-0">
        </a>

        <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold">
            <?php foreach ($menuItems as $item):
                $itemPath = rtrim($item['resolved_url'], '/') ?: '/';
                $isActive = $itemPath === $currentPath || ($itemPath !== '/' && str_starts_with($currentPath, $itemPath . '/'));
            ?>
                <div class="relative group shrink-0">
                    <a href="<?= e($item['resolved_url']) ?>"
                       class="relative flex items-center gap-1.5 py-7 transition <?= $isActive ? 'text-aj-teal' : 'text-gray-600 hover:text-aj-teal' ?>">
                        <?= e($item['label']) ?>
                        <?php if (!empty($item['children'])): ?>
                            <?= ajm_icon('chevron-down', 'w-4 h-4 opacity-70') ?>
                        <?php endif; ?>
                        <?php if ($isActive): ?>
                            <span class="absolute left-0 right-0 bottom-0 h-0.5 rounded-full bg-aj-olive"></span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                        <div class="absolute left-0 top-full hidden group-hover:block min-w-[220px]">
                            <div class="bg-white text-gray-700 rounded-2xl shadow-lg ring-1 ring-gray-100 py-2 font-medium">
                                <?php foreach ($item['children'] as $child): ?>
                                    <a href="<?= e($child['resolved_url']) ?>" class="block px-4 py-2 text-sm hover:bg-aj-teal-light hover:text-aj-teal"><?= e($child['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-3 shrink-0">
            <?php if ($catalogoActivo): ?>
                <a href="/catalogo/cotizacion" title="Mi cotización"
                   class="relative w-11 h-11 rounded-full bg-aj-teal-light text-aj-teal hover:bg-aj-teal hover:text-white transition flex items-center justify-center">
                    <?= ajm_icon('clipboard', 'w-5 h-5') ?>
                    <span data-quote-count class="<?= $quoteCount > 0 ? 'flex' : 'hidden' ?> absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-aj-olive text-white text-[11px] font-bold items-center justify-center"><?= (int) $quoteCount ?></span>
                </a>
                <a href="/catalogo" class="hidden md:inline-flex items-center gap-2 bg-aj-teal hover:bg-aj-teal-dark transition text-white text-sm font-semibold px-5 py-3 rounded-full">
                    <?= ajm_icon('grid', 'w-4 h-4') ?> Ver catálogo
                </a>
            <?php endif; ?>
            <button type="button" id="aj-mobile-toggle" aria-label="Abrir menú" class="lg:hidden w-11 h-11 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center shrink-0">
                <?= ajm_icon('menu', 'w-5 h-5') ?>
            </button>
        </div>
    </div>

    <nav id="aj-mobile-menu" class="hidden lg:hidden bg-white border-t border-gray-100 px-5 pt-2 pb-4 text-sm font-semibold">
        <?php foreach ($menuItems as $item): ?>
            <a href="<?= e($item['resolved_url']) ?>" class="block py-3 text-gray-700 border-b border-gray-100"><?= e($item['label']) ?></a>
            <?php foreach ($item['children'] ?? [] as $child): ?>
                <a href="<?= e($child['resolved_url']) ?>" class="block py-2.5 pl-4 text-sm text-gray-500 border-b border-gray-100"><?= e($child['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</header>

<script>
(function () {
    var header = document.getElementById('aj-header');
    if (!header) return;
    function onScroll() {
        header.classList.toggle('shadow-md', window.scrollY > 10);
    }
    window.addEventListener('scroll', onScroll);
    onScroll();

    var toggle = document.getElementById('aj-mobile-toggle');
    var menu = document.getElementById('aj-mobile-menu');
    if (toggle && menu) {
        toggle.addEventListener('click', function () { menu.classList.toggle('hidden'); });
    }
})();
</script>

<main class="flex-1">
    <?= $content ?>
</main>

<footer class="relative bg-aj-teal-dark text-white/70 text-sm overflow-hidden">
    <!-- Cuadros del logo como textura de fondo -->
    <span class="absolute -top-16 -right-10 w-72 h-48 rounded-tl-[3rem] rounded-br-[3rem] bg-white/[0.04] pointer-events-none"></span>
    <span class="absolute bottom-10 -left-16 w-64 h-40 rounded-tl-[3rem] rounded-br-[3rem] bg-aj-olive/10 pointer-events-none"></span>

    <div class="relative max-w-7xl mx-auto px-5 md:px-10 pt-16 sm:pt-20">
        <!-- Logo + CTA -->
        <div class="flex flex-wrap items-center justify-between gap-5 pb-8 border-b border-white/15">
            <a href="/" class="inline-flex items-center bg-white rounded-2xl px-5 py-3 shrink-0">
                <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>" class="h-11 w-auto">
            </a>
            <div class="flex flex-wrap gap-3">
                <?php if ($catalogoActivo): ?>
                    <a href="/catalogo/cotizacion" class="inline-flex items-center gap-2 bg-aj-olive hover:bg-aj-olive-dark text-white font-semibold text-sm px-6 py-3 rounded-full transition">
                        Solicitar cotización <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
                    </a>
                <?php endif; ?>
                <a href="/contacto" class="inline-flex items-center gap-2 bg-white text-aj-teal font-semibold text-sm px-6 py-3 rounded-full hover:bg-white/90 transition">
                    Contáctenos <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
                </a>
            </div>
        </div>

        <!-- Columnas de enlaces -->
        <div class="py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
            <div>
                <h3 class="text-white font-bold text-base mb-4"><?= e(SITE_NAME) ?></h3>
                <p class="leading-relaxed"><?= e((string) setting('HERO_SUBTITLE', 'Distribución de insumos médicos y hospitalarios.')) ?></p>
                <?php if ($social): ?>
                    <div class="flex items-center gap-2.5 mt-5">
                        <?php foreach ($social as $s): ?>
                            <a href="<?= e($s['href']) ?>" target="_blank" rel="noopener" title="<?= e($s['label']) ?>"
                               class="w-10 h-10 rounded-lg flex items-center justify-center text-white hover:opacity-85 transition"
                               style="background-color: <?= e($s['brand']) ?>">
                                <svg viewBox="0 0 24 24" class="w-4.5 h-4.5"><?= $socialIcons[$s['icon']] ?></svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h3 class="text-white font-bold text-base mb-4">Menú</h3>
                <ul class="space-y-2.5">
                    <?php foreach ($menuItems as $item): ?>
                        <li>
                            <a href="<?= e($item['resolved_url']) ?>" class="flex items-center gap-1.5 hover:text-white transition">
                                <?= ajm_icon('chevron-right', 'w-3.5 h-3.5 text-aj-olive-light shrink-0') ?> <?= e($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h3 class="text-white font-bold text-base mb-4">Catálogo</h3>
                <ul class="space-y-2.5">
                    <?php if ($catalogoActivo): ?>
                        <?php foreach (\AJM\Plugins\Catalogo\CatalogoRepository::instance()->categoriasRaiz(6) as $cat): ?>
                            <li>
                                <a href="/catalogo/categoria/<?= e($cat['slug']) ?>" class="flex items-center gap-1.5 hover:text-white transition">
                                    <?= ajm_icon('chevron-right', 'w-3.5 h-3.5 text-aj-olive-light shrink-0') ?> <?= e($cat['nombre']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li>
                            <a href="/catalogo/imprimir" class="flex items-center gap-1.5 hover:text-white transition">
                                <?= ajm_icon('download', 'w-3.5 h-3.5 text-aj-olive-light shrink-0') ?> Descargar catálogo
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="text-white/40">Próximamente.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div>
                <h3 class="text-white font-bold text-base mb-4">Contáctenos</h3>
                <ul class="space-y-4">
                    <?php if ($contactAddress !== ''): ?>
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-white/10 text-aj-olive-light flex items-center justify-center shrink-0"><?= ajm_icon('map-pin', 'w-5 h-5') ?></span>
                            <div>
                                <p class="text-white font-semibold text-sm m-0">Dirección</p>
                                <p class="text-white/70 m-0"><?= nl2br(e($contactAddress)) ?></p>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if ($contactPhone !== ''): ?>
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-white/10 text-aj-olive-light flex items-center justify-center shrink-0"><?= ajm_icon('phone', 'w-5 h-5') ?></span>
                            <div>
                                <p class="text-white font-semibold text-sm m-0">Teléfono</p>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>" class="hover:text-white"><?= e($contactPhone) ?></a>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if ($contactEmail !== ''): ?>
                        <li class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-full bg-white/10 text-aj-olive-light flex items-center justify-center shrink-0"><?= ajm_icon('mail', 'w-5 h-5') ?></span>
                            <div>
                                <p class="text-white font-semibold text-sm m-0">Correo</p>
                                <a href="mailto:<?= e($contactEmail) ?>" class="hover:text-white break-all"><?= e($contactEmail) ?></a>
                            </div>
                        </li>
                    <?php endif; ?>
                    <?php if ($contactAddress === '' && $contactPhone === '' && $contactEmail === ''): ?>
                        <li><a href="/contacto" class="hover:text-white">Escríbenos desde el formulario de contacto.</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="relative border-t border-white/10 py-5">
        <div class="max-w-7xl mx-auto px-5 md:px-10 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <p>Copyright &copy; <?= date('Y') ?> <span class="text-white/80"><?= e(SITE_NAME) ?></span>. Todos los derechos reservados.</p>
            <div class="flex items-center gap-5">
                <a href="/nosotros" class="hover:text-white">Nosotros</a>
                <a href="/politicas-de-privacidad" class="hover:text-white">Políticas de privacidad</a>
            </div>
        </div>
    </div>

    <button type="button" id="aj-scroll-top" aria-label="Volver arriba"
            class="hidden fixed bottom-6 right-6 z-40 w-11 h-11 rounded-full bg-aj-teal text-white shadow-lg items-center justify-center hover:bg-aj-teal-dark transition">
        <?= ajm_icon('arrow-up', 'w-5 h-5') ?>
    </button>
</footer>

<?php if ($whatsapp !== ''): ?>
    <!-- Botón flotante de WhatsApp -->
    <a href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode('Hola ' . SITE_NAME . ', quisiera información sobre sus productos.') ?>"
       target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp"
       class="fixed bottom-6 left-6 z-40 w-14 h-14 rounded-full bg-[#25D366] text-white shadow-lg flex items-center justify-center hover:scale-105 transition">
        <?= ajm_icon('whatsapp', 'w-7 h-7') ?>
    </a>
<?php endif; ?>

<script>
(function () {
    var btn = document.getElementById('aj-scroll-top');
    if (!btn) return;
    window.addEventListener('scroll', function () {
        btn.classList.toggle('hidden', window.scrollY < 400);
        btn.classList.toggle('flex', window.scrollY >= 400);
    });
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>

<script>
// Revela elementos con la clase .reveal a medida que entran en pantalla (usado en home y futuras páginas).
(function () {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;

    if (!('IntersectionObserver' in window)) {
        els.forEach(function (el) { el.classList.add('reveal-visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    els.forEach(function (el) { observer.observe(el); });
})();
</script>

<!-- Lightbox: galerías de fotos y reproductor de video (contenido de noticias) -->
<div id="aj-lightbox" class="fixed inset-0 z-50 hidden bg-black/90 items-center justify-center p-4 sm:p-8">
    <button type="button" id="aj-lightbox-close" aria-label="Cerrar"
            class="absolute top-4 right-4 sm:top-6 sm:right-6 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
    </button>
    <button type="button" id="aj-lightbox-prev" aria-label="Anterior"
            class="absolute left-2 sm:left-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6" /></svg>
    </button>
    <button type="button" id="aj-lightbox-next" aria-label="Siguiente"
            class="absolute right-2 sm:right-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6" /></svg>
    </button>
    <div id="aj-lightbox-content" class="max-w-4xl w-full max-h-full flex items-center justify-center"></div>
</div>

<script>
(function () {
    var overlay = document.getElementById('aj-lightbox');
    if (!overlay) return;
    var content = document.getElementById('aj-lightbox-content');
    var prevBtn = document.getElementById('aj-lightbox-prev');
    var nextBtn = document.getElementById('aj-lightbox-next');

    var group = [];
    var index = 0;

    function render() {
        var el = group[index];
        var type = el.getAttribute('data-lightbox-type') || 'image';
        var src = el.getAttribute('data-src') || el.getAttribute('href');
        content.innerHTML = '';

        if (type === 'youtube') {
            var wrap = document.createElement('div');
            wrap.className = 'w-full aspect-video';
            var iframe = document.createElement('iframe');
            iframe.src = src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
            iframe.allowFullscreen = true;
            iframe.className = 'w-full h-full rounded-lg';
            wrap.appendChild(iframe);
            content.appendChild(wrap);
        } else if (type === 'video') {
            var video = document.createElement('video');
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            video.className = 'max-w-full max-h-[85vh] rounded-lg';
            content.appendChild(video);
        } else {
            var img = document.createElement('img');
            img.src = src;
            img.className = 'max-w-full max-h-[85vh] rounded-lg object-contain';
            content.appendChild(img);
        }

        var multi = group.length > 1;
        prevBtn.classList.toggle('hidden', !multi);
        nextBtn.classList.toggle('hidden', !multi);
    }

    function open(el) {
        var groupName = el.getAttribute('data-lightbox');
        group = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox="' + CSS.escape(groupName) + '"]'));
        index = group.indexOf(el);
        render();
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        content.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-lightbox]');
        if (trigger) {
            e.preventDefault();
            open(trigger);
            return;
        }
        if (e.target === overlay || e.target.closest('#aj-lightbox-close')) {
            close();
        } else if (e.target.closest('#aj-lightbox-prev')) {
            index = (index - 1 + group.length) % group.length;
            render();
        } else if (e.target.closest('#aj-lightbox-next')) {
            index = (index + 1) % group.length;
            render();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (overlay.classList.contains('hidden')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') prevBtn.click();
        if (e.key === 'ArrowRight') nextBtn.click();
    });
})();
</script>

</body>
</html>
