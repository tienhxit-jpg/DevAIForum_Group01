<?php

ob_start();
require __DIR__ . '/../../app/Views/admin/users/index.php';
$content = ob_get_clean();

require __DIR__ . '/../../app/Views/layouts/admin/app.php';