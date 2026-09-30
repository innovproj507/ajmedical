<?php
/**
 * Plugin: Catálogo virtual de productos + solicitud de cotización.
 * Manifiesto leído por core/Plugins.php (ver ahí el formato).
 */

use AJM\Core\Database;
use AJM\Core\Router;
use AJM\Core\Security;
use AJM\Core\View;
use AJM\Plugins\Catalogo\CatalogoRepository;
use AJM\Plugins\Catalogo\Cotizacion;

$tpl = __DIR__ . '/templates';

return [
    'key'         => 'catalogo',
    'name'        => 'Catálogo virtual',
    'version'     => '1.1.0',
    'description' => 'Catálogo de productos con categorías, marcas, fichas técnicas, precios opcionales, '
                   . 'solicitud de cotización (correo + WhatsApp), catálogo imprimible en PDF e importación CSV.',
    'install'     => __DIR__ . '/install.sql',
    // Cambios de esquema para instalaciones existentes (install.sql ya trae la versión final).
    'migrations'  => [
        '1.1.0' => __DIR__ . '/migrations/1.1.0.sql',
    ],

    // ─── Rutas públicas ───────────────────────────────────────────────────
    'routes' => function (Router $router, array $ctx) use ($tpl) {
        $renderPage = $ctx['renderPage'];
        $notFound   = $ctx['notFound'];
        $repo       = CatalogoRepository::instance();

        $listado = function (array $base, array $meta) use ($repo, $renderPage, $tpl) {
            $perPage = max(1, (int) setting('CATALOGO_POR_PAGINA', 12));
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $filtros = $base + [
                'q'              => trim((string) ($_GET['q'] ?? '')),
                'disponibilidad' => (string) ($_GET['disponibilidad'] ?? ''),
                'orden'          => array_key_exists($_GET['orden'] ?? '', CatalogoRepository::ORDENES) ? $_GET['orden'] : 'relevancia',
            ];
            if (empty($filtros['marca_id']) && !empty($_GET['marca'])) {
                $marca = $repo->marcaPorSlug((string) $_GET['marca']);
                $filtros['marca_id'] = $marca['id'] ?? -1;
                $meta['marca'] = $marca;
            }

            $total = $repo->contarProductos($filtros);
            $renderPage($tpl . '/catalogo-list.php', $meta + [
                'productos'   => $repo->buscarProductos($filtros, $perPage, ($page - 1) * $perPage),
                'total'       => $total,
                'page'        => $page,
                'totalPages'  => (int) max(1, ceil($total / $perPage)),
                'filtros'     => $filtros,
                'arbol'       => $repo->categoriasArbol(),
                'marcas'      => $repo->marcas(),
                'csrfToken'   => Security::generateCSRF(),
            ], $meta['pageTitle'], $meta['seoDescription'] ?? (string) setting('CATALOGO_INTRO'));
        };

        $router->get('/catalogo', function () use ($listado) {
            $listado([], [
                'pageTitle' => (string) setting('CATALOGO_TITULO', 'Catálogo de productos'),
                'categoria' => null,
            ]);
        });

        $router->get('/catalogo/categoria/{slug}', function (array $params) use ($repo, $listado, $notFound) {
            $categoria = $repo->categoriaPorSlug($params['slug']);
            if (!$categoria) {
                $notFound('Categoría no encontrada');
                return;
            }
            $listado(['categoria_id' => (int) $categoria['id']], [
                'pageTitle'      => $categoria['nombre'],
                'seoDescription' => $categoria['descripcion'] ?: null,
                'categoria'      => $categoria,
                'ruta'           => $repo->rutaCategoria((int) $categoria['id']),
            ]);
        });

        $router->get('/catalogo/producto/{slug}', function (array $params) use ($repo, $renderPage, $notFound, $tpl) {
            $producto = $repo->productoPorSlug($params['slug']);
            if (!$producto) {
                $notFound('Producto no encontrado');
                return;
            }
            $renderPage($tpl . '/producto-detail.php', [
                'producto'     => $producto,
                'imagenes'     => $repo->imagenes($producto),
                'ruta'         => $repo->rutaCategoria($producto['categoria_id'] ? (int) $producto['categoria_id'] : null),
                'relacionados' => $repo->relacionados($producto),
                'enCotizacion' => Cotizacion::raw()[(int) $producto['id']] ?? 0,
                'csrfToken'    => Security::generateCSRF(),
            ], $producto['seo_title'] ?: $producto['nombre'], $producto['seo_description'] ?: $producto['resumen']);
        });

        // ── Cotización ──
        $router->get('/catalogo/cotizacion', function () use ($renderPage, $tpl) {
            $renderPage($tpl . '/cotizacion.php', [
                'items'     => Cotizacion::items(),
                'csrfToken' => Security::generateCSRF(),
                'enviada'   => $_SESSION['ajm_cotizacion_enviada'] ?? null,
                'error'     => $_GET['error'] ?? null,
            ], 'Mi cotización');
            unset($_SESSION['ajm_cotizacion_enviada']);
        });

        $router->post('/catalogo/cotizacion/agregar', function () use ($repo) {
            $ajax = ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
            if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
                $ajax ? Security::jsonResponse(false, 'Sesión expirada, recarga la página.') : header('Location: /catalogo');
                exit;
            }
            $id = (int) ($_POST['producto_id'] ?? 0);
            if ($repo->productosPorIds([$id])) {
                Cotizacion::agregar($id, (int) ($_POST['cantidad'] ?? 1));
            }
            if ($ajax) {
                Security::jsonResponse(true, 'Producto agregado a tu cotización.', ['count' => Cotizacion::count()]);
            }
            $back = $_POST['back'] ?? '/catalogo/cotizacion';
            header('Location: ' . (str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : '/catalogo/cotizacion'));
            exit;
        });

        $router->post('/catalogo/cotizacion/actualizar', function () {
            if (Security::validateCSRF($_POST['csrf_token'] ?? '')) {
                if (isset($_POST['quitar'])) {
                    Cotizacion::actualizar((int) $_POST['quitar'], 0);
                } elseif (isset($_POST['vaciar'])) {
                    Cotizacion::vaciar();
                } else {
                    foreach ((array) ($_POST['cantidad'] ?? []) as $id => $cantidad) {
                        Cotizacion::actualizar((int) $id, (int) $cantidad);
                    }
                }
            }
            header('Location: /catalogo/cotizacion');
            exit;
        });

        $router->post('/catalogo/cotizacion/enviar', function () {
            // Honeypot + CSRF + límite de envíos por IP.
            if (trim($_POST['website'] ?? '') !== '') {
                header('Location: /catalogo/cotizacion');
                exit;
            }
            if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
                header('Location: /catalogo/cotizacion?error=' . urlencode('Tu sesión expiró, intenta de nuevo.'));
                exit;
            }
            if (!Security::checkRateLimit('cotizacion', 5, 3600)) {
                header('Location: /catalogo/cotizacion?error=' . urlencode('Has enviado varias solicitudes seguidas. Intenta más tarde o escríbenos por WhatsApp.'));
                exit;
            }
            try {
                $codigo = Cotizacion::enviar($_POST);
                $_SESSION['ajm_cotizacion_enviada'] = $codigo;
                header('Location: /catalogo/cotizacion');
            } catch (\InvalidArgumentException $e) {
                header('Location: /catalogo/cotizacion?error=' . urlencode($e->getMessage()));
            }
            exit;
        });

        // ── Catálogo imprimible (Guardar como PDF desde el navegador) ──
        $router->get('/catalogo/imprimir', function () use ($repo, $tpl) {
            $categoria = !empty($_GET['categoria']) ? $repo->categoriaPorSlug((string) $_GET['categoria']) : null;
            $filtros   = $categoria ? ['categoria_id' => (int) $categoria['id']] : [];
            View::display($tpl . '/imprimir.php', [
                'categoria' => $categoria,
                'arbol'     => $repo->categoriasArbol(),
                'productos' => $repo->buscarProductos($filtros + ['orden' => 'nombre'], 5000),
            ]);
        });
    },

    // ─── Admin ────────────────────────────────────────────────────────────
    'admin_nav' => [
        [
            'key'      => 'catalogo',
            'label'    => 'Catálogo',
            'href'     => '/admin/plugin.php?p=catalogo&page=productos',
            'icon'     => 'box',
            'min_role' => 'viewer',
            'pages'    => ['productos', 'producto', 'categorias', 'marcas', 'importar'],
        ],
        [
            'key'      => 'cotizaciones',
            'label'    => 'Cotizaciones',
            'href'     => '/admin/plugin.php?p=catalogo&page=cotizaciones',
            'icon'     => 'clipboard',
            'min_role' => 'viewer',
            'pages'    => ['cotizaciones'],
            'badge'    => fn() => (int) (Database::getInstance()->fetchOne("SELECT COUNT(*) AS total FROM cat_cotizaciones WHERE estado = 'nueva'")['total'] ?? 0),
        ],
    ],
    'admin_pages' => [
        'productos'    => __DIR__ . '/admin/productos.php',
        'producto'     => __DIR__ . '/admin/producto-editar.php',
        'categorias'   => __DIR__ . '/admin/categorias.php',
        'marcas'       => __DIR__ . '/admin/marcas.php',
        'importar'     => __DIR__ . '/admin/importar.php',
        'cotizaciones' => __DIR__ . '/admin/cotizaciones.php',
    ],

    'dashboard' => function (): array {
        $s = CatalogoRepository::instance()->estadisticas();
        return [
            ['label' => 'Productos en catálogo', 'value' => $s['productos'],  'hint' => "{$s['categorias']} categorías · {$s['marcas']} marcas", 'icon' => 'box',       'tone' => 'azul',    'href' => '/admin/plugin.php?p=catalogo&page=productos'],
            ['label' => 'Cotizaciones nuevas',   'value' => $s['cot_nuevas'], 'hint' => "{$s['cot_mes']} recibidas este mes",                    'icon' => 'clipboard', 'tone' => 'naranja', 'href' => '/admin/plugin.php?p=catalogo&page=cotizaciones'],
        ];
    },
];
