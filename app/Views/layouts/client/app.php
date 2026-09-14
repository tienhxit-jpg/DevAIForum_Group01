<?php require __DIR__ . '/header.php'; ?>

<?php require __DIR__ . '/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">

        <div class="col-lg-3 col-xl-2">
            <?php require __DIR__ . '/sidebar.php'; ?>
        </div>

        <main class="col-lg-9 col-xl-10 py-4">

            <?php
            if (isset($content)) {
                echo $content;
            }
            ?>

        </main>

    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
