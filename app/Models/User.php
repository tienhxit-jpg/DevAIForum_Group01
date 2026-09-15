<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use Throwable;

final class User extends Model
{
    public function create(array $data): int
    {
        $role = $this->one("SELECT id FROM roles WHERE name = 'member' LIMIT 1");
        if ($role === null) {
            throw new DomainException('Vai trò thành viên chưa được khởi tạo.');
        }
        if ($this->findByLogin((string) $data['username']) !== null || $this->findByLogin((string) $data['email']) !== null) {
            throw new DomainException('Tên đăng nhập hoặc email đã tồn tại.');
        }

        $this->execute(
            'INSERT INTO users (role_id, username, email, password_hash, display_name) VALUES (:role_id, :username, :email, :password_hash, :display_name)',
            [
                'role_id' => $role['id'],
                'username' => trim((string) $data['username']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
                'display_name' => trim((string) $data['display_name']),
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function authenticate(string $login, string $password): ?array
    {
        $user = $this->findByLogin($login);
        if ($user === null || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        $this->execute('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $user['id']]);
        unset($user['password_hash']);
        return $user;
    }

    public function findByLogin(string $login): ?array
    {
        return $this->one(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :username_login OR u.email = :email_login LIMIT 1',
            [
                'username_login' => trim($login),
                'email_login' => mb_strtolower(trim($login)),
            ]
        );
    }

    public function findByUsername(string $username): ?array
    {
        $user = $this->one(
            'SELECT u.id, u.username, u.email, u.display_name, u.bio, u.avatar_path, u.reputation, u.status, u.created_at, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :username LIMIT 1',
            ['username' => $username]
        );
        return $user;
    }

    public function findPublic(string $idOrUsername): ?array
    {
        $condition = ctype_digit($idOrUsername) ? 'u.id = :key' : 'u.username = :key';
        $row = $this->one(
            "SELECT u.id, u.username, u.display_name, u.bio, u.avatar_path, u.reputation, u.created_at, r.name AS role_name,
                (SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id AND p.status = 'published') AS post_count,
                (SELECT COUNT(*) FROM comments c WHERE c.author_id = u.id AND c.status = 'visible') AS comment_count,
                COALESCE((SELECT SUM(
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p2.id)
                    - (SELECT COUNT(*) FROM post_dislikes pd WHERE pd.post_id = p2.id)
                ) FROM posts p2 WHERE p2.author_id = u.id AND p2.status = 'published'), 0) AS post_karma,
                COALESCE((SELECT SUM(
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c2.id)
                    - (SELECT COUNT(*) FROM comment_dislikes cd WHERE cd.comment_id = c2.id)
                ) FROM comments c2 WHERE c2.author_id = u.id AND c2.status = 'visible'), 0) AS comment_karma
             FROM users u JOIN roles r ON r.id = u.role_id WHERE {$condition} AND u.status <> 'inactive'",
            ['key' => $idOrUsername]
        );
        if ($row === null) {
            return null;
        }
        $row['post_karma'] = (int) $row['post_karma'];
        $row['comment_karma'] = (int) $row['comment_karma'];
        return $row;
    }

    public function karmaFor(int $id): array
    {
        $row = $this->one(
            "SELECT
                COALESCE((SELECT SUM(
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id)
                    - (SELECT COUNT(*) FROM post_dislikes pd WHERE pd.post_id = p.id)
                ) FROM posts p WHERE p.author_id = :id1 AND p.status = 'published'), 0) AS post_karma,
                COALESCE((SELECT SUM(
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id)
                    - (SELECT COUNT(*) FROM comment_dislikes cd WHERE cd.comment_id = c.id)
                ) FROM comments c WHERE c.author_id = :id2 AND c.status = 'visible'), 0) AS comment_karma",
            ['id1' => $id, 'id2' => $id]
        );
        return [
            'post_karma' => (int) ($row['post_karma'] ?? 0),
            'comment_karma' => (int) ($row['comment_karma'] ?? 0),
        ];
    }

    public function updateProfile(int $id, array $data): void
    {
        $this->execute(
            'UPDATE users SET display_name = :display_name, bio = :bio WHERE id = :id',
            ['id' => $id, 'display_name' => trim((string) $data['display_name']), 'bio' => trim((string) ($data['bio'] ?? '')) ?: null]
        );
    }

    public function setAvatar(int $id, string $path): void
    {
        $this->execute('UPDATE users SET avatar_path = :path WHERE id = :id', ['path' => $path, 'id' => $id]);
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        $row = $this->one('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        if ($row === null || !password_verify($currentPassword, $row['password_hash'])) {
            return false;
        }
        $this->execute('UPDATE users SET password_hash = :hash WHERE id = :id', [
            'hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'id' => $id,
        ]);
        return true;
    }

    public function permissions(int $id): array
    {
        $rows = $this->all(
            'SELECT p.code FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id WHERE u.id = :id',
            ['id' => $id]
        );
        return array_column($rows, 'code');
    }

    public function createPasswordReset(string $email): ?string
    {
        $user = $this->one('SELECT id FROM users WHERE email = :email AND status = \'active\'', [
            'email' => mb_strtolower(trim($email)),
        ]);
        if ($user === null) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $this->execute('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND used_at IS NULL', ['user_id' => $user['id']]);
        $this->execute('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, CURRENT_TIMESTAMP + INTERVAL 1 HOUR)', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
        ]);
        return $token;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $this->db->beginTransaction();
        try {
            $row = $this->one('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP FOR UPDATE', [
                'token_hash' => hash('sha256', $token),
            ]);
            if ($row === null) {
                $this->db->rollBack();
                return false;
            }
            $this->execute('UPDATE users SET password_hash = :password_hash WHERE id = :user_id', [
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'user_id' => $row['user_id'],
            ]);
            $this->execute('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $row['id']]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
