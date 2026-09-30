<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Repository;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('admin');
$repo = new Repository();
$db   = Database::getInstance();

$typeKey = $_GET['type'] ?? '';
$type    = $repo->getContentType($typeKey);
if (!$type) {
    http_response_code(404);
    exit('Tipo de contenido no encontrado.');
}

$entryId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$entry   = $entryId ? $repo->findById($entryId) : null;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada, intenta de nuevo.';
    } else {
        try {
            $fieldsData = [];
            foreach ($type['fields_schema'] as $field) {
                $fieldsData[$field['name']] = $_POST['data'][$field['name']] ?? null;
            }

            $id = $repo->save([
                'content_type_id'   => $type['id'],
                'slug'              => $_POST['slug'] ?? '',
                'title'             => $_POST['title'] ?? '',
                'excerpt'           => $_POST['excerpt'] ?? null,
                'status'            => $_POST['status'] ?? 'draft',
                'seo_title'         => $_POST['seo_title'] ?? null,
                'seo_description'   => $_POST['seo_description'] ?? null,
                'featured_image_id' => !empty($_POST['featured_image_id']) ? (int) $_POST['featured_image_id'] : null,
                'data'              => $fieldsData,
            ], $entryId);

            header('Location: /admin/content/edit.php?type=' . urlencode($type['key']) . '&id=' . $id . '&saved=1');
            exit;
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$mediaOptions = $db->fetchAll('SELECT id, filename, mime_type FROM media ORDER BY created_at DESC LIMIT 200');
$csrfToken = Security::generateCSRF();

$pageTitle = $entry ? 'Editar: ' . $entry['title'] : 'Nuevo ' . $type['label'];
$activeNav = 'content';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">Guardado correctamente.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="lg:col-span-2 space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
            <input type="text" name="title" required value="<?= e($entry['title'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">

            <label class="block text-sm font-medium text-gray-700 mt-4 mb-1">Slug (URL) — opcional, se genera del título si se deja vacío</label>
            <input type="text" name="slug" value="<?= e($entry['slug'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">

            <label class="block text-sm font-medium text-gray-700 mt-4 mb-1">Extracto</label>
            <textarea name="excerpt" rows="2"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal"><?= e($entry['excerpt'] ?? '') ?></textarea>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide">Campos de <?= e($type['label']) ?></h2>
            <?php foreach ($type['fields_schema'] as $field):
                $name  = $field['name'];
                $value = $entry['data'][$name] ?? '';
            ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?>
                    </label>
                    <?php if (in_array($field['type'], ['richtext', 'textarea'], true)): ?>
                        <textarea name="data[<?= e($name) ?>]" rows="<?= $field['type'] === 'richtext' ? 10 : 3 ?>"
                                  <?= !empty($field['required']) ? 'required' : '' ?>
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-aj-teal"><?= e((string) $value) ?></textarea>
                    <?php elseif ($field['type'] === 'media'): ?>
                        <select name="data[<?= e($name) ?>]" <?= !empty($field['required']) ? 'required' : '' ?>
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
                            <option value="">— Seleccionar de Media —</option>
                            <?php foreach ($mediaOptions as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= (string) $value === (string) $m['id'] ? 'selected' : '' ?>>
                                    <?= e($m['filename']) ?> (<?= e($m['mime_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">¿No está en la lista? Súbelo primero en <a href="/admin/media/index.php" class="text-aj-teal underline">Media</a>.</p>
                    <?php elseif ($field['type'] === 'boolean'): ?>
                        <input type="checkbox" name="data[<?= e($name) ?>]" value="1" <?= $value ? 'checked' : '' ?> class="rounded border-gray-300">
                    <?php else: ?>
                        <input type="<?= $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text') ?>"
                               name="data[<?= e($name) ?>]" value="<?= e((string) $value) ?>"
                               <?= !empty($field['required']) ? 'required' : '' ?>
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">SEO</h2>
            <label class="block text-sm font-medium text-gray-700 mb-1">Título SEO</label>
            <input type="text" name="seo_title" value="<?= e($entry['seo_title'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-aj-teal">
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción SEO</label>
            <textarea name="seo_description" rows="2"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal"><?= e($entry['seo_description'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
            <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-aj-teal">
                <?php foreach (['draft' => 'Borrador', 'published' => 'Publicado', 'scheduled' => 'Programado', 'archived' => 'Archivado'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($entry['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <label class="block text-sm font-medium text-gray-700 mb-1">Imagen destacada</label>
            <select name="featured_image_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal">
                <option value="">— Ninguna —</option>
                <?php foreach ($mediaOptions as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (string) ($entry['featured_image_id'] ?? '') === (string) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['filename']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="w-full mt-5 rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90">
                Guardar
            </button>
            <a href="/admin/content/index.php?type=<?= urlencode($type['key']) ?>" class="block text-center mt-2 text-sm text-gray-500 hover:underline">Cancelar</a>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
