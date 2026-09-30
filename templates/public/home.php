<?php
/** @var array|null $entry  Página "inicio" (su contenido, si tiene, se muestra bajo los destacados) */

use AJM\Core\Media;
use AJM\Core\Plugins;
use AJM\Core\Repository;
use AJM\Core\Security;
use AJM\Plugins\Catalogo\CatalogoRepository;

$repo       = new Repository();
$mediaRepo  = new Media();
$latestNews = $repo->listEntries('noticia', ['status' => 'published', 'limit' => 3]);
$nosotros   = $repo->findBySlug('pagina', 'nosotros');

$catalogo   = Plugins::isEnabled('catalogo');
$categorias = $catalogo ? CatalogoRepository::instance()->categoriasRaiz(12) : [];
$destacados = $catalogo ? CatalogoRepository::instance()->buscarProductos(['destacado' => true], 8) : [];
if ($catalogo && !$destacados) {
    $destacados = CatalogoRepository::instance()->buscarProductos(['orden' => 'recientes'], 8);
}
// Filas completas de 4 en la grilla (5 destacados dejarían uno solo en la segunda fila).
if (count($destacados) > 4) {
    $destacados = array_slice($destacados, 0, intdiv(count($destacados), 4) * 4);
}
$csrfToken  = $catalogo ? Security::generateCSRF() : '';
// Fabricantes con logo para la franja "Fabricantes que representamos".
$fabricantes = $catalogo ? array_values(array_filter(CatalogoRepository::instance()->marcas(), fn($m) => !empty($m['logo_path']))) : [];

// Capacidades clave (perfil corporativo de AJ Medical Supply).
$valores = [
    ['icon' => 'shield',    'title' => 'Calidad y marcas reconocidas', 'text' => 'Dispositivos médicos de fabricantes internacionales con registro sanitario y fichas técnicas.'],
    ['icon' => 'headset',   'title' => 'Equipo técnico especializado', 'text' => 'Asesoría técnica para elegir el insumo correcto en cada servicio.'],
    ['icon' => 'truck',     'title' => 'Solidez financiera y logística', 'text' => 'Abastecemos a instituciones públicas y privadas con entregas confiables.'],
    ['icon' => 'check-circle', 'title' => 'Ética, servicio y posventa', 'text' => 'Acompañamiento posventa y atención personalizada desde 2017.'],
];

$monthNamesEs = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$formatDate = function (?string $datetime) use ($monthNamesEs): string {
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return date('d', $ts) . ' ' . $monthNamesEs[date('m', $ts)] . ' ' . date('Y', $ts);
};
?>

<!-- Hero -->
<section class="relative overflow-hidden bg-gradient-to-br from-aj-teal-dark via-aj-teal to-aj-teal-dark">
    <!-- Cuadros de esquina redondeada — el motivo del logo, en grande -->
    <span class="aj-blob absolute -top-16 right-[4%] w-80 h-56 rounded-tl-[4rem] rounded-br-[4rem] bg-aj-olive/25 pointer-events-none"></span>
    <span class="aj-blob absolute top-40 right-[30%] w-32 h-24 rounded-tl-[2rem] rounded-br-[2rem] bg-white/10 pointer-events-none hidden md:block" style="animation-delay:-3s"></span>
    <span class="aj-blob absolute -bottom-20 left-[-4%] w-96 h-60 rounded-tl-[4rem] rounded-br-[4rem] bg-white/5 pointer-events-none" style="animation-delay:-5s"></span>
    <span class="aj-blob absolute bottom-24 left-[38%] w-24 h-16 rounded-tl-2xl rounded-br-2xl bg-aj-olive/30 pointer-events-none hidden md:block" style="animation-delay:-1s"></span>

    <div class="relative max-w-7xl mx-auto px-5 md:px-10 py-20 md:py-28 grid grid-cols-1 lg:grid-cols-5 gap-12 items-center">
        <div class="lg:col-span-3">
            <span class="reveal inline-block bg-white/10 backdrop-blur-sm ring-1 ring-white/15 text-white/90 text-sm md:text-base font-medium px-5 py-2 rounded-full mb-6">
                <?= e((string) setting('HERO_EYEBROW')) ?>
            </span>
            <h1 class="reveal text-white text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.05] tracking-tight mb-6"><?= e((string) setting('HERO_TITLE')) ?></h1>
            <?php if (setting('HERO_SUBTITLE')): ?>
                <p class="reveal text-white/80 text-lg max-w-2xl mb-8"><?= e((string) setting('HERO_SUBTITLE')) ?></p>
            <?php endif; ?>

            <?php if ($catalogo): ?>
                <form method="get" action="/catalogo" class="reveal flex max-w-xl rounded-full bg-white p-1.5 shadow-xl mb-8">
                    <span class="pl-4 flex items-center text-gray-400"><?= ajm_icon('search', 'w-5 h-5') ?></span>
                    <input type="text" name="q" placeholder="Busca guantes, gasas, jeringas, mascarillas…" aria-label="Buscar en el catálogo"
                           class="flex-1 min-w-0 border-0 px-3 py-3 text-sm md:text-base focus:outline-none rounded-full">
                    <button type="submit" class="shrink-0 bg-aj-olive hover:bg-aj-olive-dark transition text-white font-semibold px-6 rounded-full">Buscar</button>
                </form>
            <?php endif; ?>

            <div class="reveal flex flex-wrap items-center gap-6">
                <a href="<?= $catalogo ? '/catalogo' : '/contacto' ?>"
                   class="group inline-flex items-center gap-3 bg-white hover:bg-aj-teal-light transition text-aj-teal font-semibold px-7 py-3.5 rounded-full">
                    <?= $catalogo ? 'Ver catálogo' : 'Contáctanos' ?>
                    <span class="w-8 h-8 rounded-full bg-aj-teal text-white flex items-center justify-center transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5">
                        <?= ajm_icon('arrow-up-right', 'w-4 h-4') ?>
                    </span>
                </a>
                <?php if ($catalogo): ?>
                    <a href="/catalogo/cotizacion" class="group inline-flex items-center gap-3 text-white font-semibold">
                        Solicitar cotización
                        <span class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5">
                            <?= ajm_icon('arrow-up-right', 'w-4 h-4') ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tarjetas con cifras — editables en /admin/settings (categoría "hero") -->
        <div class="lg:col-span-2 hidden lg:flex flex-col gap-6">
            <?php foreach ([1, 2] as $n): if (!setting("HERO_STAT_{$n}_VALUE")) continue; ?>
                <div class="reveal flex items-center justify-between gap-6 rounded-3xl bg-white/10 backdrop-blur-md ring-1 ring-white/15 shadow-xl px-8 py-7 <?= $n === 2 ? 'ml-10' : '' ?>" style="transition-delay: <?= $n * 120 ?>ms">
                    <p class="text-aj-olive-light font-bold text-xl leading-tight max-w-[170px]"><?= e((string) setting("HERO_STAT_{$n}_LABEL")) ?></p>
                    <p class="text-white text-5xl font-extrabold whitespace-nowrap"><?= e((string) setting("HERO_STAT_{$n}_VALUE")) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-aj-olive"></div>
</section>

<!-- Por qué elegirnos -->
<section class="relative max-w-7xl mx-auto px-5 md:px-10 -mt-px">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 py-14">
        <?php foreach ($valores as $i => $v): ?>
            <div class="reveal group flex gap-4 rounded-2xl p-5 transition-colors duration-300 hover:bg-aj-teal-light" style="transition-delay: <?= $i * 80 ?>ms">
                <span class="w-12 h-12 rounded-tl-2xl rounded-br-2xl rounded-tr-md rounded-bl-md bg-aj-teal text-white flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:rotate-6">
                    <?= ajm_icon($v['icon'], 'w-6 h-6') ?>
                </span>
                <div>
                    <h3 class="font-bold text-gray-900"><?= e($v['title']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1 leading-snug"><?= e($v['text']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($categorias): ?>
    <!-- Categorías -->
    <section class="bg-aj-teal-light/60">
        <div class="max-w-7xl mx-auto px-5 md:px-10 py-16">
            <div class="reveal flex flex-wrap items-end justify-between gap-4 mb-10">
                <div>
                    <p class="text-aj-olive-dark font-semibold italic mb-1">Nuestro catálogo</p>
                    <h2 class="text-3xl font-extrabold"><span class="text-gray-900">Explora por</span> <span class="text-aj-teal">categoría</span></h2>
                </div>
                <a href="/catalogo" class="inline-flex items-center gap-2 text-aj-teal font-semibold hover:underline">Ver todo el catálogo <?= ajm_icon('arrow-right', 'w-4 h-4') ?></a>
            </div>
            <div class="grid grid-cols-2 <?= count($categorias) % 3 === 0 && count($categorias) % 4 !== 0 ? 'md:grid-cols-3' : 'md:grid-cols-4' ?> gap-4 sm:gap-6">
                <?php foreach ($categorias as $i => $cat): ?>
                    <a href="/catalogo/categoria/<?= e($cat['slug']) ?>"
                       class="reveal group relative overflow-hidden rounded-2xl bg-white border border-gray-200 p-5 min-h-[150px] flex flex-col justify-end transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
                       style="transition-delay: <?= $i * 60 ?>ms">
                        <?php if (!empty($cat['imagen_path'])): ?>
                            <img src="/uploads/<?= e($cat['imagen_path']) ?>" alt="" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            <span class="absolute inset-0 bg-gradient-to-t from-aj-teal-dark/85 via-aj-teal-dark/30 to-transparent"></span>
                        <?php else: ?>
                            <span class="absolute top-4 right-4 w-14 h-10 rounded-tl-2xl rounded-br-2xl rounded-tr-md rounded-bl-md <?= $i % 2 ? 'bg-aj-olive/20' : 'bg-aj-teal/10' ?> transition-transform duration-500 group-hover:scale-110"></span>
                            <span class="absolute top-5 left-5 text-aj-teal"><?= ajm_icon('box', 'w-8 h-8') ?></span>
                        <?php endif; ?>
                        <span class="relative font-bold leading-snug <?= !empty($cat['imagen_path']) ? 'text-white' : 'text-gray-900 group-hover:text-aj-teal' ?>"><?= e($cat['nombre']) ?></span>
                        <span class="relative text-xs mt-1 <?= !empty($cat['imagen_path']) ? 'text-white/75' : 'text-gray-400' ?>"><?= (int) $cat['total_arbol'] ?> producto<?= (int) $cat['total_arbol'] === 1 ? '' : 's' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($fabricantes): ?>
    <!-- Fabricantes que representamos -->
    <section class="border-y border-gray-100 bg-white">
        <div class="max-w-7xl mx-auto px-5 md:px-10 py-12">
            <p class="reveal text-center text-sm font-semibold uppercase tracking-widest text-aj-olive-dark mb-8">Fabricantes que representamos</p>
            <div class="flex flex-wrap justify-center gap-4">
                <?php foreach ($fabricantes as $i => $f): ?>
                    <a href="/catalogo?marca=<?= e($f['slug']) ?>" title="<?= e($f['razon_social'] ?: $f['nombre']) ?>"
                       class="reveal group flex items-center justify-center w-[calc(50%-0.5rem)] sm:w-32 h-24 rounded-2xl border border-gray-100 bg-white px-4 transition hover:border-aj-teal/30 hover:shadow-md"
                       style="transition-delay: <?= $i * 50 ?>ms">
                        <img src="/uploads/<?= e($f['logo_path']) ?>" alt="<?= e($f['nombre']) ?>" loading="lazy"
                             class="max-h-14 max-w-full object-contain grayscale opacity-70 transition group-hover:grayscale-0 group-hover:opacity-100">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($destacados): ?>
    <!-- Productos destacados -->
    <section class="max-w-7xl mx-auto px-5 md:px-10 py-16">
        <div class="reveal text-center mb-10">
            <h2 class="text-3xl font-extrabold"><span class="text-gray-900">Productos</span> <span class="text-aj-teal">destacados</span></h2>
            <p class="text-gray-500 mt-2">Agrega lo que necesites con <strong>+</strong> y envíanos tu solicitud de cotización.</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach ($destacados as $p): ?>
                <div class="reveal"><?php include ROOT_PATH . '/plugins/Catalogo/templates/partials/producto-card.php'; ?></div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-10">
            <a href="/catalogo" class="inline-flex items-center gap-2 bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold px-7 py-3 rounded-full">
                Ver todos los productos <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>
    </section>
    <?php include ROOT_PATH . '/plugins/Catalogo/templates/partials/quote-js.php'; ?>
<?php endif; ?>

<?php if (!empty($entry['data']['contenido'])): ?>
    <section class="reveal max-w-7xl mx-auto px-5 md:px-10 py-12">
        <div class="prose max-w-none prose-headings:text-aj-teal prose-a:text-aj-teal">
            <?= $entry['data']['contenido'] ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($nosotros): ?>
    <!-- Acerca de nosotros -->
    <section class="relative overflow-hidden bg-gradient-to-br from-white via-white to-aj-teal-light">
        <div class="relative max-w-7xl mx-auto px-5 md:px-10 py-16 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="reveal hidden lg:flex justify-center relative">
                <span class="aj-blob absolute top-0 left-10 w-56 h-40 rounded-tl-[3rem] rounded-br-[3rem] bg-aj-olive/20"></span>
                <span class="aj-blob absolute bottom-0 right-10 w-64 h-44 rounded-tl-[3rem] rounded-br-[3rem] bg-aj-teal/10" style="animation-delay:-4s"></span>
                <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>" class="relative w-[420px] h-auto drop-shadow-sm my-16">
            </div>
            <div>
                <p class="reveal text-aj-olive-dark font-semibold italic mb-1">Acerca de nosotros</p>
                <h2 class="reveal text-3xl font-extrabold text-aj-teal mb-5"><?= e(SITE_NAME) ?></h2>
                <p class="reveal text-gray-600 leading-relaxed"><?= e($nosotros['excerpt'] ?? '') ?></p>
                <a href="/nosotros" class="group reveal inline-flex items-center gap-2 mt-8 bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold px-6 py-3 rounded-full text-sm">
                    Conozca más
                    <?= ajm_icon('arrow-right', 'w-4 h-4 transition-transform duration-300 group-hover:translate-x-1') ?>
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($latestNews): ?>
    <!-- Noticias -->
    <section class="max-w-7xl mx-auto px-5 md:px-10 py-16">
        <div class="reveal text-center mb-10">
            <h2 class="text-3xl font-extrabold"><span class="text-gray-900">Noticias</span> <span class="text-aj-teal">recientes</span></h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
            <?php foreach ($latestNews as $i => $news): $thumb = !empty($news['featured_image_id']) ? $mediaRepo->find((int) $news['featured_image_id']) : null; ?>
                <article class="reveal bg-white rounded-2xl border border-gray-200 overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1.5" style="transition-delay: <?= $i * 100 ?>ms">
                    <a href="/noticias/<?= e($news['slug']) ?>" class="block aspect-[16/10] bg-aj-teal-light overflow-hidden">
                        <?php if ($thumb): ?>
                            <img src="<?= e(Media::url($thumb)) ?>" alt="" class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                        <?php endif; ?>
                    </a>
                    <div class="p-5">
                        <p class="text-xs text-gray-400 mb-2"><?= e($formatDate($news['published_at'])) ?></p>
                        <h3 class="font-bold text-gray-900 leading-snug mb-2"><a href="/noticias/<?= e($news['slug']) ?>" class="hover:text-aj-teal"><?= e($news['title']) ?></a></h3>
                        <p class="text-sm text-gray-500 line-clamp-2"><?= e($news['excerpt'] ?? '') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- Llamado a la acción -->
<section class="max-w-7xl mx-auto px-5 md:px-10 pb-20 pt-4">
    <div class="reveal relative overflow-hidden rounded-3xl bg-aj-teal px-8 py-12 md:px-14 md:py-14 flex flex-col md:flex-row items-center gap-8">
        <span class="absolute -top-10 -right-8 w-56 h-36 rounded-tl-[3rem] rounded-br-[3rem] bg-aj-olive/30"></span>
        <span class="absolute -bottom-12 left-1/3 w-40 h-28 rounded-tl-[2.5rem] rounded-br-[2.5rem] bg-white/5"></span>
        <div class="relative flex-1 text-center md:text-left">
            <h2 class="text-white text-2xl md:text-3xl font-extrabold">¿Listo para abastecer tu institución?</h2>
            <p class="text-white/75 mt-2">Cuéntanos qué necesitas y te enviamos una cotización a la medida.</p>
        </div>
        <div class="relative flex flex-wrap justify-center gap-3">
            <a href="<?= $catalogo ? '/catalogo/cotizacion' : '/contacto' ?>" class="inline-flex items-center gap-2 bg-white text-aj-teal font-semibold px-6 py-3 rounded-full hover:bg-aj-teal-light transition">
                Solicitar cotización <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
            </a>
            <a href="/contacto" class="inline-flex items-center gap-2 bg-aj-olive text-white font-semibold px-6 py-3 rounded-full hover:bg-aj-olive-dark transition">
                Contáctanos
            </a>
        </div>
    </div>
</section>
