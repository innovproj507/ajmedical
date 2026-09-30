<?php
/**
 * Admin — importar / exportar productos en CSV (compatible con Excel).
 * Importar hace "upsert": si el código (SKU) ya existe actualiza ese producto, si no lo crea.
 * Solo se actualizan las columnas presentes en el archivo.
 * @var callable $pluginUrl
 * @var string   $pageKey
 */
use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Security;
use AJM\Plugins\Catalogo\CatalogoRepository as Cat;

$currentUser = Auth::requireAuth('admin');
$repo = Cat::instance();

const CAT_CSV_COLUMNAS = [
    'sku'                => 'Código (SKU). Clave para actualizar productos existentes.',
    'nombre'             => 'Obligatorio.',
    'categoria'          => 'Nombre de la categoría (se crea si no existe).',
    'marca'              => 'Nombre de la marca (se crea si no existe).',
    'presentacion'       => 'Ej. Caja x 100 unidades.',
    'resumen'            => 'Texto corto del listado.',
    'descripcion'        => 'Texto largo.',
    'especificaciones'   => 'Separadas por salto de línea o por " | ". Ej. Material: Nitrilo | Talla: M',
    'registro_sanitario' => '',
    'precio'             => 'Número. Ej. 12.50',
    'precio_oferta'      => 'Número (opcional).',
    'mostrar_precio'     => 'si / no',
    'disponibilidad'     => 'disponible / bajo_pedido / agotado',
    'destacado'          => 'si / no',
    'estado'             => 'publicado / borrador',
];

$siNo = fn($v) => in_array(mb_strtolower(trim((string) $v)), ['si', 'sí', 's', '1', 'yes', 'y', 'x', 'true', 'verdadero'], true);

// ─── Exportar / plantilla ─────────────────────────────────────────────────
if (isset($_GET['export']) || isset($_GET['plantilla'])) {
    $nombre = isset($_GET['plantilla']) ? 'plantilla-productos.csv' : 'productos-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete las tildes
    fputcsv($out, array_keys(CAT_CSV_COLUMNAS), ';');

    if (isset($_GET['plantilla'])) {
        fputcsv($out, ['GN-100-M', 'Guantes de nitrilo sin polvo talla M', 'Protección personal', 'Marca X', 'Caja x 100 unidades',
            'Guantes desechables de nitrilo azul.', '', 'Material: Nitrilo | Color: Azul | Talla: M', '', '8.50', '', 'si', 'disponible', 'no', 'publicado'], ';');
    } else {
        $rows = $repo->buscarProductos(['estado' => null, 'orden' => 'nombre'], 100000);
        foreach ($rows as $p) {
            fputcsv($out, [
                $p['sku'], $p['nombre'], $p['categoria_nombre'], $p['marca_nombre'], $p['presentacion'], $p['resumen'],
                $p['descripcion'], implode(' | ', array_map(fn($s) => $s[1] !== '' ? "{$s[0]}: {$s[1]}" : $s[0], $p['especificaciones'])),
                $p['registro_sanitario'], $p['precio'], $p['precio_oferta'], $p['mostrar_precio'] ? 'si' : 'no',
                $p['disponibilidad'], $p['destacado'] ? 'si' : 'no', $p['estado'] === 'published' ? 'publicado' : 'borrador',
            ], ';');
        }
    }
    fclose($out);
    exit;
}

// ─── Importar ─────────────────────────────────────────────────────────────
$resultado = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['archivo'] ?? null;
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Selecciona un archivo CSV.';
    } elseif (!preg_match('/\.(csv|txt)$/i', $file['name'])) {
        $error = 'El archivo debe ser .csv. En Excel: Archivo > Guardar como > "CSV UTF-8 (delimitado por comas)".';
    } else {
        $contenido = file_get_contents($file['tmp_name']);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $primera   = strtok($contenido, "\n");
        $delim     = substr_count($primera, ';') >= substr_count($primera, ',') ? ';' : ',';

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $contenido);
        rewind($fh);

        $header = array_map(fn($h) => cat_normalizar_columna($h), fgetcsv($fh, 0, $delim) ?: []);
        if (!in_array('nombre', $header, true) && !in_array('sku', $header, true)) {
            $error = 'No se encontró la columna "nombre" ni "sku" en la primera fila. Descarga la plantilla para ver el formato.';
        } else {
            $resultado = ['creados' => 0, 'actualizados' => 0, 'errores' => []];
            $db = Database::getInstance();
            $linea = 1;
            while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                $linea++;
                if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $data = [];
                foreach ($header as $i => $col) {
                    if ($col !== '' && array_key_exists($col, CAT_CSV_COLUMNAS)) {
                        $data[$col] = trim((string) ($row[$i] ?? ''));
                    }
                }

                try {
                    $existente = null;
                    if (($data['sku'] ?? '') !== '') {
                        $existente = $db->fetchOne('SELECT id FROM cat_productos WHERE sku = ?', [$data['sku']]);
                    } elseif (($data['nombre'] ?? '') !== '') {
                        $existente = $db->fetchOne('SELECT id FROM cat_productos WHERE nombre = ? AND sku IS NULL', [$data['nombre']]);
                    }
                    $base = $existente ? $repo->productoPorId((int) $existente['id']) : ['mostrar_precio' => 1, 'estado' => 'published', 'disponibilidad' => 'disponible'];

                    $input = $base;
                    foreach (['sku', 'nombre', 'presentacion', 'resumen', 'descripcion', 'registro_sanitario', 'precio', 'precio_oferta'] as $c) {
                        if (array_key_exists($c, $data)) {
                            $input[$c] = $data[$c];
                        }
                    }
                    if (array_key_exists('categoria', $data)) {
                        $input['categoria_id'] = $repo->obtenerOCrear('cat_categorias', $data['categoria']);
                    }
                    if (array_key_exists('marca', $data)) {
                        $input['marca_id'] = $repo->obtenerOCrear('cat_marcas', $data['marca']);
                    }
                    if (array_key_exists('especificaciones', $data)) {
                        $input['especificaciones'] = Cat::parseEspecificaciones(str_replace(' | ', "\n", $data['especificaciones']));
                    }
                    if (array_key_exists('mostrar_precio', $data) && $data['mostrar_precio'] !== '') {
                        $input['mostrar_precio'] = $siNo($data['mostrar_precio']);
                    }
                    if (array_key_exists('destacado', $data) && $data['destacado'] !== '') {
                        $input['destacado'] = $siNo($data['destacado']);
                    }
                    if (!empty($data['disponibilidad'])) {
                        $d = str_replace(' ', '_', mb_strtolower($data['disponibilidad']));
                        $input['disponibilidad'] = isset(Cat::DISPONIBILIDAD[$d]) ? $d : 'disponible';
                    }
                    if (!empty($data['estado'])) {
                        $input['estado'] = in_array(mb_strtolower($data['estado']), ['borrador', 'draft', 'no', 'oculto'], true) ? 'draft' : 'published';
                    }
                    if ($existente) {
                        $input['slug'] = $base['slug'];
                    }

                    $repo->guardarProducto($input, $existente ? (int) $existente['id'] : null);
                    $existente ? $resultado['actualizados']++ : $resultado['creados']++;
                } catch (\Throwable $e) {
                    $resultado['errores'][] = "Fila {$linea}: " . $e->getMessage();
                }
            }
        }
        fclose($fh);
    }
}

/** "Código SKU" / "Categoría" / "PRECIO" → sku / categoria / precio */
function cat_normalizar_columna(string $h): string
{
    $h = ajm_slugify($h, '_');
    $alias = ['codigo' => 'sku', 'codigo_sku' => 'sku', 'producto' => 'nombre', 'nombre_del_producto' => 'nombre', 'precio_de_oferta' => 'precio_oferta', 'oferta' => 'precio_oferta', 'registro' => 'registro_sanitario', 'descripcion_corta' => 'resumen'];
    return $alias[$h] ?? $h;
}

$csrfToken = Security::generateCSRF();
$pageTitle = 'Catálogo — Importar / Exportar';
require ADMIN_PARTIALS . '/header.php';
include __DIR__ . '/_tabs.php';
?>

<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($resultado): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">
        Importación terminada: <strong><?= (int) $resultado['creados'] ?></strong> creados, <strong><?= (int) $resultado['actualizados'] ?></strong> actualizados<?= $resultado['errores'] ? ', <strong>' . count($resultado['errores']) . '</strong> con error' : '' ?>.
        <a href="<?= e($pluginUrl('productos')) ?>" class="underline font-semibold ml-1">Ver productos</a>
    </div>
    <?php if ($resultado['errores']): ?>
        <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs px-4 py-3 max-h-48 overflow-auto">
            <?php foreach ($resultado['errores'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">Importar productos</h2>
        <ol class="text-sm text-gray-600 space-y-1.5 list-decimal pl-5 mb-5">
            <li>Descarga la <a href="<?= e($pluginUrl('importar', ['plantilla' => 1])) ?>" class="text-aj-teal font-semibold hover:underline">plantilla CSV</a> (o exporta tus productos actuales).</li>
            <li>Ábrela en Excel y completa una fila por producto.</li>
            <li>Guárdala como <strong>CSV UTF-8</strong> y súbela aquí.</li>
        </ol>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="file" name="archivo" required accept=".csv,.txt" class="text-sm w-full">
            <button type="submit" class="rounded-lg bg-aj-teal text-white font-semibold px-5 py-2.5 text-sm hover:opacity-90 inline-flex items-center gap-2">
                <?= ajm_icon('upload', 'w-4 h-4') ?> Importar
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-4">Si el código (SKU) ya existe, el producto se actualiza con las columnas del archivo; si no, se crea. Las imágenes se agregan luego desde la edición de cada producto.</p>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">Exportar</h2>
            <p class="text-sm text-gray-600 mb-4">Descarga todos los productos (publicados y borradores) en un CSV que abre en Excel.</p>
            <a href="<?= e($pluginUrl('importar', ['export' => 1])) ?>" class="rounded-lg border border-aj-teal text-aj-teal font-semibold px-5 py-2.5 text-sm hover:bg-aj-teal/5 inline-flex items-center gap-2">
                <?= ajm_icon('download', 'w-4 h-4') ?> Exportar productos
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide px-5 pt-5 pb-3">Columnas</h2>
            <table class="w-full text-xs">
                <tbody class="divide-y divide-gray-100">
                    <?php foreach (CAT_CSV_COLUMNAS as $col => $desc): ?>
                        <tr><td class="px-5 py-2 font-mono text-gray-800 whitespace-nowrap"><?= e($col) ?></td><td class="px-5 py-2 text-gray-500"><?= e($desc) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
