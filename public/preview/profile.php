<?php

ob_start();

require __DIR__ . '/../../app/Views/client/profile/index.php';

$content = ob_get_clean();

require __DIR__ . '/../../app/Views/layouts/client/app.php';