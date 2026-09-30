<?php
/**
 * Barra lateral de Noticias (listado y detalle): búsqueda + post recientes.
 * @var array  $recent  Últimas entradas publicadas de tipo 'noticia'
 * @var string $search  Término de búsqueda actual (vacío en el detalle)
 */
$monthNamesEsShort = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$sidebarDayMonth = function (?string $datetime) use ($monthNamesEsShort): array {
    if (!$datetime) {
        return ['day' => '--', 'month' => ''];
    }
    $ts = strtotime($datetime);
    return ['day' => date('d', $ts), 'month' => $monthNamesEsShort[date('m', $ts)]];
};
?>
<aside class="space-y-8">
    <div class="rounded-2xl border border-gray-200 p-5">
        <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide mb-3">Búsqueda</h3>
        <span class="block w-10 h-1 rounded-full bg-aj-teal mb-4"></span>
        <form method="get" action="/noticias" class="flex rounded-full border border-gray-300 focus-within:ring-2 focus-within:ring-aj-teal overflow-hidden">
            <input type="text" name="q" value="<?= e($search ?? '') ?>" placeholder="Buscar noticias…"
                   class="flex-1 min-w-0 border-0 px-4 py-2.5 text-sm focus:outline-none">
            <button type="submit" class="shrink-0 rounded-full m-1 w-9 h-9 bg-aj-teal hover:bg-aj-teal-dark transition text-white flex items-center justify-center">
                <?= ajm_icon('search', 'w-4 h-4') ?>
            </button>
        </form>
    </div>

    <div class="rounded-2xl border border-gray-200 p-5">
        <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide mb-3">Post Recientes</h3>
        <span class="block w-10 h-1 rounded-full bg-aj-teal mb-4"></span>
        <ul class="space-y-4">
            <?php foreach ($recent as $r): $d = $sidebarDayMonth($r['published_at']); ?>
                <li>
                    <a href="/noticias/<?= e($r['slug']) ?>" class="flex items-center gap-3 group">
                        <span class="w-14 h-14 rounded-2xl bg-aj-teal/10 text-aj-teal text-sm font-bold flex flex-col items-center justify-center shrink-0 leading-none">
                            <?= e($d['day']) ?><span class="font-medium text-[11px]"><?= e($d['month']) ?></span>
                        </span>
                        <span class="text-sm text-gray-700 group-hover:text-aj-teal leading-snug"><?= e($r['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <li class="text-sm text-gray-400">Aún no hay publicaciones.</li>
            <?php endif; ?>
        </ul>
    </div>
</aside>
