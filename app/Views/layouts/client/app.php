<?php
$title = $title ?? 'DevAI Hub';
$content = $content ?? '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($title) ?></title>
</head>

<body>

    <?php require __DIR__ . '/header.php'; ?>

    <?php require __DIR__ . '/navbar.php'; ?>

    <main>
        <?= $content ?>
    </main>

    <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>