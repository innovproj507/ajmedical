<?php
/**
 * Definición de los tipos de campo soportados por el editor de contenido.
 * Los tipos de contenido en sí (página, noticia, documento, ...) y sus
 * `fields_schema` viven en la tabla `content_types` (ver database/ajmedical_cms.sql)
 * — este archivo solo describe CÓMO se renderiza/valida cada `type` de campo
 * dentro de ese JSON schema, para admin/content/edit.php y
 * templates/admin/field-renderer.php.
 */

return [
    'text' => [
        'input' => 'text',
    ],
    'textarea' => [
        'input' => 'textarea',
    ],
    'richtext' => [
        'input' => 'textarea',
        'class' => 'js-richtext',
    ],
    'media' => [
        'input' => 'media-picker',
    ],
    'date' => [
        'input' => 'date',
    ],
    'number' => [
        'input' => 'number',
    ],
    'boolean' => [
        'input' => 'checkbox',
    ],
    'select' => [
        'input' => 'select',
    ],
];
