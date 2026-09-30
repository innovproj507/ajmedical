<?php

namespace AJM\Core;

use InvalidArgumentException;
use finfo;

/**
 * Media — manejo de archivos subidos (imágenes y PDFs).
 * Valida el MIME real (no la extensión) antes de aceptar cualquier archivo.
 */
class Media
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array $file Entrada cruda de $_FILES['campo']
     */
    public function upload(array $file, string $subdir = 'media'): array
    {
        if (!Security::validateUploadedDocument($file)) {
            throw new InvalidArgumentException('Archivo no permitido (solo PDF, JPG, PNG, WEBP).');
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $ext      = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            default            => throw new InvalidArgumentException('Tipo de archivo no soportado.'),
        };

        $dir = rtrim(UPLOADS_PATH, '/') . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('No se pudo crear el directorio de subida.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new InvalidArgumentException('No se pudo guardar el archivo subido.');
        }

        [$width, $height] = str_starts_with($mimeType, 'image/')
            ? (getimagesize($destination) ?: [null, null])
            : [null, null];

        $relativePath = trim($subdir, '/') . '/' . $filename;

        $this->db->execute(
            "INSERT INTO media (filename, path, mime_type, size, width, height, alt_text, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $file['name'],
                $relativePath,
                $mimeType,
                $file['size'],
                $width,
                $height,
                null,
                Auth::user()['id'] ?? null,
            ]
        );

        $id = (int) $this->db->lastInsertId();
        return $this->find($id);
    }

    /**
     * Registra un archivo que ya está en el servidor (importaciones masivas por CLI, donde
     * move_uploaded_file() no aplica). Las imágenes de más de $maxLado px o $maxBytes se
     * redimensionan/recomprimen para no servir fotos de varios MB en el catálogo.
     */
    public function importFile(string $sourcePath, string $subdir = 'media', ?string $originalName = null, int $maxLado = 1400, int $maxBytes = 400_000): array
    {
        if (!is_file($sourcePath)) {
            throw new InvalidArgumentException("No existe el archivo: {$sourcePath}");
        }
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        $ext = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            default           => throw new InvalidArgumentException('Tipo de archivo no soportado: ' . basename($sourcePath)),
        };

        $dir = rtrim(UPLOADS_PATH, '/') . '/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('No se pudo crear el directorio de subida.');
        }
        $filename    = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $dir . '/' . $filename;

        [$width, $height] = str_starts_with($mimeType, 'image/') ? (@getimagesize($sourcePath) ?: [null, null]) : [null, null];

        $optimizada = false;
        if ($width && (max($width, $height) > $maxLado || filesize($sourcePath) > $maxBytes)) {
            $img = @imagecreatefromstring((string) file_get_contents($sourcePath));
            if ($img) {
                $escala = min(1, $maxLado / max($width, $height));
                $nw = (int) round($width * $escala);
                $nh = (int) round($height * $escala);
                $out = imagecreatetruecolor($nw, $nh);
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
                imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $width, $height);
                $optimizada = match ($ext) {
                    'jpg'  => imagejpeg($out, $destination, 85),
                    'png'  => imagepng($out, $destination, 8),
                    'webp' => imagewebp($out, $destination, 85),
                    default => false,
                };
                [$width, $height] = [$nw, $nh];
            }
        }
        if (!$optimizada && !copy($sourcePath, $destination)) {
            throw new InvalidArgumentException('No se pudo copiar ' . basename($sourcePath));
        }

        $this->db->execute(
            "INSERT INTO media (filename, path, mime_type, size, width, height, alt_text, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $originalName ?? basename($sourcePath),
                trim($subdir, '/') . '/' . $filename,
                $mimeType,
                filesize($destination),
                $width,
                $height,
                null,
                Auth::user()['id'] ?? null,
            ]
        );
        return $this->find((int) $this->db->lastInsertId());
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM media WHERE id = ?', [$id]);
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM media ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function delete(int $id): void
    {
        $item = $this->find($id);
        if (!$item) {
            return;
        }
        $fullPath = rtrim(UPLOADS_PATH, '/') . '/' . $item['path'];
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        $this->db->execute('DELETE FROM media WHERE id = ?', [$id]);
    }

    public static function url(array $media): string
    {
        return SITE_URL . '/uploads/' . ltrim($media['path'], '/');
    }
}
