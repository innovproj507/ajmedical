<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Database;

$currentUser = Auth::requireAuth('super_admin');
$db = Database::getInstance();

$users = $db->fetchAll('SELECT id, username, email, nombre_completo, rol, activo, ultimo_login FROM admin_users ORDER BY nombre_completo');

$pageTitle = 'Usuarios';
$activeNav = 'users';
require __DIR__ . '/../partials/header.php';
?>

<div class="flex justify-end mb-5">
    <a href="/admin/users/edit.php" class="rounded-lg bg-aj-olive text-white text-sm font-semibold px-4 py-2 hover:opacity-90">+ Nuevo usuario</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
            <tr>
                <th class="px-5 py-3 font-medium">Nombre</th>
                <th class="px-5 py-3 font-medium">Usuario</th>
                <th class="px-5 py-3 font-medium">Rol</th>
                <th class="px-5 py-3 font-medium">Estado</th>
                <th class="px-5 py-3 font-medium">Último login</th>
                <th class="px-5 py-3 font-medium text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-gray-800"><?= e($u['nombre_completo']) ?></td>
                    <td class="px-5 py-3 text-gray-500"><?= e($u['username']) ?></td>
                    <td class="px-5 py-3 capitalize"><?= e($u['rol']) ?></td>
                    <td class="px-5 py-3"><?= $u['activo'] ? '<span class="text-green-600">Activo</span>' : '<span class="text-red-600">Inactivo</span>' ?></td>
                    <td class="px-5 py-3 text-gray-500"><?= e($u['ultimo_login'] ?? 'Nunca') ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="/admin/users/edit.php?id=<?= (int) $u['id'] ?>" class="text-aj-teal font-medium hover:underline">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
