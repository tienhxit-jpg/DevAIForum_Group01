<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function requireValid(Request $request): void
    {
        $token = $request->header('X-CSRF-Token') ?? $request->input('_csrf');
        if (!self::verify(is_string($token) ? $token : null)) {
            Response::json(['error' => 'CSRF token không hợp lệ.'], 419);
        }
    }
}
