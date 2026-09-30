<?php
/** @var array $entry */
/** @var array $recent */
use AJM\Core\Media;
use AJM\Core\Repository;

$monthNamesEs = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
$formatDate = function (?string $datetime) use ($monthNamesEs): string {
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return (int) date('d', $ts) . ' de ' . $monthNamesEs[date('m', $ts)] . ' de ' . date('Y', $ts);
};

$cover = !empty($entry['featured_image_id']) ? (new Media())->find((int) $entry['featured_image_id']) : null;
$search = '';

$bannerImage = $cover ? Media::url($cover) : '';
$bannerTitle = 'Noticias';
$breadcrumb  = [
    ['label' => 'Inicio', 'href' => '/'],
    ['label' => 'Noticias', 'href' => '/noticias'],
    ['label' => $entry['title']],
];
include __DIR__ . '/partials/page-banner.php';

$mediaRepo2 = new Media();
$lista = (new Repository())->listEntries('noticia', ['status' => 'published', 'limit' => 200, 'order' => 'published_at DESC, created_at DESC']);

$idxActual = null;
foreach ($lista as $i => $item) {
    if ($item['slug'] === $entry['slug']) {
        $idxActual = $i;
        break;
    }
}
$anterior = ($idxActual !== null && $idxActual > 0) ? $lista[$idxActual - 1] : null;
$siguiente = ($idxActual !== null && $idxActual < count($lista) - 1) ? $lista[$idxActual + 1] : null;
$relacionados = [];
if ($idxActual !== null) {
    foreach ($lista as $i => $item) {
        if ($i === $idxActual) {
            continue;
        }
        $relacionados[] = $item;
        if (count($relacionados) >= 2) {
            break;
        }
    }
}

$shareUrl = rtrim(SITE_URL, '/') . '/noticias/' . $entry['slug'];
?>
<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

        <article class="lg:col-span-2">
            <p class="text-xs text-gray-400 mb-2"><?= e($formatDate($entry['published_at'] ?? null)) ?></p>
            <h1 class="text-3xl font-bold text-aj-teal mb-6"><?= e($entry['title']) ?></h1>

            <div class="prose max-w-none">
                <?= $entry['data']['contenido'] ?? '' ?>
            </div>
            <div class="clear-both"></div>

            <?php if (!empty($entry['data']['fuente'])): ?>
                <p class="text-sm text-gray-400 mt-8">Fuente: <?= e($entry['data']['fuente']) ?></p>
            <?php endif; ?>

            <a href="/noticias" class="inline-block mt-10 text-aj-teal font-medium hover:underline">← Volver a Noticias</a>

            <!-- Compartir -->
            <div class="not-prose flex flex-wrap items-center justify-between gap-4 mt-8 pt-6 border-t border-gray-100">
                <span class="text-sm font-semibold text-gray-500">Compartir:</span>
                <div class="flex items-center gap-2.5">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener" title="Compartir en Facebook"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-white hover:opacity-85 transition" style="background-color:#1877F2">
                        <svg viewBox="0 0 24 24" class="w-4 h-4"><path d="M14 9h2.5V6H14c-1.7 0-3 1.3-3 3v2H9v3h2v7h3v-7h2.3l.7-3H14V9.2c0-.1.1-.2.3-.2Z" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode($entry['title']) ?>" target="_blank" rel="noopener" title="Compartir en X"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-white hover:opacity-85 transition" style="background-color:#000000">
                        <svg viewBox="0 0 24 24" class="w-4 h-4"><path d="M4 4l7.3 9.7L4.4 20h1.9l6.1-5.4L17.4 20H20l-7.6-10.1L19 4h-1.9l-5.6 5-4.4-5H4Z" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener" title="Compartir en LinkedIn"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-white hover:opacity-85 transition" style="background-color:#0A66C2">
                        <svg viewBox="0 0 24 24" class="w-4 h-4"><path d="M6.9 8.5H4.2V19h2.7V8.5ZM5.6 4.5a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2ZM19.8 19h-2.7v-5.5c0-1.3-.5-2.2-1.6-2.2-.9 0-1.4.6-1.6 1.2-.1.2-.1.5-.1.8V19H11s0-9.6 0-10.6h2.7v1.5c.4-.6 1-1.5 2.6-1.5 1.9 0 3.4 1.3 3.4 4V19Z" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a href="https://wa.me/?text=<?= urlencode($entry['title'] . ' ' . $shareUrl) ?>" target="_blank" rel="noopener" title="Compartir en WhatsApp"
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-white hover:opacity-85 transition" style="background-color:#25D366">
                        <svg viewBox="0 0 24 24" class="w-4 h-4"><path d="M12 3.5a8.4 8.4 0 0 0-7.2 12.7L3.5 20.5l4.4-1.3A8.4 8.4 0 1 0 12 3.5Z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M9 9c.2-.5.5-.5.8-.5h.5c.2 0 .4 0 .6.4l.7 1.6c.1.2 0 .4-.1.6l-.4.5c-.1.2-.1.3 0 .5.4.7 1.4 1.7 2.1 2.1.2.1.3.1.5 0l.5-.4c.2-.1.4-.2.6-.1l1.6.7c.3.2.3.4.3.6v.5c0 .3 0 .6-.5.8-.9.4-2 .4-3.5-.5-1.5-.9-2.9-2.3-3.8-3.8-.9-1.5-.9-2.6-.5-3.5Z" fill="currentColor" stroke="none"/></svg>
                    </a>
                </div>
            </div>

            <!-- Anterior / Siguiente -->
            <?php if ($anterior || $siguiente): ?>
                <div class="not-prose flex items-stretch gap-4 mt-6 rounded-2xl border border-gray-200 overflow-hidden">
                    <a href="<?= $anterior ? '/noticias/' . e($anterior['slug']) : '#' ?>"
                       class="flex-1 flex items-center gap-3 p-4 <?= $anterior ? 'hover:bg-gray-50 transition' : 'opacity-40 pointer-events-none' ?>">
                        <?= ajm_icon('chevron-right', 'w-4 h-4 text-aj-teal shrink-0 rotate-180') ?>
                        <?php $thumbA = $anterior && !empty($anterior['featured_image_id']) ? $mediaRepo2->find((int) $anterior['featured_image_id']) : null; ?>
                        <?php if ($thumbA): ?>
                            <img src="<?= e(Media::url($thumbA)) ?>" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0">
                        <?php endif; ?>
                        <span class="min-w-0">
                            <span class="block text-xs text-gray-400">Anterior</span>
                            <span class="block text-sm font-semibold text-gray-800 truncate"><?= $anterior ? e($anterior['title']) : 'Inicio' ?></span>
                        </span>
                    </a>
                    <span class="w-px bg-gray-200"></span>
                    <a href="<?= $siguiente ? '/noticias/' . e($siguiente['slug']) : '#' ?>"
                       class="flex-1 flex items-center justify-end gap-3 p-4 text-right <?= $siguiente ? 'hover:bg-gray-50 transition' : 'opacity-40 pointer-events-none' ?>">
                        <span class="min-w-0">
                            <span class="block text-xs text-gray-400">Siguiente</span>
                            <span class="block text-sm font-semibold text-gray-800 truncate"><?= $siguiente ? e($siguiente['title']) : 'Fin' ?></span>
                        </span>
                        <?php $thumbS = $siguiente && !empty($siguiente['featured_image_id']) ? $mediaRepo2->find((int) $siguiente['featured_image_id']) : null; ?>
                        <?php if ($thumbS): ?>
                            <img src="<?= e(Media::url($thumbS)) ?>" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0">
                        <?php endif; ?>
                        <?= ajm_icon('chevron-right', 'w-4 h-4 text-aj-teal shrink-0') ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Relacionados -->
            <?php if ($relacionados): ?>
                <div class="mt-10">
                    <h2 class="text-lg font-bold text-aj-teal mb-4">Noticias Relacionadas</h2>
                    <div class="grid sm:grid-cols-2 gap-6">
                        <?php foreach ($relacionados as $rel): ?>
                            <?php $thumbR = !empty($rel['featured_image_id']) ? $mediaRepo2->find((int) $rel['featured_image_id']) : null; ?>
                            <article class="rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition bg-white">
                                <a href="/noticias/<?= e($rel['slug']) ?>" class="relative block aspect-[16/10] bg-gray-100">
                                    <?php if ($thumbR): ?>
                                        <img src="<?= e(Media::url($thumbR)) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
                                    <?php endif; ?>
                                </a>
                                <div class="p-4">
                                    <p class="text-xs text-gray-400 mb-1"><?= e($formatDate($rel['published_at'] ?? null)) ?></p>
                                    <h3 class="font-bold text-gray-900 leading-snug mb-2">
                                        <a href="/noticias/<?= e($rel['slug']) ?>" class="hover:text-aj-teal"><?= e($rel['title']) ?></a>
                                    </h3>
                                    <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?= e($rel['excerpt'] ?? '') ?></p>
                                    <a href="/noticias/<?= e($rel['slug']) ?>" class="inline-flex items-center gap-1 text-aj-teal hover:text-aj-teal-dark text-xs font-semibold">
                                        Leer Más <?= ajm_icon('chevron-right', 'w-3.5 h-3.5') ?>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>

        <?php include __DIR__ . '/partials/noticias-sidebar.php'; ?>
    </div>
</section>
