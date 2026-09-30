<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\AuditLogger;
use AJM\Core\Database;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('super_admin');
$db = Database::getInstance();

$userId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$user   = $userId ? $db->fetchOne('SELECT * FROM admin_users WHERE id = ?', [$userId]) : null;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $nombre   = trim($_POST['nombre_completo'] ?? '');
        $rol      = $_POST['rol'] ?? 'viewer';
        $activo   = isset($_POST['activo']) ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $email === '' || $nombre === '') {
            $error = 'Usuario, correo y nombre completo son obligatorios.';
        } elseif (!$user && $password === '') {
            $error = 'La contraseña es obligatoria para un usuario nuevo.';
        } else {
            if ($user) {
                $before = $user;
                if ($password !== '') {
                    $db->execute(
                        'UPDATE admin_users SET username=?, email=?, nombre_completo=?, rol=?, activo=?, password_hash=? WHERE id=?',
                        [$username, $email, $nombre, $rol, $activo, Auth::hashPassword($password), $user['id']]
                    );
                } else {
                    $db->execute(
                        'UPDATE admin_users SET username=?, email=?, nombre_completo=?, rol=?, activo=? WHERE id=?',
                        [$username, $email, $nombre, $rol, $activo, $user['id']]
                    );
                }
                AuditLogger::log('admin_users', (string) $user['id'], 'update', $before, ['username' => $username, 'rol' => $rol]);
            } else {
                $db->execute(
                    'INSERT INTO admin_users (username, email, password_hash, nombre_completo, rol, activo) VALUES (?,?,?,?,?,?)',
                    [$username, $email, Auth::hashPassword($password), $nombre, $rol, $activo]
                );
                AuditLogger::log('admin_users', (string) $db->lastInsertId(), 'insert', null, ['username' => $username, 'rol' => $rol]);
            }
            header('Location: /admin/users/index.php');
            exit;
        }
    }
}

$csrfToken = Security::generateCSRF();
$pageTitle = $user ? 'Editar usuario' : 'Nuevo usuario';
$activeNav = 'users';
require __DIR__ . '/../partials/header.php';
?>

<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="bg-white rounded-xl border border-gray-200 p-6 max-w-lg space-y-4">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
        <input type="text" name="nombre_completo" required value="<?= e($user['nombre_completo'] ?? '') ?>"
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
        <input type="text" name="username" required value="<?= e($user['username'] ?? '') ?>"
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Correo</label>
        <input type="email" name="email" required value="<?= e($user['email'] ?? '') ?>"
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Contraseña <?= $user ? '(dejar en blanco para no cambiar)' : '' ?>
        </label>
        <input type="password" name="password" <?= $user ? '' : 'required' ?>
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
        <select name="rol" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <?php foreach (['viewer' => 'Viewer', 'admin' => 'Admin', 'super_admin' => 'Super Admin'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($user['rol'] ?? 'viewer') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center gap-2">
        <input type="checkbox" name="activo" id="activo" <?= ($user['activo'] ?? 1) ? 'checked' : '' ?> class="rounded border-gray-300">
        <label for="activo" class="text-sm text-gray-700">Usuario activo</label>
    </div>

    <button type="submit" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90">Guardar</button>
    <a href="/admin/users/index.php" class="block text-center text-sm text-gray-500 hover:underline">Cancelar</a>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
