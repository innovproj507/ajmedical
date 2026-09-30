<?php

namespace AJM\Core;

use InvalidArgumentException;

/**
 * Repository — CRUD genérico sobre content_entries, validado contra
 * el fields_schema declarado en content_types. Es la pieza que hace que
 * "agregar una sección al sitio" sea una fila de datos, no código nuevo.
 */
class Repository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ─── TIPOS DE CONTENIDO ───────────────────────────────────────────────

    public function getContentType(string $key): ?array
    {
        $type = $this->db->fetchOne('SELECT * FROM content_types WHERE `key` = ?', [$key]);
        if ($type) {
            $type['fields_schema'] = json_decode($type['fields_schema'], true) ?? [];
        }
        return $type;
    }

    public function getContentTypeById(int $id): ?array
    {
        $type = $this->db->fetchOne('SELECT * FROM content_types WHERE id = ?', [$id]);
        if ($type) {
            $type['fields_schema'] = json_decode($type['fields_schema'], true) ?? [];
        }
        return $type;
    }

    public function listContentTypes(): array
    {
        $types = $this->db->fetchAll('SELECT * FROM content_types ORDER BY label');
        foreach ($types as &$type) {
            $type['fields_schema'] = json_decode($type['fields_schema'], true) ?? [];
        }
        return $types;
    }

    // ─── LECTURA DE ENTRADAS ──────────────────────────────────────────────

    /**
     * @param array{status?:string,limit?:int,offset?:int,order?:string} $opts
     */
    public function listEntries(string $typeKey, array $opts = []): array
    {
        $type = $this->getContentType($typeKey);
        if (!$type) {
            throw new InvalidArgumentException("Tipo de contenido desconocido: {$typeKey}");
        }

        $where  = ['content_type_id = ?'];
        $params = [$type['id']];

        if (!empty($opts['status'])) {
            $where[] = 'status = ?';
            $params[] = $opts['status'];
        }

        if (!empty($opts['search'])) {
            $where[] = '(title LIKE ? OR excerpt LIKE ?)';
            $like = '%' . $opts['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $order  = $opts['order'] ?? 'published_at DESC, created_at DESC';
        $limit  = (int) ($opts['limit'] ?? 20);
        $offset = (int) ($opts['offset'] ?? 0);

        $sql = 'SELECT * FROM content_entries WHERE ' . implode(' AND ', $where)
             . " ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'decodeEntry'], $rows);
    }

    public function countEntries(string $typeKey, array $opts = []): int
    {
        $type = $this->getContentType($typeKey);
        if (!$type) {
            return 0;
        }

        $where  = ['content_type_id = ?'];
        $params = [$type['id']];

        if (!empty($opts['status'])) {
            $where[] = 'status = ?';
            $params[] = $opts['status'];
        }

        if (!empty($opts['search'])) {
            $where[] = '(title LIKE ? OR excerpt LIKE ?)';
            $like = '%' . $opts['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM content_entries WHERE ' . implode(' AND ', $where),
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    public function findBySlug(string $typeKey, string $slug): ?array
    {
        $type = $this->getContentType($typeKey);
        if (!$type) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT * FROM content_entries WHERE content_type_id = ? AND slug = ? LIMIT 1',
            [$type['id'], $slug]
        );
        return $row ? $this->decodeEntry($row) : null;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM content_entries WHERE id = ?', [$id]);
        return $row ? $this->decodeEntry($row) : null;
    }

    private function decodeEntry(array $row): array
    {
        $row['data'] = json_decode($row['data'] ?? '{}', true) ?? [];
        return $row;
    }

    /** Datos de la extensión relacional `events` para un content_entry de tipo 'evento'. */
    public function getEventMeta(int $entryId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM events WHERE entry_id = ?', [$entryId]);
    }

    // ─── ESCRITURA (validada + auditada) ──────────────────────────────────

    /**
     * @param array $input Debe incluir: content_type_id, slug, title, status, data[], ...
     */
    public function save(array $input, ?int $id = null): int
    {
        $type = $this->getContentTypeById((int) $input['content_type_id']);
        if (!$type) {
            throw new InvalidArgumentException('Tipo de contenido inválido.');
        }

        $data = $this->validateFields($type['fields_schema'], $input['data'] ?? []);

        $before = $id ? $this->findById($id) : null;

        $columns = [
            'content_type_id'   => $type['id'],
            'slug'              => $this->slugify($input['slug'] ?? $input['title']),
            'title'             => trim($input['title']),
            'excerpt'           => $input['excerpt'] ?? null,
            'status'            => $input['status'] ?? 'draft',
            'published_at'      => $input['published_at'] ?? ($input['status'] === 'published' ? date('Y-m-d H:i:s') : null),
            'author_id'         => $input['author_id'] ?? (Auth::user()['id'] ?? null),
            'featured_image_id' => $input['featured_image_id'] ?? null,
            'data'              => json_encode($data, JSON_UNESCAPED_UNICODE),
            'seo_title'         => $input['seo_title'] ?? null,
            'seo_description'   => $input['seo_description'] ?? null,
        ];

        if ($id) {
            $set = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($columns)));
            $this->db->execute(
                "UPDATE content_entries SET {$set} WHERE id = ?",
                [...array_values($columns), $id]
            );
        } else {
            $cols = implode(', ', array_keys($columns));
            $ph   = implode(', ', array_fill(0, count($columns), '?'));
            $this->db->execute(
                "INSERT INTO content_entries ({$cols}) VALUES ({$ph})",
                array_values($columns)
            );
            $id = (int) $this->db->lastInsertId();
        }

        AuditLogger::log('content_entries', (string) $id, $before ? 'update' : 'insert', $before, $this->findById($id));

        return $id;
    }

    public function delete(int $id): void
    {
        $before = $this->findById($id);
        if (!$before) {
            return;
        }
        $this->db->execute('DELETE FROM content_entries WHERE id = ?', [$id]);
        AuditLogger::log('content_entries', (string) $id, 'delete', $before, null);
    }

    // ─── VALIDACIÓN ───────────────────────────────────────────────────────

    private function validateFields(array $schema, array $data): array
    {
        $clean = [];
        foreach ($schema as $field) {
            $name  = $field['name'];
            $value = $data[$name] ?? null;

            if (!empty($field['required']) && ($value === null || $value === '')) {
                throw new InvalidArgumentException("El campo '{$field['label']}' es obligatorio.");
            }

            $clean[$name] = match ($field['type']) {
                'boolean' => (bool) $value,
                'number'  => $value !== null && $value !== '' ? (float) $value : null,
                'media'   => $value !== null ? (int) $value : null,
                default   => $value,
            };
        }
        return $clean;
    }

    private function slugify(string $text): string
    {
        return ajm_slugify($text);
    }
}
