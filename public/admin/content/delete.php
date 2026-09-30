<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AJM\Core\Auth;
use AJM\Core\Repository;
use AJM\Core\Security;

Auth::requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$id      = (int) ($_POST['id'] ?? 0);
$typeKey = $_POST['type'] ?? '';

if ($id > 0) {
    (new Repository())->delete($id);
}

header('Location: /admin/content/index.php?type=' . urlencode($typeKey));
exit;
