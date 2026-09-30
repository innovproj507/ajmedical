<?php

namespace AJM\Plugins\Catalogo;

use AJM\Core\AuditLogger;
use AJM\Core\Database;
use InvalidArgumentException;

/**
 * CatalogoRepository — lectura/escritura de productos, categorías y marcas del catálogo.
 * Tablas propias (cat_*) en vez de content_entries: los productos necesitan columnas
 * filtrables (categoría, marca, disponibilidad, precio) que un JSON no indexa.
 */
class CatalogoRepository
{
    public const DISPONIBILIDAD = [
        'disponible'  => ['label' => 'Disponible',  'class' => 'bg-green-100 text-green-700'],
        'bajo_pedido' => ['label' => 'Bajo pedido', 'class' => 'bg-amber-100 text-amber-700'],
        'agotado'     => ['label' => 'Agotado',     'class' => 'bg-red-100 text-red-600'],
    ];

    public const ORDENES = [
        'relevancia'  => 'Destacados primero',
        'nombre'      => 'Nombre (A-Z)',
        'recientes'   => 'Más recientes',
        'precio_asc'  => 'Precio: menor a mayor',
        'precio_desc' => 'Precio: mayor a menor',
    ];

    private static ?self $instance = null;
    private Database $db;
    private ?array $categoriasCache = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // ─── CATEGORÍAS ───────────────────────────────────────────────────────

    /** Todas las categorías, con `total` = productos publicados directamente en ella. */
    public function categorias(bool $soloActivas = true): array
    {
        if ($soloActivas && $this->categoriasCache !== null) {
            return $this->categoriasCache;
        }
        $rows = $this->db->fetchAll(
            "SELECT c.*, m.path AS imagen_path,
                    (SELECT COUNT(*) FROM cat_productos p WHERE p.categoria_id = c.id AND p.estado = 'published') AS total
             FROM cat_categorias c
             LEFT JOIN media m ON m.id = c.imagen_id
             " . ($soloActivas ? 'WHERE c.activo = 1' : '') . "
             ORDER BY c.orden ASC, c.nombre ASC"
        );
        if ($soloActivas) {
            $this->categoriasCache = $rows;
        }
        return $rows;
    }

    /**
     * Árbol plano en orden jerárquico: cada fila trae `depth` y `total_arbol`
     * (productos de la categoría + todas sus subcategorías).
     */
    public function categoriasArbol(bool $soloActivas = true): array
    {
        $all = $this->categorias($soloActivas);
        $byParent = [];
        foreach ($all as $c) {
            $byParent[(int) ($c['parent_id'] ?? 0)][] = $c;
        }
        $ids = array_column($all, 'id');

        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $c) {
                $c['depth'] = $depth;
                $index = count($out);
                $out[] = $c;
                $before = count($out);
                $walk((int) $c['id'], $depth + 1);
                $sum = (int) $c['total'];
                for ($i = $before; $i < count($out); $i++) {
                    $sum += (int) $out[$i]['total'];
                }
                $out[$index]['total_arbol'] = $sum;
            }
        };
        $walk(0, 0);

        // Huérfanas (padre inactivo o inexistente) al final, como raíz.
        foreach ($all as $c) {
            if ($c['parent_id'] && !in_array($c['parent_id'], $ids)) {
                $c['depth'] = 0;
                $c['total_arbol'] = (int) $c['total'];
                $out[] = $c;
            }
        }
        return $out;
    }

    public function categoriasRaiz(int $limit = 0): array
    {
        $rows = array_values(array_filter($this->categoriasArbol(), fn($c) => $c['depth'] === 0));
        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    public function categoriaPorSlug(string $slug): ?array
    {
        return $this->db->fetchOne('SELECT * FROM cat_categorias WHERE slug = ? AND activo = 1', [$slug]);
    }

    public function categoriaPorId(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM cat_categorias WHERE id = ?', [$id]);
    }

    /** IDs de la categoría y todas sus descendientes (para filtrar "Curaciones" e incluir sus subcategorías). */
    public function descendientesIds(int $id): array
    {
        $all = $this->categorias(false);
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $next = [];
            foreach ($all as $c) {
                if ($c['parent_id'] && in_array((int) $c['parent_id'], $frontier, true) && !in_array((int) $c['id'], $ids, true)) {
                    $ids[] = (int) $c['id'];
                    $next[] = (int) $c['id'];
                }
            }
            $frontier = $next;
        }
        return $ids;
    }

    /** Cadena de ancestros desde la raíz hasta la categoría (para el breadcrumb). */
    public function rutaCategoria(?int $id): array
    {
        $ruta = [];
        $guard = 0;
        while ($id && $guard++ < 10) {
            $cat = $this->categoriaPorId($id);
            if (!$cat) {
                break;
            }
            array_unshift($ruta, $cat);
            $id = $cat['parent_id'] ? (int) $cat['parent_id'] : null;
        }
        return $ruta;
    }

    public function guardarCategoria(array $input, ?int $id = null): int
    {
        $nombre = trim($input['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }
        $parentId = !empty($input['parent_id']) ? (int) $input['parent_id'] : null;
        if ($id && $parentId && in_array($parentId, $this->descendientesIds($id), true)) {
            throw new InvalidArgumentException('Una categoría no puede ser subcategoría de sí misma ni de sus hijas.');
        }

        $cols = [
            'parent_id'   => $parentId,
            'nombre'      => $nombre,
            'slug'        => $this->slugUnico('cat_categorias', $input['slug'] ?: $nombre, $id),
            'descripcion' => trim($input['descripcion'] ?? '') ?: null,
            'imagen_id'   => !empty($input['imagen_id']) ? (int) $input['imagen_id'] : null,
            'orden'       => (int) ($input['orden'] ?? 0),
            'activo'      => !empty($input['activo']) ? 1 : 0,
        ];
        return $this->upsert('cat_categorias', $cols, $id);
    }

    public function eliminarCategoria(int $id): void
    {
        $this->delete('cat_categorias', $id);
    }

    // ─── MARCAS ───────────────────────────────────────────────────────────

    public function marcas(bool $soloActivas = true): array
    {
        return $this->db->fetchAll(
            "SELECT b.*, m.path AS logo_path,
                    (SELECT COUNT(*) FROM cat_productos p WHERE p.marca_id = b.id AND p.estado = 'published') AS total
             FROM cat_marcas b
             LEFT JOIN media m ON m.id = b.logo_id
             " . ($soloActivas ? 'WHERE b.activo = 1' : '') . "
             ORDER BY b.orden ASC, b.nombre ASC"
        );
    }

    public function marcaPorSlug(string $slug): ?array
    {
        return $this->db->fetchOne('SELECT * FROM cat_marcas WHERE slug = ? AND activo = 1', [$slug]);
    }

    public function guardarMarca(array $input, ?int $id = null): int
    {
        $nombre = trim($input['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la marca es obligatorio.');
        }
        return $this->upsert('cat_marcas', [
            'nombre'       => $nombre,
            'razon_social' => trim($input['razon_social'] ?? '') ?: null,
            'slug'         => $this->slugUnico('cat_marcas', ($input['slug'] ?? '') ?: $nombre, $id),
            'logo_id'      => !empty($input['logo_id']) ? (int) $input['logo_id'] : null,
            'orden'        => (int) ($input['orden'] ?? 0),
            'activo'       => !empty($input['activo']) ? 1 : 0,
        ], $id);
    }

    public function eliminarMarca(int $id): void
    {
        $this->delete('cat_marcas', $id);
    }

    /** Busca una marca/categoría por nombre y la crea si no existe (importación CSV). */
    public function obtenerOCrear(string $tabla, string $nombre): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '' || !in_array($tabla, ['cat_categorias', 'cat_marcas'], true)) {
            return null;
        }
        $row = $this->db->fetchOne("SELECT id FROM {$tabla} WHERE nombre = ? LIMIT 1", [$nombre]);
        if ($row) {
            return (int) $row['id'];
        }
        return $tabla === 'cat_categorias'
            ? $this->guardarCategoria(['nombre' => $nombre, 'slug' => '', 'activo' => 1])
            : $this->guardarMarca(['nombre' => $nombre, 'slug' => '', 'activo' => 1]);
    }

    // ─── PRODUCTOS: LECTURA ───────────────────────────────────────────────

    /**
     * @param array{q?:string,categoria_id?:int,marca_id?:int,disponibilidad?:string,destacado?:bool,estado?:string|null,orden?:string} $f
     */
    public function buscarProductos(array $f = [], int $limit = 12, int $offset = 0): array
    {
        [$where, $params] = $this->filtros($f);

        $orden = match ($f['orden'] ?? 'relevancia') {
            'nombre'      => 'p.nombre ASC',
            'recientes'   => 'p.created_at DESC',
            'precio_asc'  => 'p.precio IS NULL, COALESCE(p.precio_oferta, p.precio) ASC',
            'precio_desc' => 'p.precio IS NULL, COALESCE(p.precio_oferta, p.precio) DESC',
            'admin'       => 'p.updated_at DESC',
            default       => 'p.destacado DESC, p.orden ASC, p.nombre ASC',
        };

        $sql = "SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                       b.nombre AS marca_nombre, b.slug AS marca_slug, m.path AS imagen_path
                FROM cat_productos p
                LEFT JOIN cat_categorias c ON c.id = p.categoria_id
                LEFT JOIN cat_marcas b ON b.id = p.marca_id
                LEFT JOIN media m ON m.id = p.imagen_id
                WHERE {$where}
                ORDER BY {$orden}
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        return array_map([$this, 'decode'], $this->db->fetchAll($sql, $params));
    }

    public function contarProductos(array $f = []): int
    {
        [$where, $params] = $this->filtros($f);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM cat_productos p WHERE {$where}", $params);
        return (int) ($row['total'] ?? 0);
    }

    private function filtros(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $estado = array_key_exists('estado', $f) ? $f['estado'] : 'published';
        if ($estado) {
            $where[]  = 'p.estado = ?';
            $params[] = $estado;
        }
        if (!empty($f['q'])) {
            $where[] = '(p.nombre LIKE ? OR p.sku LIKE ? OR p.resumen LIKE ? OR p.presentacion LIKE ?
                         OR p.marca_id IN (SELECT id FROM cat_marcas WHERE nombre LIKE ? OR razon_social LIKE ?))';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if (!empty($f['categoria_id'])) {
            $ids = $this->descendientesIds((int) $f['categoria_id']);
            $where[] = 'p.categoria_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        if (!empty($f['sin_categoria'])) {
            $where[] = 'p.categoria_id IS NULL';
        }
        if (!empty($f['marca_id'])) {
            $where[]  = 'p.marca_id = ?';
            $params[] = (int) $f['marca_id'];
        }
        if (!empty($f['disponibilidad']) && isset(self::DISPONIBILIDAD[$f['disponibilidad']])) {
            $where[]  = 'p.disponibilidad = ?';
            $params[] = $f['disponibilidad'];
        }
        if (!empty($f['destacado'])) {
            $where[] = 'p.destacado = 1';
        }
        if (!empty($f['excluir_id'])) {
            $where[]  = 'p.id <> ?';
            $params[] = (int) $f['excluir_id'];
        }
        return [implode(' AND ', $where), $params];
    }

    public function productoPorSlug(string $slug, bool $soloPublicados = true): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug,
                    b.nombre AS marca_nombre, b.slug AS marca_slug, b.razon_social AS marca_razon_social, bl.path AS marca_logo_path,
                    m.path AS imagen_path, f.path AS ficha_path
             FROM cat_productos p
             LEFT JOIN cat_categorias c ON c.id = p.categoria_id
             LEFT JOIN cat_marcas b ON b.id = p.marca_id
             LEFT JOIN media bl ON bl.id = b.logo_id
             LEFT JOIN media m ON m.id = p.imagen_id
             LEFT JOIN media f ON f.id = p.ficha_tecnica_id
             WHERE p.slug = ?" . ($soloPublicados ? " AND p.estado = 'published'" : '') . ' LIMIT 1',
            [$slug]
        );
        return $row ? $this->decode($row) : null;
    }

    public function productoPorId(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT p.*, m.path AS imagen_path, f.path AS ficha_path, f.filename AS ficha_nombre
             FROM cat_productos p
             LEFT JOIN media m ON m.id = p.imagen_id
             LEFT JOIN media f ON f.id = p.ficha_tecnica_id
             WHERE p.id = ?',
            [$id]
        );
        return $row ? $this->decode($row) : null;
    }

    /** @param int[] $ids */
    public function productosPorIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT p.*, m.path AS imagen_path FROM cat_productos p
             LEFT JOIN media m ON m.id = p.imagen_id
             WHERE p.estado = 'published' AND p.id IN (" . implode(',', $ids) . ')'
        );
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $this->decode($row);
        }
        return $byId;
    }

    /** Galería completa: la imagen principal primero, luego el resto en su orden. */
    public function imagenes(array $producto): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.* FROM cat_producto_imagenes pi JOIN media m ON m.id = pi.media_id
             WHERE pi.producto_id = ? ORDER BY pi.orden ASC, m.id ASC',
            [$producto['id']]
        );
        usort($rows, fn($a, $b) => ((int) $b['id'] === (int) $producto['imagen_id']) <=> ((int) $a['id'] === (int) $producto['imagen_id']));
        return $rows;
    }

    public function relacionados(array $producto, int $limit = 4): array
    {
        if (!$producto['categoria_id']) {
            return $this->buscarProductos(['destacado' => true, 'excluir_id' => $producto['id']], $limit);
        }
        return $this->buscarProductos(['categoria_id' => $producto['categoria_id'], 'excluir_id' => $producto['id']], $limit);
    }

    private function decode(array $row): array
    {
        $row['especificaciones'] = json_decode($row['especificaciones'] ?? '[]', true) ?: [];
        return $row;
    }

    // ─── PRODUCTOS: ESCRITURA ─────────────────────────────────────────────

    public function guardarProducto(array $input, ?int $id = null): int
    {
        $nombre = trim($input['nombre'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del producto es obligatorio.');
        }

        $sku = trim($input['sku'] ?? '') ?: null;
        if ($sku !== null) {
            $dup = $this->db->fetchOne('SELECT id FROM cat_productos WHERE sku = ? AND id <> ?', [$sku, (int) $id]);
            if ($dup) {
                throw new InvalidArgumentException("Ya existe otro producto con el código (SKU) {$sku}.");
            }
        }

        $disp = $input['disponibilidad'] ?? 'disponible';
        $cols = [
            'categoria_id'       => !empty($input['categoria_id']) ? (int) $input['categoria_id'] : null,
            'marca_id'           => !empty($input['marca_id']) ? (int) $input['marca_id'] : null,
            'sku'                => $sku,
            'nombre'             => $nombre,
            'slug'               => $this->slugUnico('cat_productos', ($input['slug'] ?? '') ?: $nombre, $id),
            'resumen'            => mb_substr(trim($input['resumen'] ?? ''), 0, 500) ?: null,
            'descripcion'        => trim($input['descripcion'] ?? '') ?: null,
            'especificaciones'   => json_encode(is_array($input['especificaciones'] ?? null) ? $input['especificaciones'] : self::parseEspecificaciones((string) ($input['especificaciones'] ?? '')), JSON_UNESCAPED_UNICODE),
            'presentacion'       => trim($input['presentacion'] ?? '') ?: null,
            'registro_sanitario' => trim($input['registro_sanitario'] ?? '') ?: null,
            'precio'             => self::parsePrecio($input['precio'] ?? null),
            'precio_oferta'      => self::parsePrecio($input['precio_oferta'] ?? null),
            'mostrar_precio'     => !empty($input['mostrar_precio']) ? 1 : 0,
            'disponibilidad'     => isset(self::DISPONIBILIDAD[$disp]) ? $disp : 'disponible',
            'destacado'          => !empty($input['destacado']) ? 1 : 0,
            'estado'             => ($input['estado'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'imagen_id'          => !empty($input['imagen_id']) ? (int) $input['imagen_id'] : null,
            'ficha_tecnica_id'   => !empty($input['ficha_tecnica_id']) ? (int) $input['ficha_tecnica_id'] : null,
            'orden'              => (int) ($input['orden'] ?? 0),
            'seo_title'          => trim($input['seo_title'] ?? '') ?: null,
            'seo_description'    => trim($input['seo_description'] ?? '') ?: null,
        ];
        return $this->upsert('cat_productos', $cols, $id);
    }

    /** Reemplaza la galería del producto por $mediaIds (en ese orden). */
    public function setImagenes(int $productoId, array $mediaIds): void
    {
        $this->db->execute('DELETE FROM cat_producto_imagenes WHERE producto_id = ?', [$productoId]);
        foreach (array_values(array_unique(array_map('intval', $mediaIds))) as $i => $mediaId) {
            if ($mediaId > 0) {
                $this->db->execute(
                    'INSERT INTO cat_producto_imagenes (producto_id, media_id, orden) VALUES (?, ?, ?)',
                    [$productoId, $mediaId, $i]
                );
            }
        }
    }

    public function galeriaIds(int $productoId): array
    {
        return array_map('intval', array_column(
            $this->db->fetchAll('SELECT media_id FROM cat_producto_imagenes WHERE producto_id = ? ORDER BY orden', [$productoId]),
            'media_id'
        ));
    }

    public function duplicarProducto(int $id): int
    {
        $p = $this->productoPorId($id);
        if (!$p) {
            throw new InvalidArgumentException('Producto no encontrado.');
        }
        $p['nombre'] .= ' (copia)';
        $p['slug']    = '';
        $p['sku']     = null;
        $p['estado']  = 'draft';
        $newId = $this->guardarProducto($p);
        $this->setImagenes($newId, $this->galeriaIds($id));
        return $newId;
    }

    public function eliminarProducto(int $id): void
    {
        $this->delete('cat_productos', $id);
    }

    // ─── PRECIOS ──────────────────────────────────────────────────────────

    public static function preciosActivos(): bool
    {
        return setting('CATALOGO_MOSTRAR_PRECIOS', false) === true;
    }

    /** El precio se ve solo si el switch global está encendido, el producto lo permite y tiene precio. */
    public static function precioVisible(array $p): bool
    {
        return self::preciosActivos() && !empty($p['mostrar_precio']) && $p['precio'] !== null;
    }

    public static function formatoPrecio(float|string|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        return setting('CATALOGO_MONEDA', '$') . ' ' . number_format((float) $valor, 2, '.', ',');
    }

    public static function parsePrecio(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $limpio = preg_replace('/[^0-9.,-]/', '', (string) $valor);
        // "1.234,50" → 1234.50 ; "1,234.50" → 1234.50 ; "12,5" → 12.5
        if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
            $limpio = strrpos($limpio, ',') > strrpos($limpio, '.')
                ? str_replace(['.', ','], ['', '.'], $limpio)
                : str_replace(',', '', $limpio);
        } else {
            $limpio = str_replace(',', '.', $limpio);
        }
        return is_numeric($limpio) ? round((float) $limpio, 2) : null;
    }

    /** "Material: Nitrilo\nTalla: M" → [['Material','Nitrilo'], ['Talla','M']] */
    public static function parseEspecificaciones(string $texto): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $texto) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $partes = preg_split('/\s*[:|]\s*/', $linea, 2);
            $out[] = [trim($partes[0]), trim($partes[1] ?? '')];
        }
        return $out;
    }

    public static function especificacionesATexto(array $specs): string
    {
        return implode("\n", array_map(fn($s) => $s[1] !== '' ? "{$s[0]}: {$s[1]}" : $s[0], $specs));
    }

    // ─── ESTADÍSTICAS (dashboard) ─────────────────────────────────────────

    public function estadisticas(): array
    {
        $one = fn(string $sql) => (int) ($this->db->fetchOne($sql)['total'] ?? 0);
        return [
            'productos'   => $one("SELECT COUNT(*) AS total FROM cat_productos WHERE estado = 'published'"),
            'borradores'  => $one("SELECT COUNT(*) AS total FROM cat_productos WHERE estado = 'draft'"),
            'categorias'  => $one('SELECT COUNT(*) AS total FROM cat_categorias WHERE activo = 1'),
            'marcas'      => $one('SELECT COUNT(*) AS total FROM cat_marcas WHERE activo = 1'),
            'cot_nuevas'  => $one("SELECT COUNT(*) AS total FROM cat_cotizaciones WHERE estado = 'nueva'"),
            'cot_mes'     => $one("SELECT COUNT(*) AS total FROM cat_cotizaciones WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
        ];
    }

    // ─── INTERNOS ─────────────────────────────────────────────────────────

    private function upsert(string $tabla, array $cols, ?int $id): int
    {
        $before = $id ? $this->db->fetchOne("SELECT * FROM {$tabla} WHERE id = ?", [$id]) : null;

        if ($id) {
            $set = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($cols)));
            $this->db->execute("UPDATE {$tabla} SET {$set} WHERE id = ?", [...array_values($cols), $id]);
        } else {
            $names = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($cols)));
            $ph    = implode(', ', array_fill(0, count($cols), '?'));
            $this->db->execute("INSERT INTO {$tabla} ({$names}) VALUES ({$ph})", array_values($cols));
            $id = (int) $this->db->lastInsertId();
        }

        $this->categoriasCache = null;
        AuditLogger::log($tabla, (string) $id, $before ? 'update' : 'insert', $before, $cols);
        return $id;
    }

    private function delete(string $tabla, int $id): void
    {
        $before = $this->db->fetchOne("SELECT * FROM {$tabla} WHERE id = ?", [$id]);
        if (!$before) {
            return;
        }
        $this->db->execute("DELETE FROM {$tabla} WHERE id = ?", [$id]);
        $this->categoriasCache = null;
        AuditLogger::log($tabla, (string) $id, 'delete', $before, null);
    }

    public function slugUnico(string $tabla, string $texto, ?int $id = null): string
    {
        $base = self::slugify($texto) ?: 'item';
        $slug = $base;
        $n = 2;
        while ($this->db->fetchOne("SELECT id FROM {$tabla} WHERE slug = ? AND id <> ?", [$slug, (int) $id])) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    public static function slugify(string $text): string
    {
        return ajm_slugify($text);
    }
}
