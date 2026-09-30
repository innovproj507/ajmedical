<?php
/**
 * Catálogo imprimible — página autocontenida pensada para "Imprimir > Guardar como PDF".
 * Agrupa los productos por categoría (en el orden del árbol) con portada y datos de contacto.
 * @var array|null $categoria
 * @var array      $arbol
 * @var array      $productos
 */
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$grupos = [];
foreach ($productos as $p) {
    $grupos[(int) ($p['categoria_id'] ?? 0)][] = $p;
}
$secciones = [];
foreach ($arbol as $c) {
    if (!empty($grupos[(int) $c['id']])) {
        $secciones[] = ['titulo' => $c['nombre'], 'depth' => $c['depth'], 'productos' => $grupos[(int) $c['id']]];
        unset($grupos[(int) $c['id']]);
    }
}
foreach ($grupos as $resto) {
    $secciones[] = ['titulo' => 'Otros productos', 'depth' => 0, 'productos' => $resto];
}

$titulo  = $categoria ? $categoria['nombre'] : (string) setting('CATALOGO_TITULO', 'Catálogo de productos');
$precios = Cat::preciosActivos();
$contacto = array_filter([
    setting('CONTACT_PHONE') ? 'Tel. ' . setting('CONTACT_PHONE') : '',
    setting('CONTACT_WHATSAPP') ? 'WhatsApp +' . preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP')) : '',
    (string) setting('CONTACT_EMAIL'),
    preg_replace('#^https?://#', '', rtrim(SITE_URL, '/')),
]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($titulo) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: letter; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Poppins', system-ui, sans-serif; color: #243333; background: #eef1ef; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 12px; justify-content: center; align-items: center; padding: 12px 16px; background: #326666; color: #fff; font-size: 14px; flex-wrap: wrap; }
        .toolbar button, .toolbar a { font: inherit; font-weight: 600; border: 0; border-radius: 999px; padding: 9px 20px; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #9A9864; color: #fff; }
        .toolbar a { background: rgba(255,255,255,.15); color: #fff; }
        .sheet { max-width: 900px; margin: 24px auto; background: #fff; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .cover { text-align: center; padding: 60px 0 50px; border-bottom: 4px solid #9A9864; margin-bottom: 30px; position: relative; overflow: hidden; }
        .cover img { max-width: 380px; width: 70%; }
        .cover h1 { font-size: 30px; color: #326666; margin: 36px 0 6px; }
        .cover p { color: #6b7777; margin: 0; }
        .cover .fecha { margin-top: 18px; font-size: 13px; }
        .contacto { display: flex; flex-wrap: wrap; gap: 6px 18px; justify-content: center; font-size: 12px; color: #326666; margin-top: 20px; }
        h2 { font-size: 18px; color: #fff; background: #326666; padding: 8px 14px; border-radius: 10px 0 10px 0; margin: 28px 0 14px; break-after: avoid; }
        h2.sub { background: #eef4f3; color: #326666; font-size: 15px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .item { border: 1px solid #e3e7e5; border-radius: 12px; padding: 10px; break-inside: avoid; display: flex; flex-direction: column; }
        .item .img { height: 120px; display: flex; align-items: center; justify-content: center; background: #f5f8f7; border-radius: 8px; margin-bottom: 8px; }
        .item .img img { max-width: 100%; max-height: 110px; object-fit: contain; }
        .item .img span { color: #c0cccb; font-size: 11px; }
        .item h3 { font-size: 12.5px; margin: 0 0 3px; line-height: 1.3; }
        .item .meta { font-size: 10.5px; color: #6b7777; line-height: 1.4; }
        .item .precio { margin-top: auto; padding-top: 6px; font-weight: 700; color: #326666; font-size: 13px; }
        .nota { font-size: 11px; color: #8a9494; margin-top: 30px; text-align: center; }
        .vacio { text-align: center; color: #6b7777; padding: 40px 0; }
        @media (max-width: 640px) { .grid { grid-template-columns: repeat(2, 1fr); } .sheet { padding: 20px; margin: 0; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
            .cover { break-after: page; border-bottom: 0; padding-top: 120px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Para guardar en PDF elige <strong>“Guardar como PDF”</strong> como impresora.</span>
        <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
        <a href="<?= $categoria ? '/catalogo/categoria/' . e($categoria['slug']) : '/catalogo' ?>">Volver al catálogo</a>
    </div>

    <div class="sheet">
        <div class="cover">
            <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>">
            <h1><?= e($titulo) ?></h1>
            <p><?= e((string) setting('SITE_TAGLINE')) ?></p>
            <p class="fecha">Actualizado al <?= date('d/m/Y') ?> · <?= count($productos) ?> productos</p>
            <?php if ($contacto): ?>
                <div class="contacto"><?php foreach ($contacto as $c): ?><span><?= e($c) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$secciones): ?>
            <p class="vacio">No hay productos publicados en esta sección todavía.</p>
        <?php endif; ?>

        <?php foreach ($secciones as $s): ?>
            <h2 class="<?= $s['depth'] > 0 ? 'sub' : '' ?>"><?= e($s['titulo']) ?></h2>
            <div class="grid">
                <?php foreach ($s['productos'] as $p): ?>
                    <div class="item">
                        <div class="img">
                            <?php if (!empty($p['imagen_path'])): ?>
                                <img src="/uploads/<?= e($p['imagen_path']) ?>" alt="">
                            <?php else: ?>
                                <span>Sin imagen</span>
                            <?php endif; ?>
                        </div>
                        <h3><?= e($p['nombre']) ?></h3>
                        <div class="meta">
                            <?php if (!empty($p['sku'])): ?>Cód. <?= e($p['sku']) ?><br><?php endif; ?>
                            <?php if (!empty($p['presentacion'])): ?><?= e($p['presentacion']) ?><br><?php endif; ?>
                            <?php if (!empty($p['marca_nombre'])): ?>Marca: <?= e($p['marca_nombre']) ?><?php endif; ?>
                        </div>
                        <?php if ($precios && Cat::precioVisible($p)): ?>
                            <div class="precio"><?= e(Cat::formatoPrecio($p['precio_oferta'] ?? $p['precio'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <p class="nota">
            <?= $precios && setting('CATALOGO_NOTA_PRECIOS') ? e((string) setting('CATALOGO_NOTA_PRECIOS')) . ' ' : '' ?>
            Solicita tu cotización en <?= e(preg_replace('#^https?://#', '', rtrim(SITE_URL, '/'))) ?>/catalogo
        </p>
    </div>
</body>
</html>
