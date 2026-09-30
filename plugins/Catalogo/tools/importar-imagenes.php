<?php
/**
 * Importa una carpeta de imágenes al catálogo. El nombre de cada archivo es el nombre del producto;
 * las fotos adicionales llevan un número al final ("Bata 1.jpg", "Bata 2.jpg") y la que no lleva
 * número es la principal. Si el mismo nombre+número viene en .png y .webp, se usa uno solo.
 *
 *   php plugins/Catalogo/tools/importar-imagenes.php imagenes [--mapa=plugins/Catalogo/tools/catalogo-inicial.php] [--crear] [--simular]
 *
 *   --mapa     Archivo PHP que devuelve ['nombre de archivo' => [nombre, categoria, marca, ...]] para
 *              corregir nombres, asignar categoría/marca/datos. Claves comparadas sin tildes ni mayúsculas.
 *   --crear    Crea como producto publicado lo que no exista (sin esto, solo agrega fotos a productos existentes).
 *   --simular  Muestra lo que haría sin tocar nada.
 *
 * Volver a correrlo no duplica fotos: se omiten las que el producto ya tiene (mismo nombre de archivo).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo desde la línea de comandos.');
}

require_once __DIR__ . '/../../../config/config.php';

use AJM\Core\Database;
use AJM\Core\Media;
use AJM\Plugins\Catalogo\CatalogoRepository;

$args    = array_slice($argv, 1);
$carpeta = null;
$opts    = ['crear' => false, 'simular' => false, 'mapa' => null];
foreach ($args as $a) {
    if ($a === '--crear') {
        $opts['crear'] = true;
    } elseif ($a === '--simular') {
        $opts['simular'] = true;
    } elseif (str_starts_with($a, '--mapa=')) {
        $opts['mapa'] = substr($a, 7);
    } else {
        $carpeta = $a;
    }
}
if (!$carpeta || !is_dir($carpeta)) {
    fwrite(STDERR, "Uso: php plugins/Catalogo/tools/importar-imagenes.php <carpeta> [--mapa=archivo.php] [--crear] [--simular]\n");
    exit(1);
}

$mapa = [];
if ($opts['mapa']) {
    foreach ((require $opts['mapa']) as $clave => $datos) {
        $mapa[ajm_slugify((string) $clave)] = $datos;
    }
}

// ─── 1. Agrupar archivos por producto ─────────────────────────────────────
$grupos = [];
foreach (scandir($carpeta) as $archivo) {
    $ruta = rtrim($carpeta, '/\\') . DIRECTORY_SEPARATOR . $archivo;
    if (!is_file($ruta) || !preg_match('/\.(jpe?g|png|webp)$/i', $archivo)) {
        continue;
    }
    $base = pathinfo($archivo, PATHINFO_FILENAME);
    $n = 0;
    if (preg_match('/^(.*?)\s*(\d+)$/u', $base, $m) && !preg_match('/\d\s*(ml|cc|mm|cm|g|kg|x\d*)$/iu', $base)) {
        [$base, $n] = [$m[1], (int) $m[2]];
    }
    $clave = ajm_slugify($base);
    // Mismo nombre + número en dos formatos (png y webp) = misma foto; se prefiere webp (más liviana).
    $actual = $grupos[$clave]['fotos'][$n] ?? null;
    if ($actual === null || str_ends_with(strtolower($archivo), '.webp')) {
        $grupos[$clave]['fotos'][$n] = $ruta;
    }
    $grupos[$clave]['base'] ??= trim($base);
}
ksort($grupos);

// ─── 2. Crear / actualizar productos y adjuntar fotos ─────────────────────
$repo  = CatalogoRepository::instance();
$db    = Database::getInstance();
$media = new Media();
$stats = ['creados' => 0, 'actualizados' => 0, 'fotos' => 0, 'omitidos' => 0];

foreach ($grupos as $clave => $grupo) {
    ksort($grupo['fotos']);
    $datos  = $mapa[$clave] ?? [];
    $nombre = $datos['nombre'] ?? mb_strtoupper(mb_substr($grupo['base'], 0, 1)) . mb_substr($grupo['base'], 1);

    $producto = $db->fetchOne('SELECT id FROM cat_productos WHERE slug = ? OR nombre = ? LIMIT 1', [ajm_slugify($nombre), $nombre]);
    $accion = $producto ? 'actualizar' : ($opts['crear'] ? 'crear' : 'omitir');
    printf("%-10s %-45s %d foto(s)\n", strtoupper($accion), $nombre, count($grupo['fotos']));
    if ($accion === 'omitir') {
        $stats['omitidos']++;
        continue;
    }
    if ($opts['simular']) {
        continue;
    }

    $input = $producto ? $repo->productoPorId((int) $producto['id']) : ['estado' => 'published', 'disponibilidad' => 'disponible', 'mostrar_precio' => 1];
    $input['nombre'] = $nombre;
    foreach (['sku', 'resumen', 'descripcion', 'presentacion', 'precio', 'destacado', 'orden'] as $campo) {
        if (array_key_exists($campo, $datos)) {
            $input[$campo] = $datos[$campo];
        }
    }
    if (!empty($datos['especificaciones'])) {
        $input['especificaciones'] = CatalogoRepository::parseEspecificaciones((string) $datos['especificaciones']);
    }
    if (!empty($datos['categoria'])) {
        $input['categoria_id'] = $repo->obtenerOCrear('cat_categorias', $datos['categoria']);
    }
    if (!empty($datos['marca'])) {
        $input['marca_id'] = $repo->obtenerOCrear('cat_marcas', $datos['marca']);
    }
    $id = $repo->guardarProducto($input, $producto ? (int) $producto['id'] : null);

    // Fotos: se agregan al final de la galería las que el producto todavía no tiene.
    $galeria    = $repo->galeriaIds($id);
    $existentes = $galeria
        ? array_column($db->fetchAll('SELECT filename FROM media WHERE id IN (' . implode(',', $galeria) . ')'), 'filename')
        : [];
    foreach ($grupo['fotos'] as $ruta) {
        if (in_array(basename($ruta), $existentes, true)) {
            continue;
        }
        $m = $media->importFile($ruta, 'catalogo', basename($ruta));
        $galeria[] = (int) $m['id'];
        $stats['fotos']++;
    }
    $repo->setImagenes($id, $galeria);

    $p = $repo->productoPorId($id);
    if (!$p['imagen_id'] && $galeria) {
        $p['imagen_id'] = $galeria[0];
        $repo->guardarProducto($p, $id);
    }
    $producto ? $stats['actualizados']++ : $stats['creados']++;
}

printf("\nListo: %d creados, %d actualizados, %d fotos importadas, %d sin producto (usa --crear).\n",
    $stats['creados'], $stats['actualizados'], $stats['fotos'], $stats['omitidos']);
