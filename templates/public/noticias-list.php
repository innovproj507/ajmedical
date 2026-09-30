<?php
/** @var array $entries */
/** @var array $recent */
/** @var string $search */
/** @var int $page */
/** @var int $totalPages */

use AJM\Core\Media;

$mediaRepo = new Media();

$monthNamesEs = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$dayMonth = function (?string $datetime) use ($monthNamesEs): array {
    if (!$datetime) {
        return ['day' => '--', 'month' => '', 'year' => ''];
    }
    $ts = strtotime($datetime);
    return ['day' => date('d', $ts), 'month' => $monthNamesEs[date('m', $ts)], 'year' => date('Y', $ts)];
};

include __DIR__ . '/partials/page-banner.php';
?>

<section class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

        <div class="lg:col-span-2">
            <?php if ($search !== ''): ?>
                <p class="text-sm text-gray-500 mb-6">
                    Resultados para "<span class="font-semibold text-gray-700"><?= e($search) ?></span>"
                    &middot; <a href="/noticias" class="text-aj-teal hover:underline">Quitar filtro</a>
                </p>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <?php foreach ($entries as $entry): $d = $dayMonth($entry['published_at']); $thumb = !empty($entry['featured_image_id']) ? $mediaRepo->find((int) $entry['featured_image_id']) : null; ?>
                    <article class="rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition bg-white">
                        <a href="/noticias/<?= e($entry['slug']) ?>" class="relative block aspect-[4/3] bg-gray-100">
                            <?php if ($thumb): ?>
                                <img src="<?= e(Media::url($thumb)) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
                            <?php endif; ?>
                            <span class="absolute top-3 left-3 bg-aj-teal text-white text-center rounded-md px-2.5 py-1.5 leading-none shadow">
                                <span class="block text-base font-extrabold"><?= e($d['day']) ?> <span class="font-semibold text-xs"><?= e($d['month']) ?></span></span>
                                <span class="block text-[10px] font-medium border-t border-white/30 mt-1 pt-1"><?= e($d['year']) ?></span>
                            </span>
                        </a>
                        <div class="p-5">
                            <h2 class="font-bold text-gray-900 leading-snug mb-2">
                                <a href="/noticias/<?= e($entry['slug']) ?>" class="hover:text-aj-teal"><?= e($entry['title']) ?></a>
                            </h2>
                            <p class="text-sm text-gray-500 mb-4 line-clamp-3"><?= e($entry['excerpt'] ?? '') ?></p>
                            <a href="/noticias/<?= e($entry['slug']) ?>" class="inline-block bg-aj-teal hover:bg-aj-teal-dark transition text-white text-xs font-semibold px-4 py-2 rounded-full">Leer Más</a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if (!$entries): ?>
                    <p class="sm:col-span-2 text-center text-gray-400 py-12">
                        <?= $search !== '' ? 'No se encontraron noticias para tu búsqueda.' : 'No hay noticias publicadas todavía.' ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="flex justify-center gap-1 mt-10">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="/noticias?page=<?= $p ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                           class="px-3 py-1.5 rounded-lg text-sm border <?= $p === $page ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/partials/noticias-sidebar.php'; ?>
    </div>
</section>
