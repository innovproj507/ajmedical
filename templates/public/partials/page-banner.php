<?php
/**
 * Banner de encabezado + breadcrumb, reutilizado por las páginas internas
 * (noticias, catálogo, páginas institucionales, ...). Espera:
 * @var string $bannerImage  Ruta absoluta (/uploads/...) — vacío = fondo de marca con los cuadros del logo
 * @var string $bannerTitle  Título mostrado sobre el banner
 * @var array  $breadcrumb   [['label' => 'Inicio', 'href' => '/'], ['label' => 'Noticias']]
 */
$bannerImage ??= '';
?>
<section class="relative h-56 md:h-64 lg:h-72 bg-aj-teal overflow-hidden">
    <?php if ($bannerImage !== ''): ?>
        <img src="<?= e($bannerImage) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-aj-teal-dark/60"></div>
    <?php else: ?>
        <div class="absolute inset-0 bg-gradient-to-br from-aj-teal-dark via-aj-teal to-aj-teal-dark"></div>
        <!-- Cuadros de esquina redondeada, el mismo motivo del logo -->
        <span class="aj-blob absolute -top-10 right-[8%] w-56 h-36 rounded-tl-[2.5rem] rounded-br-[2.5rem] bg-aj-olive/25"></span>
        <span class="aj-blob absolute top-24 right-[26%] w-24 h-16 rounded-tl-3xl rounded-br-3xl bg-white/10" style="animation-delay:-3s"></span>
        <span class="aj-blob absolute -bottom-12 left-[6%] w-64 h-40 rounded-tl-[2.5rem] rounded-br-[2.5rem] bg-white/5" style="animation-delay:-5s"></span>
        <span class="aj-blob absolute bottom-16 left-[24%] w-20 h-14 rounded-tl-2xl rounded-br-2xl bg-aj-olive/30" style="animation-delay:-1s"></span>
    <?php endif; ?>

    <div class="relative max-w-6xl mx-auto px-4 h-full flex flex-col items-center justify-center text-center gap-5">
        <h1 class="text-white text-3xl md:text-5xl font-extrabold"><?= e($bannerTitle) ?></h1>
        <div class="inline-flex flex-wrap items-center justify-center gap-2 bg-white rounded-full px-5 py-2 text-sm shadow-lg max-w-full">
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?php if ($i > 0): ?><?= ajm_icon('chevron-right', 'w-3.5 h-3.5 text-gray-400') ?><?php endif; ?>
                <?php if (!empty($crumb['href'])): ?>
                    <a href="<?= e($crumb['href']) ?>" class="text-aj-teal font-medium hover:underline"><?= e($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="text-gray-600 truncate max-w-[220px]"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-aj-olive"></div>
</section>
