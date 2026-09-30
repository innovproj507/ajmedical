<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Media;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('admin');
$media = new Media();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } elseif (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Selecciona un archivo válido.';
    } else {
        try {
            $media->upload($_FILES['archivo'], $_POST['subdir'] ?? 'media');
            $success = true;
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$items = $media->list(100);
$csrfToken = Security::generateCSRF();

$pageTitle = 'Media';
$activeNav = 'media';
require __DIR__ . '/../partials/header.php';
?>

<?php if ($success): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">Archivo subido correctamente.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">Subir archivo</h2>
    <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Archivo (PDF, JPG, PNG, WEBP)</label>
            <input type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Carpeta</label>
            <select name="subdir" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="media">media</option>
                <option value="catalogo">catalogo</option>
                <option value="paginas">paginas</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-aj-olive text-white text-sm font-semibold px-4 py-2 hover:opacity-90">Subir</button>
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
    <?php foreach ($items as $item): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-3">
            <?php if (str_starts_with($item['mime_type'], 'image/')): ?>
                <img src="/uploads/<?= e($item['path']) ?>" alt="" class="w-full h-24 object-cover rounded-lg mb-2 bg-gray-100">
            <?php else: ?>
                <div class="w-full h-24 rounded-lg mb-2 bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-400">PDF</div>
            <?php endif; ?>
            <p class="text-xs font-medium text-gray-700 truncate" title="<?= e($item['filename']) ?>"><?= e($item['filename']) ?></p>
            <p class="text-[11px] text-gray-400">ID: <?= (int) $item['id'] ?></p>
        </div>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <p class="text-gray-400 text-sm col-span-full text-center py-8">No hay archivos todavía.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
