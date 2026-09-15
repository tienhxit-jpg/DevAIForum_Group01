<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?array $cachedUser = null;

    public static function login(int $userId): void
    {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;
        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$cachedUser = null;
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        if (self::$cachedUser !== null && (int) self::$cachedUser['id'] === $id) {
            return self::$cachedUser;
        }
        $row = Database::connection()->prepare('SELECT u.id, u.role_id, u.username, u.email, u.display_name, u.bio, u.avatar_path, u.reputation, u.status, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $row->execute(['id' => $id]);
        $user = $row->fetch();
        if (!is_array($user) || $user['status'] !== 'active') {
            self::logout();
            return null;
        }
        self::$cachedUser = $user;
        return $user;
    }

    public static function requireLogin(): int
    {
        $user = self::user();
        if ($user === null) {
            Response::json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        return (int) $user['id'];
    }

    public static function can(string $permission): bool
    {
        $id = self::id();
        if ($id === null) {
            return false;
        }
        return in_array($permission, (new User())->permissions($id), true);
    }

    public static function requirePermission(string $permission): int
    {
        $id = self::requireLogin();
        if (!self::can($permission)) {
            Response::json(['error' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }
        return $id;
    }
}
