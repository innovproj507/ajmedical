<?php
/**
 * Helpers globales disponibles en todo el proyecto (admin, público, plantillas).
 * Cargado explícitamente desde config/config.php — PSR-4 solo autocarga clases,
 * no funciones sueltas, así que esto NO puede depender del autoloader.
 */

if (!function_exists('e')) {
    /** Escapa para salida HTML. Úsalo en toda plantilla que imprima datos del usuario/BD. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return SITE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('ajm_slugify')) {
    /**
     * "Protección Personal" → "proteccion-personal".
     * Sin iconv('ASCII//TRANSLIT'): en Windows convierte "ó" en "'o" y los slugs salían "protecci-on".
     */
    function ajm_slugify(string $text, string $sep = '-'): string
    {
        $text = strtr(mb_strtolower(trim($text), 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'º' => 'o', 'ª' => 'a', '%' => '',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', $sep, $text);
        return trim($text, $sep);
    }
}

if (!function_exists('setting')) {
    /** Valor de un setting dinámico (constante definida por config.php) o $default si no existe / está vacío. */
    function setting(string $key, mixed $default = ''): mixed
    {
        if (!defined($key)) {
            return $default;
        }
        $value = constant($key);
        return ($value === '' || $value === null) ? $default : $value;
    }
}

if (!function_exists('ajm_icon')) {
    /**
     * Set mínimo de íconos de línea (24x24, stroke-based) usados en el admin.
     * Sin librería de íconos externa — SVG inline, hereda color vía currentColor.
     */
    function ajm_icon(string $name, string $class = 'w-5 h-5'): string
    {
        $paths = [
            'home'          => '<path d="M4 11.5 12 4l8 7.5" /><path d="M6 10v9a1 1 0 0 0 1 1h4v-6h2v6h4a1 1 0 0 0 1-1v-9" />',
            'file-text'     => '<path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M14 3v4h4" /><path d="M9 12.5h6M9 16h6" />',
            'image'         => '<rect x="3.5" y="4.5" width="17" height="15" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="m4 17 5-5 3.5 3.5L17 11l3 3" />',
            'list'          => '<path d="M9 6h11M9 12h11M9 18h11" /><circle cx="4.5" cy="6" r="1.25" /><circle cx="4.5" cy="12" r="1.25" /><circle cx="4.5" cy="18" r="1.25" />',
            'users'         => '<circle cx="9" cy="8" r="3.25" /><path d="M3.5 19.5c0-3 2.5-5 5.5-5s5.5 2 5.5 5" /><path d="M16 8.25a3 3 0 1 1 2.4 4.8" /><path d="M20.5 19.5c0-2.3-1.6-4.1-3.8-4.7" />',
            'settings'      => '<circle cx="12" cy="12" r="3" /><path d="M12 3.5v2.2M12 18.3v2.2M4.6 7.3l1.9 1.1M17.5 15.6l1.9 1.1M4.6 16.7l1.9-1.1M17.5 8.4l1.9-1.1M3.5 12h2.2M18.3 12h2.2" />',
            'clock'         => '<circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" />',
            'search'        => '<circle cx="10.5" cy="10.5" r="6.5" /><path d="m20 20-4.3-4.3" />',
            'bell'          => '<path d="M6 10a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 14 6 10Z" /><path d="M10 18.5a2 2 0 0 0 4 0" />',
            'logout'        => '<path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" />',
            'check-circle'  => '<circle cx="12" cy="12" r="8.5" /><path d="m8.5 12.5 2.5 2.5 5-5.5" />',
            'pencil'        => '<path d="m14.5 4.5 5 5L9 20H4v-5Z" /><path d="m12.5 6.5 5 5" />',
            'layers'        => '<path d="m12 3 8.5 4.75L12 12.5 3.5 7.75Z" /><path d="m3.5 12 8.5 4.75L20.5 12" /><path d="m3.5 16.25 8.5 4.75 8.5-4.75" />',
            'user-check'    => '<circle cx="9.5" cy="8" r="3.25" /><path d="M3.5 19.5c0-3 2.5-5 6-5s6 2 6 5" /><path d="m15.5 11 1.75 1.75L21 9" />',
            'trend-up'      => '<path d="M4 16.5 10 10l3.5 3.5L20 6.5" /><path d="M14.5 6.5H20V12" />',
            'chevron-right' => '<path d="m9 6 6 6-6 6" />',
            'chevron-down'  => '<path d="m6 9 6 6 6-6" />',
            'mail'          => '<rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4.5 7 7.5 6 7.5-6" />',
            'phone'         => '<path d="M8 3.5 6 4.7c-1.3.8-1.8 2.4-1 3.8 1.9 3.4 4.7 6.2 8 8 1.4.8 3 .3 3.8-1l1.2-2-3.6-2.2-.9 1.5c-1.8-.9-3.4-2.5-4.3-4.3l1.5-.9L8 3.5Z" />',
            'map-pin'       => '<path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" />',
            'arrow-up'      => '<path d="M12 19V5M6 11l6-6 6 6" />',
            'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6" />',
            'arrow-up-right'=> '<path d="M7 17 17 7M8 7h9v9" />',
            'sparkle'       => '<path d="M12 3v3.5M12 17.5V21M3 12h3.5M17.5 12H21M5.6 5.6l2.5 2.5M15.9 15.9l2.5 2.5M18.4 5.6l-2.5 2.5M8.1 15.9l-2.5 2.5" />',
            'menu'          => '<path d="M4 7h16M4 12h16M4 17h16" />',
            'box'           => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" />',
            'tag'           => '<path d="M3.5 12.3V4.5a1 1 0 0 1 1-1h7.8l8.2 8.2a1 1 0 0 1 0 1.4l-7.1 7.1a1 1 0 0 1-1.4 0Z" /><circle cx="8" cy="8" r="1.5" />',
            'cart'          => '<path d="M3.5 4.5h2l2.2 10.2a1 1 0 0 0 1 .8h8.6a1 1 0 0 0 1-.8l1.4-6.7H6.4" /><circle cx="9.5" cy="19" r="1.25" /><circle cx="17" cy="19" r="1.25" />',
            'clipboard'     => '<rect x="5.5" y="4.5" width="13" height="16" rx="1.5" /><path d="M9 4.5V3.5h6v1M9 10h6M9 13.5h6M9 17h3.5" />',
            'download'      => '<path d="M12 4v11M7 10.5l5 5 5-5" /><path d="M4.5 19.5h15" />',
            'upload'        => '<path d="M12 16V5M7 9.5l5-5 5 5" /><path d="M4.5 19.5h15" />',
            'printer'       => '<path d="M7 8.5V4h10v4.5" /><rect x="3.5" y="8.5" width="17" height="8" rx="1.5" /><path d="M7 14h10v6H7Z" />',
            'plug'          => '<path d="M9 3.5v4M15 3.5v4M6.5 7.5h11v3a5.5 5.5 0 0 1-11 0Z" /><path d="M12 16v4.5" />',
            'grid'          => '<rect x="4" y="4" width="6.5" height="6.5" rx="1" /><rect x="13.5" y="4" width="6.5" height="6.5" rx="1" /><rect x="4" y="13.5" width="6.5" height="6.5" rx="1" /><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1" />',
            'shield'        => '<path d="M12 3.5 19 6v5.5c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6Z" /><path d="m9 12 2 2 4-4.5" />',
            'truck'         => '<path d="M3.5 6.5h10v9h-10ZM13.5 9.5h4l3 3v3h-7" /><circle cx="7" cy="17.5" r="1.75" /><circle cx="17" cy="17.5" r="1.75" />',
            'headset'       => '<path d="M4.5 14v-2a7.5 7.5 0 0 1 15 0v2" /><rect x="3.5" y="13" width="4" height="6" rx="1.5" /><rect x="16.5" y="13" width="4" height="6" rx="1.5" />',
            'plus'          => '<path d="M12 5v14M5 12h14" />',
            'minus'         => '<path d="M5 12h14" />',
            'x'             => '<path d="m6 6 12 12M18 6 6 18" />',
            'trash'         => '<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13" />',
            'eye'           => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="2.75" />',
            'filter'        => '<path d="M4 5.5h16l-6 7.5v5.5l-4 1.5v-7Z" />',
            'whatsapp'      => '<path d="M12 3.5a8.4 8.4 0 0 0-7.2 12.7L3.5 20.5l4.4-1.3A8.4 8.4 0 1 0 12 3.5Z" /><path d="M9 9c.2-.5.5-.5.8-.5h.5c.2 0 .4 0 .6.4l.7 1.6c.1.2 0 .4-.1.6l-.4.5c-.1.2-.1.3 0 .5.4.7 1.4 1.7 2.1 2.1.2.1.3.1.5 0l.5-.4c.2-.1.4-.2.6-.1l1.6.7c.3.2.3.4.3.6v.5c0 .3 0 .6-.5.8-.9.4-2 .4-3.5-.5-1.5-.9-2.9-2.3-3.8-3.8-.9-1.5-.9-2.6-.5-3.5Z" fill="currentColor" stroke="none" />',
        ];

        $inner = $paths[$name] ?? '';
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}
