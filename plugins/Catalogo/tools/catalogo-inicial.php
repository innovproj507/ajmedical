<?php
/**
 * Mapa del primer lote de imágenes (carpeta imagenes/) → datos del producto.
 * Clave = nombre del archivo sin número ni extensión (se compara sin tildes ni mayúsculas).
 * Usado por: php plugins/Catalogo/tools/importar-imagenes.php imagenes --mapa=plugins/Catalogo/tools/catalogo-inicial.php --crear
 */
return [
    // Protección personal
    'Bata de aislamiento reutilizable tipo C' => ['nombre' => 'Bata de aislamiento reutilizable tipo C', 'categoria' => 'Protección personal', 'destacado' => 1],
    'Respirador facial'                       => ['nombre' => 'Respirador facial',                       'categoria' => 'Protección personal', 'destacado' => 1],
    'set de ropa'                             => ['nombre' => 'Set de ropa quirúrgica',                  'categoria' => 'Protección personal'],
    'set de ropa desechable'                  => ['nombre' => 'Set de ropa quirúrgica desechable',       'categoria' => 'Protección personal'],

    // Descartables
    'Jeringuillas Huafu'                      => ['nombre' => 'Jeringuillas Huafu',        'categoria' => 'Descartables', 'marca' => 'Huafu', 'destacado' => 1],
    'Jeringuillas Huafu 20 ml'                => ['nombre' => 'Jeringuilla Huafu 20 ml',   'categoria' => 'Descartables', 'marca' => 'Huafu'],
    'Cateter'                                 => ['nombre' => 'Catéter intravenoso',       'categoria' => 'Descartables', 'destacado' => 1],
    'Llave de 3 vías kenno'                   => ['nombre' => 'Llave de 3 vías Kenno',     'categoria' => 'Descartables', 'marca' => 'Kenno'],
    'bolsa de orine para adulto'              => ['nombre' => 'Bolsa de orina para adulto', 'categoria' => 'Descartables', 'destacado' => 1],
    'tubo de alimentación'                    => ['nombre' => 'Tubo de alimentación',      'categoria' => 'Descartables'],

    // Vía aérea
    'nasofaringea'                            => ['nombre' => 'Cánula nasofaríngea',       'categoria' => 'Vía aérea'],
    'orofaringea'                             => ['nombre' => 'Cánula orofaríngea',        'categoria' => 'Vía aérea'],

    // Curaciones
    'Gasa 4x4 con 16 dobleces'                => ['nombre' => 'Gasa 4x4 con 16 dobleces',  'categoria' => 'Curaciones', 'destacado' => 1],
    'Gasa 8x4 con 12 dobleces'                => ['nombre' => 'Gasa 8x4 con 12 dobleces',  'categoria' => 'Curaciones'],
    'esparadrapo'                             => ['nombre' => 'Esparadrapo',               'categoria' => 'Curaciones'],
    'hidrocoloide'                            => ['nombre' => 'Apósito hidrocoloide',      'categoria' => 'Curaciones', 'destacado' => 1],

    // Ortopedia e inmovilización
    'ferula en rollo'                         => ['nombre' => 'Férula en rollo',                   'categoria' => 'Ortopedia e inmovilización'],
    'ferula sintetica en rollo'               => ['nombre' => 'Férula sintética en rollo',         'categoria' => 'Ortopedia e inmovilización'],
    'venda de yeso de poliester Senolo'       => ['nombre' => 'Venda de yeso de poliéster Senolo', 'categoria' => 'Ortopedia e inmovilización', 'marca' => 'Senolo', 'destacado' => 1],

    // Sets quirúrgicos (paquetes de campos/ropa quirúrgica por procedimiento)
    'dilatación y curetaje'                   => ['nombre' => 'Set quirúrgico de dilatación y curetaje', 'categoria' => 'Sets quirúrgicos'],
    'laparatomia'                             => ['nombre' => 'Set quirúrgico de laparotomía',          'categoria' => 'Sets quirúrgicos'],
    'Neurocirugía'                            => ['nombre' => 'Set quirúrgico de neurocirugía',         'categoria' => 'Sets quirúrgicos'],
    'oftalmología'                            => ['nombre' => 'Set quirúrgico de oftalmología',         'categoria' => 'Sets quirúrgicos'],
];
