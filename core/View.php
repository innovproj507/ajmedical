<?php

namespace AJM\Core;

/**
 * View — render de plantillas PHP planas (sin motor de terceros).
 * Las plantillas reciben las variables de $data como variables locales
 * y pueden usar la función global e() para escapar salida HTML.
 */
class View
{
    public static function render(string $templatePath, array $data = []): string
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException("Plantilla no encontrada: {$templatePath}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    public static function display(string $templatePath, array $data = []): void
    {
        echo self::render($templatePath, $data);
    }

    /** Envuelve $content dentro de una plantilla de layout bajo la variable $slot. */
    public static function renderWithLayout(string $layoutPath, string $contentTemplatePath, array $data = []): string
    {
        $content = self::render($contentTemplatePath, $data);
        return self::render($layoutPath, array_merge($data, ['slot' => $content]));
    }
}
