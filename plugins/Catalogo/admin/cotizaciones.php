<?php
/**
 * Admin — solicitudes de cotización recibidas desde el sitio.
 * @var callable $pluginUrl
 * @var string   $pageKey
 */
use AJM\Core\AuditLogger;
use AJM\Core\Auth;
use AJM\Core\Database;
use AJM\Core\Security;

$currentUser = Auth::requireAuth('viewer');
$db = Database::getInstance();
$puedeEditar = Auth::hasRole('admin');

$estados = [
    'nueva'      => ['label' => 'Nueva',      'class' => 'bg-aj-olive/15 text-aj-olive-dark'],
    'en_proceso' => ['label' => 'En proceso', 'class' => 'bg-amber-100 text-amber-700'],
    'respondida' => ['label' => 'Respondida', 'class' => 'bg-green-100 text-green-700'],
    'cerrada'    => ['label' => 'Cerrada',    'class' => 'bg-gray-100 text-gray-500'],
];

$id    = isset($_GET['id']) ? (int) $_GET['id'] : null;
$flash = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditar && Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    $postId = (int) ($_POST['id'] ?? 0);
    $before = $db->fetchOne('SELECT * FROM cat_cotizaciones WHERE id = ?', [$postId]);
    if ($before && ($_POST['action'] ?? '') === 'delete') {
        $db->execute('DELETE FROM cat_cotizaciones WHERE id = ?', [$postId]);
        AuditLogger::log('cat_cotizaciones', (string) $postId, 'delete', $before, null);
        header('Location: ' . $pluginUrl('cotizaciones', ['msg' => 'Solicitud eliminada.']));
        exit;
    }
    if ($before && ($_POST['action'] ?? '') === 'update') {
        $estado = array_key_exists($_POST['estado'] ?? '', $estados) ? $_POST['estado'] : $before['estado'];
        $notas  = trim($_POST['notas_admin'] ?? '');
        $db->execute('UPDATE cat_cotizaciones SET estado = ?, notas_admin = ? WHERE id = ?', [$estado, $notas ?: null, $postId]);
        AuditLogger::log('cat_cotizaciones', (string) $postId, 'update', $before, ['estado' => $estado, 'notas_admin' => $notas]);
        header('Location: ' . $pluginUrl('cotizaciones', ['id' => $postId, 'msg' => 'Solicitud actualizada.']));
        exit;
    }
}

$csrfToken = Security::generateCSRF();
$cot = $id ? $db->fetchOne('SELECT * FROM cat_cotizaciones WHERE id = ?', [$id]) : null;

// Abrir una solicitud nueva la pasa automáticamente a "en proceso".
if ($cot && $cot['estado'] === 'nueva' && $puedeEditar) {
    $db->execute("UPDATE cat_cotizaciones SET estado = 'en_proceso' WHERE id = ?", [$cot['id']]);
    $cot['estado'] = 'en_proceso';
}

if (!$cot) {
    $filtro  = array_key_exists($_GET['estado'] ?? '', $estados) ? $_GET['estado'] : '';
    $q       = trim((string) ($_GET['q'] ?? ''));
    $where   = ['1 = 1'];
    $params  = [];
    if ($filtro !== '') {
        $where[]  = 'estado = ?';
        $params[] = $filtro;
    }
    if ($q !== '') {
        $where[] = '(codigo LIKE ? OR nombre LIKE ? OR empresa LIKE ? OR correo LIKE ?)';
        array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
    }
    $lista   = $db->fetchAll('SELECT * FROM cat_cotizaciones WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200', $params);
    $conteos = array_column($db->fetchAll('SELECT estado, COUNT(*) AS total FROM cat_cotizaciones GROUP BY estado'), 'total', 'estado');
}

$pageTitle = $cot ? 'Cotización ' . $cot['codigo'] : 'Cotizaciones';
require ADMIN_PARTIALS . '/header.php';
?>

<?php if ($flash): ?>
    <div class="mb-5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($cot): $items = json_decode($cot['items'], true) ?: []; $st = $estados[$cot['estado']]; $wa = preg_replace('/\D/', '', (string) $cot['telefono']); ?>
    <a href="<?= e($pluginUrl('cotizaciones')) ?>" class="inline-block text-sm text-aj-teal hover:underline mb-4">← Todas las cotizaciones</a>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-800"><?= e($cot['codigo']) ?></h2>
                        <p class="text-xs text-gray-400">Recibida el <?= e(date('d/m/Y H:i', strtotime($cot['created_at']))) ?></p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $st['class'] ?>"><?= e($st['label']) ?></span>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-2.5 font-medium">Código</th>
                            <th class="px-5 py-2.5 font-medium">Producto</th>
                            <th class="px-5 py-2.5 font-medium text-right">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= e($item['sku'] ?? '—') ?></td>
                                <td class="px-5 py-3">
                                    <a href="<?= e($pluginUrl('producto', ['id' => $item['id']])) ?>" class="font-medium text-gray-800 hover:text-aj-teal"><?= e($item['nombre']) ?></a>
                                    <?php if (!empty($item['presentacion'])): ?><p class="text-xs text-gray-400"><?= e($item['presentacion']) ?></p><?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold"><?= (int) $item['cantidad'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($cot['mensaje']): ?>
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-2">Mensaje del cliente</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line"><?= e($cot['mensaje']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-sm font-semibold text-aj-teal uppercase tracking-wide mb-3">Cliente</h3>
                <p class="font-semibold text-gray-800"><?= e($cot['nombre']) ?></p>
                <?php if ($cot['empresa']): ?><p class="text-sm text-gray-500"><?= e($cot['empresa']) ?></p><?php endif; ?>
                <div class="mt-3 space-y-1.5 text-sm">
                    <a href="mailto:<?= e($cot['correo']) ?>?subject=<?= rawurlencode('Cotización ' . $cot['codigo'] . ' — ' . SITE_NAME) ?>" class="flex items-center gap-2 text-aj-teal hover:underline"><?= ajm_icon('mail', 'w-4 h-4') ?> <?= e($cot['correo']) ?></a>
                    <?php if ($cot['telefono']): ?>
                        <a href="tel:<?= e($cot['telefono']) ?>" class="flex items-center gap-2 text-aj-teal hover:underline"><?= ajm_icon('phone', 'w-4 h-4') ?> <?= e($cot['telefono']) ?></a>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a href="mailto:<?= e($cot['correo']) ?>?subject=<?= rawurlencode('Cotización ' . $cot['codigo'] . ' — ' . SITE_NAME) ?>"
                       class="rounded-lg bg-aj-teal text-white text-xs font-semibold px-3 py-2 hover:opacity-90">Responder por correo</a>
                    <?php if (strlen($wa) >= 7): ?>
                        <a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hola ' . $cot['nombre'] . ', le escribimos de ' . SITE_NAME . ' sobre su solicitud ' . $cot['codigo'] . '.') ?>" target="_blank" rel="noopener"
                           class="rounded-lg bg-[#25D366] text-white text-xs font-semibold px-3 py-2 hover:opacity-90">WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($puedeEditar): ?>
                <form method="post" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= (int) $cot['id'] ?>">
                    <h3 class="text-sm font-semibold text-aj-teal uppercase tracking-wide">Seguimiento</h3>
                    <select name="estado" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <?php foreach ($estados as $k => $e): ?>
                            <option value="<?= e($k) ?>" <?= $cot['estado'] === $k ? 'selected' : '' ?>><?= e($e['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="notas_admin" rows="4" placeholder="Notas internas (precio enviado, seguimiento…)" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"><?= e($cot['notas_admin'] ?? '') ?></textarea>
                    <button name="action" value="update" class="w-full rounded-lg bg-aj-teal text-white font-semibold py-2.5 text-sm hover:opacity-90">Guardar</button>
                    <button name="action" value="delete" onclick="return confirm('¿Eliminar esta solicitud?')" class="w-full text-sm text-red-600 hover:underline">Eliminar solicitud</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($pluginUrl('cotizaciones')) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium border <?= $filtro === '' ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200 hover:border-aj-teal' ?>">
                Todas (<?= (int) array_sum($conteos) ?>)
            </a>
            <?php foreach ($estados as $k => $e): ?>
                <a href="<?= e($pluginUrl('cotizaciones', ['estado' => $k])) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium border <?= $filtro === $k ? 'bg-aj-teal text-white border-aj-teal' : 'bg-white text-gray-600 border-gray-200 hover:border-aj-teal' ?>">
                    <?= e($e['label']) ?> (<?= (int) ($conteos[$k] ?? 0) ?>)
                </a>
            <?php endforeach; ?>
        </div>
        <form method="get" class="flex gap-2">
            <input type="hidden" name="p" value="catalogo">
            <input type="hidden" name="page" value="cotizaciones">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Código, cliente o correo…" class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-500 border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="px-5 py-3 font-medium">Código</th>
                    <th class="px-5 py-3 font-medium">Cliente</th>
                    <th class="px-5 py-3 font-medium">Productos</th>
                    <th class="px-5 py-3 font-medium">Fecha</th>
                    <th class="px-5 py-3 font-medium">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($lista as $c): $st = $estados[$c['estado']]; $n = count(json_decode($c['items'], true) ?: []); ?>
                    <tr class="hover:bg-gray-50 cursor-pointer <?= $c['estado'] === 'nueva' ? 'font-semibold' : '' ?>" onclick="location.href='<?= e($pluginUrl('cotizaciones', ['id' => $c['id']])) ?>'">
                        <td class="px-5 py-3 text-aj-teal whitespace-nowrap"><?= e($c['codigo']) ?></td>
                        <td class="px-5 py-3">
                            <span class="text-gray-800"><?= e($c['nombre']) ?></span>
                            <span class="block text-xs text-gray-400 font-normal"><?= e($c['empresa'] ?: $c['correo']) ?></span>
                        </td>
                        <td class="px-5 py-3 text-gray-500"><?= $n ?></td>
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
                        <td class="px-5 py-3"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold <?= $st['class'] ?>"><?= e($st['label']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$lista): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">No hay solicitudes<?= $filtro !== '' || $q !== '' ? ' con ese filtro' : ' todavía' ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require ADMIN_PARTIALS . '/footer.php'; ?>
