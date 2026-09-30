<?php
/**
 * Pestañas comunes de las páginas admin del catálogo.
 * @var string   $pageKey    Página actual (viene de public/admin/plugin.php)
 * @var callable $pluginUrl
 */
$tabs = [
    'productos'  => ['label' => 'Productos',  'icon' => 'box'],
    'categorias' => ['label' => 'Categorías', 'icon' => 'layers'],
    'marcas'     => ['label' => 'Marcas / fabricantes',     'icon' => 'tag'],
    'importar'   => ['label' => 'Importar / Exportar', 'icon' => 'upload'],
];
$tabActual = $pageKey === 'producto' ? 'productos' : $pageKey;
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex flex-wrap gap-1 bg-white border border-gray-200 rounded-xl p-1">
        <?php foreach ($tabs as $key => $tab): $on = $tabActual === $key; ?>
            <a href="<?= e($pluginUrl($key)) ?>"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-medium transition <?= $on ? 'bg-aj-teal text-white' : 'text-gray-600 hover:bg-gray-50' ?>">
                <?= ajm_icon($tab['icon'], 'w-4 h-4') ?> <?= e($tab['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3 text-sm">
        <a href="/catalogo" target="_blank" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-aj-teal"><?= ajm_icon('eye', 'w-4 h-4') ?> Ver catálogo</a>
        <a href="/catalogo/imprimir" target="_blank" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-aj-teal"><?= ajm_icon('printer', 'w-4 h-4') ?> PDF</a>
        <?php if (\AJM\Core\Auth::hasRole('super_admin')): ?>
            <a href="/admin/settings/index.php#catalogo" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-aj-teal"><?= ajm_icon('settings', 'w-4 h-4') ?> Ajustes</a>
        <?php endif; ?>
    </div>
</div>
