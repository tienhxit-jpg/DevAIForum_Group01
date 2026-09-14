<?php

ob_start();

require __DIR__ . '/../../app/Views/client/feed/index.php';

$content = ob_get_clean();

require __DIR__ . '/../../app/Views/layouts/client/app.php';