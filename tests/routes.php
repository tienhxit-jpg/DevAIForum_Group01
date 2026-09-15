<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';
require dirname(__DIR__) . '/bootstrap.php';

$test = new TestCase();
$router = require dirname(__DIR__) . '/routes/web.php';

$routes = [
    ['GET', '/moderation'],
    ['GET', '/admin'], ['GET', '/admin/users'], ['GET', '/admin/posts'],
    ['GET', '/admin/comments'], ['GET', '/admin/reports'], ['GET', '/admin/taxonomy'],
    ['GET', '/api/csrf'], ['POST', '/api/auth/register'], ['POST', '/api/auth/login'],
    ['POST', '/api/auth/logout'], ['POST', '/api/auth/forgot-password'],
    ['POST', '/api/auth/reset-password'], ['GET', '/api/me'], ['GET', '/api/feed'],
    ['GET', '/api/posts/1'], ['POST', '/api/posts'], ['PUT', '/api/posts/1'],
    ['DELETE', '/api/posts/1'], ['POST', '/api/posts/1/best-answer'],
    ['POST', '/api/posts/1/comments'], ['PUT', '/api/comments/1'], ['DELETE', '/api/comments/1'],
    ['POST', '/api/posts/1/like'], ['POST', '/api/posts/1/bookmark'],
    ['GET', '/api/categories'], ['GET', '/api/tags'], ['GET', '/api/users/1'],
    ['GET', '/api/users/tester/posts'], ['PUT', '/api/profile'], ['PUT', '/api/profile/password'],
    ['POST', '/api/profile/avatar'], ['GET', '/api/profile/bookmarks'], ['GET', '/api/profile/posts'],
    ['GET', '/api/profile/posts/1'],
    ['GET', '/api/notifications'], ['PATCH', '/api/notifications/1/read'],
    ['PATCH', '/api/notifications/read-all'], ['POST', '/api/reports'],
    ['GET', '/api/admin/dashboard'], ['GET', '/api/admin/users'],
    ['GET', '/api/admin/posts'], ['GET', '/api/admin/comments'],
    ['PATCH', '/api/admin/users/1/status'], ['PATCH', '/api/admin/users/1/role'],
    ['PATCH', '/api/admin/posts/1/moderate'], ['PATCH', '/api/admin/comments/1/moderate'],
    ['PATCH', '/api/admin/posts/1/title'],
    ['DELETE', '/api/admin/posts/1/permanent'],
    ['GET', '/api/admin/reports'], ['PATCH', '/api/admin/reports/1'],
    ['POST', '/api/admin/categories'], ['PUT', '/api/admin/categories/1'],
    ['DELETE', '/api/admin/categories/1'], ['POST', '/api/admin/tags'],
    ['PUT', '/api/admin/tags/1'], ['DELETE', '/api/admin/tags/1'], ['POST', '/api/admin/tags/1/merge'],
];

foreach ($routes as [$method, $path]) {
    $test->test("route {$method} {$path} is registered", function () use ($test, $router, $method, $path): void {
        $test->assertTrue($router->match($method, $path) !== null, 'Route is missing');
    });
}

$test->finish();
