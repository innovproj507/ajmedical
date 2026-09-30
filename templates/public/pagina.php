<?php
/** @var array $entry */

use AJM\Core\Media;
use AJM\Core\Menu;
use AJM\Core\Repository;

$mediaRepo = new Media();
$featured = !empty($entry['featured_image_id']) ? $mediaRepo->find((int) $entry['featured_image_id']) : null;
$recent = (new Repository())->listEntries('noticia', ['status' => 'published', 'limit' => 4]);
$search = '';

// Busca si esta página cuelga de una sección del menú (ej. Nosotros > Historia)
// para armar el breadcrumb "Inicio > Sección > Título".
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$section = null;
foreach ((new Menu())->tree('principal') as $item) {
    foreach ($item['children'] as $child) {
        if (rtrim($child['resolved_url'], '/') === $currentPath) {
            $section = $item;
            break 2;
        }
    }
}

$breadcrumb = [['label' => 'Inicio', 'href' => '/']];
if ($section) {
    $breadcrumb[] = ['label' => $section['label'], 'href' => $section['resolved_url'] !== '#' ? $section['resolved_url'] : null];
}
$breadcrumb[] = ['label' => $entry['title']];

$bannerImage = $featured ? Media::url($featured) : '';
$bannerTitle = $entry['title'];
include __DIR__ . '/partials/page-banner.php';
?>
<section class="relative max-w-6xl mx-auto px-4 py-16 overflow-hidden">
    <?= ajm_icon('sparkle', 'aj-sparkle absolute top-6 right-[6%] w-6 h-6 text-aj-olive/60 hidden lg:block') ?>
    <?= ajm_icon('sparkle', 'aj-sparkle absolute bottom-24 left-[2%] w-5 h-5 text-aj-teal/50 hidden lg:block') ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <article class="reveal lg:col-span-2">
            <div class="prose max-w-none prose-headings:text-aj-teal prose-headings:font-bold prose-a:text-aj-teal prose-a:no-underline hover:prose-a:underline prose-strong:text-gray-900 prose-blockquote:border-aj-teal prose-blockquote:text-gray-600 prose-blockquote:not-italic prose-img:rounded-xl prose-img:shadow-lg prose-figcaption:text-center">
                <?= $entry['data']['contenido'] ?? '' ?>
            </div>
        </article>

        <div class="reveal">
            <?php include __DIR__ . '/partials/noticias-sidebar.php'; ?>
        </div>
    </div>
</section>
