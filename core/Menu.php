<?php

namespace AJM\Core;

/**
 * Menu — lee menus/menu_items y resuelve la URL final de cada item
 * (enlace directo, o slug de un content_entry vinculado).
 */
class Menu
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Devuelve el árbol de items (con 'children') para el menú indicado por su `key`.
     */
    public function tree(string $menuKey): array
    {
        $menu = $this->db->fetchOne('SELECT * FROM menus WHERE `key` = ?', [$menuKey]);
        if (!$menu) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT mi.*, ce.slug AS entry_slug, ct.route_prefix AS entry_route_prefix
             FROM menu_items mi
             LEFT JOIN content_entries ce ON ce.id = mi.content_entry_id
             LEFT JOIN content_types ct ON ct.id = ce.content_type_id
             WHERE mi.menu_id = ?
             ORDER BY mi.sort_order ASC, mi.id ASC",
            [$menu['id']]
        );

        foreach ($rows as &$row) {
            $row['resolved_url'] = $this->resolveUrl($row);
            $row['children'] = [];
        }
        unset($row);

        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['id']] = $row;
        }

        // Primera pasada: cuelga cada hijo de su padre en $byId.
        foreach ($byId as $row) {
            if ($row['parent_id'] && isset($byId[$row['parent_id']])) {
                $byId[$row['parent_id']]['children'][] = $row;
            }
        }

        // Segunda pasada: arma el árbol releyendo $byId ya actualizado — un foreach
        // normal no ve las mutaciones hechas a $byId durante su propia iteración
        // (itera sobre una copia), así que $tree[] = $row de una sola pasada
        // siempre quedaba con 'children' vacío.
        $tree = [];
        foreach ($byId as $id => $row) {
            if (!$row['parent_id'] || !isset($byId[$row['parent_id']])) {
                $tree[] = $byId[$id];
            }
        }

        return $tree;
    }

    private function resolveUrl(array $item): string
    {
        if ($item['target_type'] === 'content' && $item['entry_slug']) {
            $prefix = $item['entry_route_prefix'] ? '/' . $item['entry_route_prefix'] : '';
            return $prefix . '/' . $item['entry_slug'];
        }
        return $item['url'] ?? '#';
    }
}
