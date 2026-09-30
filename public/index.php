<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Mailer;
use AJM\Core\Plugins;
use AJM\Core\Repository;
use AJM\Core\Router;
use AJM\Core\View;

$repo      = new Repository();
$templates = ROOT_PATH . '/templates/public';
$method    = $_SERVER['REQUEST_METHOD'];
$requestPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';

// Modo mantenimiento (setting MAINTENANCE_MODE): el público ve la página de mantenimiento;
// un administrador con sesión iniciada navega el sitio normal para revisarlo antes de abrirlo.
if (setting('MAINTENANCE_MODE', false) === true && !Auth::check()) {
    http_response_code(503);
    header('Retry-After: 3600');
    View::display($templates . '/maintenance.php');
    exit;
}

// Redirecciones (protección de SEO al cambiar formatos de URL — ver database/ajmedical_cms.sql)
if ($method === 'GET') {
    $redirect = Database::getInstance()->fetchOne('SELECT * FROM redirects WHERE from_path = ?', [$requestPath]);
    if ($redirect) {
        http_response_code((int) $redirect['status_code']);
        header('Location: ' . $redirect['to_path']);
        exit;
    }
}

$renderPage = function (string $template, array $data, ?string $pageTitle = null, ?string $seoDescription = null) use ($templates) {
    // Los plugins pasan rutas absolutas a sus propias plantillas; el core, nombres relativos.
    $templatePath = str_ends_with($template, '.php') ? $template : $templates . '/' . $template . '.php';
    View::display($templates . '/layout.php', [
        'pageTitle'      => $pageTitle,
        'seoDescription' => $seoDescription,
        'content'        => View::render($templatePath, $data),
    ]);
};

$notFound = function (string $message = 'Página no encontrada') use ($renderPage) {
    http_response_code(404);
    $renderPage('404', ['message' => $message], 'No encontrado');
};

$router = new Router();

$router->get('/', function () use ($repo, $renderPage) {
    $home = $repo->findBySlug('pagina', 'inicio');
    $renderPage('home', ['entry' => $home], null, $home['seo_description'] ?? setting('HERO_SUBTITLE'));
});

$router->get('/noticias', function () use ($repo, $renderPage) {
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 9;
    $search  = trim((string) ($_GET['q'] ?? ''));

    $opts = ['status' => 'published', 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
    if ($search !== '') {
        $opts['search'] = $search;
    }

    $entries = $repo->listEntries('noticia', $opts);
    $total   = $repo->countEntries('noticia', $search !== '' ? ['status' => 'published', 'search' => $search] : ['status' => 'published']);
    $recent  = $repo->listEntries('noticia', ['status' => 'published', 'limit' => 4]);

    $renderPage('noticias-list', [
        'entries'      => $entries,
        'recent'       => $recent,
        'search'       => $search,
        'page'         => $page,
        'totalPages'   => (int) max(1, ceil($total / $perPage)),
        'bannerImage'  => '',
        'bannerTitle'  => 'Noticias',
        'breadcrumb'   => [['label' => 'Inicio', 'href' => '/'], ['label' => 'Noticias']],
    ], 'Noticias');
});

$router->get('/noticias/{slug}', function (array $params) use ($repo, $renderPage, $notFound) {
    $entry = $repo->findBySlug('noticia', $params['slug']);
    if (!$entry || $entry['status'] !== 'published') {
        $notFound('Noticia no encontrada');
        return;
    }
    $recent = $repo->listEntries('noticia', ['status' => 'published', 'limit' => 4]);
    $renderPage('noticias-detail', ['entry' => $entry, 'recent' => $recent], $entry['seo_title'] ?: $entry['title'], $entry['seo_description'] ?: $entry['excerpt']);
});

$router->get('/contacto', function () use ($repo, $renderPage) {
    $entry = $repo->findBySlug('pagina', 'contacto');
    $renderPage('contacto', ['entry' => $entry], 'Contacto', $entry['seo_description'] ?? null);
});

// Formulario de contacto — reenvía el mensaje al correo del admin. Campo "website" es
// un honeypot (los bots suelen rellenarlo; los humanos nunca lo ven, está oculto por CSS).
$router->post('/contacto', function () {
    if (trim($_POST['website'] ?? '') !== '') {
        header('Location: /contacto?enviado=1');
        exit;
    }

    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $asunto   = trim($_POST['asunto'] ?? '');
    $mensaje  = trim($_POST['mensaje'] ?? '');

    if ($nombre === '' || $correo === '' || $mensaje === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: /contacto?error=1');
        exit;
    }

    $body = '<p><strong>Nombre:</strong> ' . htmlspecialchars($nombre) . '</p>'
        . '<p><strong>Correo:</strong> ' . htmlspecialchars($correo) . '</p>'
        . '<p><strong>Teléfono:</strong> ' . htmlspecialchars($telefono) . '</p>'
        . '<p><strong>Asunto:</strong> ' . htmlspecialchars($asunto) . '</p>'
        . '<p><strong>Mensaje:</strong><br>' . nl2br(htmlspecialchars($mensaje)) . '</p>';

    Mailer::notifyAdmin('Nuevo mensaje de contacto: ' . ($asunto !== '' ? $asunto : 'Sin asunto'), $body);

    header('Location: /contacto?enviado=1');
    exit;
});

// Rutas de los plugins activos (catálogo, ...) — antes del catch-all de páginas.
Plugins::registerRoutes($router, [
    'renderPage' => $renderPage,
    'notFound'   => $notFound,
    'templates'  => $templates,
]);

// Catch-all: páginas institucionales por slug (nosotros, políticas, ...)
$router->get('/{slug}', function (array $params) use ($repo, $renderPage, $notFound) {
    $entry = $repo->findBySlug('pagina', $params['slug']);
    if (!$entry || $entry['status'] !== 'published' || $entry['slug'] === 'inicio') {
        $notFound();
        return;
    }
    $renderPage('pagina', ['entry' => $entry], $entry['seo_title'] ?: $entry['title'], $entry['seo_description'] ?: $entry['excerpt']);
});

$router->dispatch($method, $_SERVER['REQUEST_URI']);
