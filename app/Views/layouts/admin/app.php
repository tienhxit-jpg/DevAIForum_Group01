<?php require __DIR__ . '/header.php'; ?>

<div class="admin-layout">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="admin-main">

        <main class="admin-content">

            <?php
            if (isset($content)) {
                echo $content;
            }
            ?>

        </main>

        <?php require __DIR__ . '/footer.php'; ?>

    </div>

</div>