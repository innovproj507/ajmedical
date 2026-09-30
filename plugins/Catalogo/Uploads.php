<?php

namespace AJM\Plugins\Catalogo;

use AJM\Core\Media;
use InvalidArgumentException;

/**
 * Subidas directas desde los formularios del catálogo (imágenes de producto, ficha técnica,
 * logo de marca...) sin pasar por la pantalla de Media. Todo termina igualmente en la tabla
 * `media` vía Media::upload(), que valida el MIME real.
 */
class Uploads
{
    /** @return int[] IDs de media creados (ignora los inputs vacíos). */
    public static function multiple(?array $files, string $subdir = 'catalogo', ?string $soloTipo = 'image/'): array
    {
        if (!$files || !is_array($files['name'] ?? null)) {
            return $files ? array_filter([self::single($files, $subdir, $soloTipo)]) : [];
        }
        $ids = [];
        foreach (array_keys($files['name']) as $i) {
            $id = self::single([
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ], $subdir, $soloTipo);
            if ($id) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public static function single(?array $file, string $subdir = 'catalogo', ?string $soloTipo = null): ?int
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo subir "' . ($file['name'] ?? '') . '" (¿archivo demasiado grande?).');
        }
        if ($soloTipo !== null) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!str_starts_with((string) $mime, $soloTipo)) {
                throw new InvalidArgumentException('"' . $file['name'] . '" no es del tipo esperado (' . ($soloTipo === 'image/' ? 'imagen JPG, PNG o WEBP' : 'PDF') . ').');
            }
        }
        $media = (new Media())->upload($file, $subdir);
        return (int) $media['id'];
    }
}
