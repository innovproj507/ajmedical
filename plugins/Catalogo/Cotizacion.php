<?php

namespace AJM\Plugins\Catalogo;

use AJM\Core\Database;
use AJM\Core\Mailer;
use AJM\Core\Security;
use InvalidArgumentException;

/**
 * Cotizacion — "carrito" de solicitud de cotización.
 * Los productos elegidos viven en la sesión ([producto_id => cantidad]) hasta que el
 * visitante envía el formulario; entonces se guarda en cat_cotizaciones y se notifica por correo.
 */
class Cotizacion
{
    private const SESSION_KEY = 'ajm_cotizacion';
    private const MAX_CANTIDAD = 99999;

    /** Solo abre la sesión si el visitante ya tiene una — no crea cookies a cada visita del sitio. */
    private static function session(bool $crear): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (!$crear && empty($_COOKIE[session_name()])) {
            return false;
        }
        if (headers_sent()) {
            return false;
        }
        session_start();
        return true;
    }

    /** @return array<int,int> */
    public static function raw(): array
    {
        if (!self::session(false)) {
            return [];
        }
        return array_map('intval', $_SESSION[self::SESSION_KEY] ?? []);
    }

    public static function count(): int
    {
        return count(self::raw());
    }

    public static function agregar(int $productoId, int $cantidad = 1): void
    {
        self::session(true);
        $actual = (int) ($_SESSION[self::SESSION_KEY][$productoId] ?? 0);
        $_SESSION[self::SESSION_KEY][$productoId] = min(self::MAX_CANTIDAD, $actual + max(1, $cantidad));
    }

    public static function actualizar(int $productoId, int $cantidad): void
    {
        self::session(true);
        if ($cantidad <= 0) {
            unset($_SESSION[self::SESSION_KEY][$productoId]);
            return;
        }
        $_SESSION[self::SESSION_KEY][$productoId] = min(self::MAX_CANTIDAD, $cantidad);
    }

    public static function vaciar(): void
    {
        if (self::session(false)) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    /** Líneas de la cotización con los datos del producto (descarta productos ya despublicados). */
    public static function items(): array
    {
        $raw = self::raw();
        $productos = CatalogoRepository::instance()->productosPorIds(array_keys($raw));
        $items = [];
        foreach ($raw as $id => $cantidad) {
            if (isset($productos[$id])) {
                $items[] = ['producto' => $productos[$id], 'cantidad' => $cantidad];
            }
        }
        return $items;
    }

    /**
     * Guarda la solicitud y notifica al administrador. Devuelve el código de la cotización.
     * @param array{nombre:string,empresa?:string,correo:string,telefono?:string,mensaje?:string} $datos
     */
    public static function enviar(array $datos): string
    {
        // Un cliente mal configurado puede mandar texto que no es UTF-8; MySQL lo rechazaría.
        $datos = array_map(fn($v) => is_string($v) && !mb_check_encoding($v, 'UTF-8') ? mb_convert_encoding($v, 'UTF-8', 'Windows-1252') : $v, $datos);

        $items = self::items();
        if (!$items) {
            throw new InvalidArgumentException('Tu cotización está vacía. Agrega productos desde el catálogo.');
        }
        $nombre = trim($datos['nombre'] ?? '');
        $correo = trim($datos['correo'] ?? '');
        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Indica tu nombre y un correo válido.');
        }

        $lineas = array_map(fn($i) => [
            'id'           => (int) $i['producto']['id'],
            'sku'          => $i['producto']['sku'],
            'nombre'       => $i['producto']['nombre'],
            'presentacion' => $i['producto']['presentacion'],
            'cantidad'     => $i['cantidad'],
            'precio'       => $i['producto']['precio'] !== null ? (float) ($i['producto']['precio_oferta'] ?? $i['producto']['precio']) : null,
        ], $items);

        $codigo = 'COT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO cat_cotizaciones (codigo, nombre, empresa, correo, telefono, mensaje, items, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $codigo,
                mb_substr($nombre, 0, 255),
                mb_substr(trim($datos['empresa'] ?? ''), 0, 255) ?: null,
                $correo,
                mb_substr(trim($datos['telefono'] ?? ''), 0, 40) ?: null,
                trim($datos['mensaje'] ?? '') ?: null,
                json_encode($lineas, JSON_UNESCAPED_UNICODE),
                Security::getClientIP(),
            ]
        );

        $tabla = self::tablaHtml($lineas);
        $datosHtml = '<p><strong>Código:</strong> ' . e($codigo) . '</p>'
            . '<p><strong>Nombre:</strong> ' . e($nombre) . '<br>'
            . '<strong>Empresa:</strong> ' . e($datos['empresa'] ?? '') . '<br>'
            . '<strong>Correo:</strong> ' . e($correo) . '<br>'
            . '<strong>Teléfono:</strong> ' . e($datos['telefono'] ?? '') . '</p>'
            . (!empty($datos['mensaje']) ? '<p><strong>Mensaje:</strong><br>' . nl2br(e($datos['mensaje'])) . '</p>' : '');

        Mailer::notifyAdmin("Nueva solicitud de cotización {$codigo}", $datosHtml . $tabla
            . '<p><a href="' . e(site_url('/admin/plugin.php?p=catalogo&page=cotizaciones&id=' . $db->lastInsertId())) . '">Ver en el panel</a></p>');

        Mailer::send($correo, $nombre, 'Recibimos tu solicitud de cotización ' . $codigo,
            '<p>Hola ' . e($nombre) . ',</p><p>Gracias por escribirnos. Recibimos tu solicitud de cotización <strong>' . e($codigo)
            . '</strong> y te responderemos a la brevedad.</p>' . $tabla . '<p>— ' . e(SITE_NAME) . '</p>');

        self::vaciar();
        return $codigo;
    }

    /** Texto prellenado para enviar la cotización por WhatsApp. */
    public static function textoWhatsapp(array $items): string
    {
        $lineas = ['Hola ' . SITE_NAME . ', quisiera cotizar:'];
        foreach ($items as $i) {
            $p = $i['producto'];
            $lineas[] = '• ' . $i['cantidad'] . ' x ' . $p['nombre'] . ($p['sku'] ? " ({$p['sku']})" : '');
        }
        return implode("\n", $lineas);
    }

    private static function tablaHtml(array $lineas): string
    {
        $rows = '';
        foreach ($lineas as $l) {
            $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee">' . e($l['sku'] ?? '') . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . e($l['nombre']) . ($l['presentacion'] ? '<br><small style="color:#777">' . e($l['presentacion']) . '</small>' : '') . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right">' . (int) $l['cantidad'] . '</td></tr>';
        }
        return '<table style="border-collapse:collapse;width:100%;font-family:sans-serif;font-size:14px">'
            . '<thead><tr style="background:#326666;color:#fff"><th style="padding:6px 10px;text-align:left">Código</th>'
            . '<th style="padding:6px 10px;text-align:left">Producto</th><th style="padding:6px 10px;text-align:right">Cant.</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }
}
