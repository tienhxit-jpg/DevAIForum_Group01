<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Csrf;
use App\Core\Router;
use App\Core\Sanitizer;
use App\Core\Validator;
use App\Helpers\Slugger;

$test = new TestCase();

$test->test('validator reports registration errors by field', function () use ($test): void {
    $errors = Validator::validate([
        'username' => 'a!',
        'email' => 'not-an-email',
        'password' => 'short',
    ], [
        'username' => ['required', 'username', 'min:3', 'max:50'],
        'email' => ['required', 'email'],
        'password' => ['required', 'password', 'min:8'],
    ]);

    $test->assertTrue(isset($errors['username']));
    $test->assertTrue(isset($errors['email']));
    $test->assertTrue(isset($errors['password']));
});

$test->test('rich text sanitizer keeps code and removes executable markup', function () use ($test): void {
    $dirty = '<p onclick="alert(1)">Xin <strong>chào</strong></p>'
        . '<script>alert(1)</script><pre><code>$x = 1;</code></pre>'
        . '<img src="javascript:alert(1)" onerror="alert(2)">';
    $clean = Sanitizer::richText($dirty);

    $test->assertContains('<strong>chào</strong>', $clean);
    $test->assertContains('<pre><code>$x = 1;</code></pre>', $clean);
    $test->assertNotContains('<script', $clean);
    $test->assertNotContains('onclick', $clean);
    $test->assertNotContains('onerror', $clean);
    $test->assertNotContains('javascript:', $clean);
});

$test->test('sanitizer still cleans dangerous descendants of unwrapped tags', function () use ($test): void {
    $clean = Sanitizer::richText('<div><img src="javascript:alert(1)" onerror="alert(2)"><script>alert(3)</script></div>');
    $test->assertNotContains('javascript:', $clean);
    $test->assertNotContains('onerror', $clean);
    $test->assertNotContains('<script', $clean);
});

$test->test('slugger creates stable Vietnamese URL slugs', function () use ($test): void {
    $test->assertSame('lap-trinh-ai-voi-php', Slugger::make('Lập trình AI với PHP!'));
});

$test->test('router matches typed routes and extracts parameters', function () use ($test): void {
    $router = new Router();
    $router->get('/api/posts/{id}', static fn (): string => 'ok');
    $match = $router->match('GET', '/api/posts/42');

    $test->assertSame('42', $match['params']['id']);
    $test->assertTrue(is_callable($match['handler']));
    $test->assertSame(null, $router->match('POST', '/api/posts/42'));
});

$test->test('csrf token validates and rejects invalid values', function () use ($test): void {
    $_SESSION = [];
    $token = Csrf::token();
    $test->assertTrue(Csrf::verify($token));
    $test->assertFalse(Csrf::verify('invalid-token'));
});

$test->finish();
