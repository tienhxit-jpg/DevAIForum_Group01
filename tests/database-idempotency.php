<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';

$test = new TestCase();
$database = 'forum_db_idempotency_test';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

try {
    $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
    $scripts = ['forum_db.sql', 'seed.sql', 'mock_data.sql'];

    for ($pass = 1; $pass <= 2; $pass++) {
        foreach ($scripts as $script) {
            $sql = (string) file_get_contents(dirname(__DIR__) . '/database/' . $script);
            $pdo->exec(str_replace('forum_db', $database, $sql));
        }
    }

    $pdo->exec("USE `{$database}`");
    $test->test('schema seed and mock data remain stable after two imports', function () use ($test, $pdo, $database): void {
        $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$database}'")->fetchColumn();
        $test->assertSame(17, $tables);
        $test->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        $test->assertSame(18, (int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        $test->assertSame(9, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $test->assertSame(12, (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());
        $test->assertSame(17, (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn());
    });
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
}

$test->finish();
