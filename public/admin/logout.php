<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AJM\Core\Auth;

Auth::logout();
header('Location: /admin/index.php');
exit;
