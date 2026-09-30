<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Security;

if (Auth::check()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (!Security::checkRateLimit('admin_login', 5, 600)) {
        $error = 'Demasiados intentos. Intenta de nuevo en unos minutos.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::login($username, $password)) {
            $redirect = $_GET['redirect'] ?? '/admin/dashboard.php';
            header('Location: ' . (str_starts_with($redirect, '/') ? $redirect : '/admin/dashboard.php'));
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$csrfToken = Security::generateCSRF();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar — AJ Medical CMS</title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="stylesheet" href="/dist/css/admin.min.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-aj-teal-dark via-aj-teal to-aj-teal-dark px-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-7">
            <img src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>" class="h-14 w-auto mx-auto">
            <p class="text-xs font-semibold uppercase tracking-widest text-aj-olive mt-5">Panel de administración</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                <input type="text" name="username" required autofocus
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
            </div>
            <button type="submit"
                    class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90 transition">
                Ingresar
            </button>
        </form>
    </div>
</body>
</html>
