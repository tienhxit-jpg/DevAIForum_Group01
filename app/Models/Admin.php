<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use Throwable;

final class Admin extends Model
{
    public function dashboard(): array
    {
        $totals = $this->one("SELECT (SELECT COUNT(*) FROM users) AS users_total, (SELECT COUNT(*) FROM posts WHERE status = 'published') AS posts_total, (SELECT COUNT(*) FROM comments WHERE status = 'visible') AS comments_total, (SELECT COUNT(*) FROM reports WHERE status IN ('pending', 'reviewing')) AS reports_pending, (SELECT COUNT(*) FROM users WHERE created_at >= CURRENT_DATE - INTERVAL 30 DAY) AS users_new_30d, (SELECT COUNT(*) FROM posts WHERE created_at >= CURRENT_DATE - INTERVAL 30 DAY AND status = 'published') AS posts_new_30d");
        $growth = $this->all("SELECT DATE(created_at) AS day, COUNT(*) AS total FROM users WHERE created_at >= CURRENT_DATE - INTERVAL 13 DAY GROUP BY DATE(created_at) ORDER BY day");
        $activity = $this->all("SELECT activity_day AS day, SUM(posts) AS posts, SUM(comments) AS comments FROM (SELECT DATE(created_at) AS activity_day, COUNT(*) AS posts, 0 AS comments FROM posts WHERE created_at >= CURRENT_DATE - INTERVAL 13 DAY GROUP BY DATE(created_at) UNION ALL SELECT DATE(created_at), 0, COUNT(*) FROM comments WHERE created_at >= CURRENT_DATE - INTERVAL 13 DAY GROUP BY DATE(created_at)) activity GROUP BY activity_day ORDER BY activity_day");
        return array_merge($totals ?? [], ['user_growth' => $growth, 'activity' => $activity]);
    }

    public function users(string $query = '', string $status = '', int $page = 1): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(u.username LIKE :username_query OR u.email LIKE :email_query OR u.display_name LIKE :name_query)';
            $like = '%' . $query . '%';
            $parameters += ['username_query' => $like, 'email_query' => $like, 'name_query' => $like];
        }
        if (in_array($status, ['active', 'banned', 'inactive'], true)) {
            $conditions[] = 'u.status = :status';
            $parameters['status'] = $status;
        }
        $offset = (max(1, $page) - 1) * 30;
        return $this->all('SELECT u.id, u.username, u.email, u.display_name, u.reputation, u.status, u.banned_reason, u.banned_until, u.created_at, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE ' . implode(' AND ', $conditions) . " ORDER BY u.created_at DESC LIMIT 30 OFFSET {$offset}", $parameters);
    }

    public function posts(string $query = '', string $status = '', int $page = 1): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = 'p.title LIKE :title_query';
            $parameters['title_query'] = '%' . $query . '%';
        }
        if (in_array($status, ['draft', 'published', 'hidden', 'deleted'], true)) {
            $conditions[] = 'p.status = :status';
            $parameters['status'] = $status;
        }
        $offset = (max(1, $page) - 1) * 30;
        return $this->all(
            'SELECT p.id, p.title, p.status, p.is_pinned, p.is_locked, p.view_count, p.created_at, u.username AS author_username, c.name AS category_name '
            . 'FROM posts p JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id '
            . 'WHERE ' . implode(' AND ', $conditions) . " ORDER BY p.created_at DESC LIMIT 30 OFFSET {$offset}",
            $parameters
        );
    }

    public function comments(string $query = '', string $status = '', int $page = 1): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = 'cm.content_html LIKE :content_query';
            $parameters['content_query'] = '%' . $query . '%';
        }
        if (in_array($status, ['visible', 'hidden', 'deleted'], true)) {
            $conditions[] = 'cm.status = :status';
            $parameters['status'] = $status;
        }
        $offset = (max(1, $page) - 1) * 30;
        return $this->all(
            'SELECT cm.id, cm.content_html, cm.status, cm.created_at, cm.post_id, u.username AS author_username, p.title AS post_title '
            . 'FROM comments cm JOIN users u ON u.id = cm.author_id JOIN posts p ON p.id = cm.post_id '
            . 'WHERE ' . implode(' AND ', $conditions) . " ORDER BY cm.created_at DESC LIMIT 30 OFFSET {$offset}",
            $parameters
        );
    }

    public function setUserStatus(int $userId, string $status, int $moderatorId, ?string $reason = null, ?string $until = null): void
    {
        if (!in_array($status, ['active', 'banned', 'inactive'], true) || $userId === $moderatorId) {
            throw new DomainException('Không thể thay đổi trạng thái tài khoản này.');
        }
        $action = $status === 'banned' ? 'ban_user' : 'unban_user';
        $this->db->beginTransaction();
        try {
            $target = $this->one('SELECT r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id FOR UPDATE', ['id' => $userId]);
            if ($target === null || $target['role_name'] === 'admin') {
                throw new DomainException('Không thể thay đổi trạng thái tài khoản này.');
            }
            $this->execute('UPDATE users SET status = :status, banned_reason = :reason, banned_until = :until WHERE id = :id', [
                'status' => $status, 'reason' => $status === 'banned' ? $reason : null,
                'until' => $status === 'banned' ? $until : null, 'id' => $userId,
            ]);
            $this->log($moderatorId, $action, $reason, $userId);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function setUserRole(int $userId, string $roleName, int $adminId): void
    {
        if (!in_array($roleName, ['member', 'moderator'], true) || $userId === $adminId) {
            throw new DomainException('Không thể thay đổi vai trò tài khoản này.');
        }
        $this->db->beginTransaction();
        try {
            $target = $this->one('SELECT r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id FOR UPDATE', ['id' => $userId]);
            if ($target === null || $target['role_name'] === 'admin') {
                throw new DomainException('Không thể thay đổi vai trò tài khoản này.');
            }
            $updated = $this->execute('UPDATE users u JOIN roles r ON r.name = :role_name SET u.role_id = r.id WHERE u.id = :id', ['role_name' => $roleName, 'id' => $userId]);
            if ($updated === 0) {
                throw new DomainException('Người dùng hoặc vai trò không tồn tại.');
            }
            $this->log($adminId, 'change_role', "Vai trò mới: {$roleName}", $userId);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function moderatePost(int $postId, int $moderatorId, string $action, ?string $reason = null): void
    {
        $updates = [
            'pin_post' => 'is_pinned = 1', 'unpin_post' => 'is_pinned = 0',
            'lock_post' => 'is_locked = 1', 'unlock_post' => 'is_locked = 0',
            'hide_post' => "status = 'hidden'", 'restore_post' => "status = 'published'",
        ];
        if (!isset($updates[$action])) {
            throw new DomainException('Thao tác kiểm duyệt không hợp lệ.');
        }
        $reason = trim((string) $reason);
        if ($action === 'hide_post' && $reason === '') {
            throw new DomainException('Cần nhập lý do hủy bài để thông báo cho tác giả.');
        }
        $this->db->beginTransaction();
        try {
            $post = $this->one('SELECT id, author_id, title FROM posts WHERE id = :id FOR UPDATE', ['id' => $postId]);
            if ($post === null) {
                throw new DomainException('Bài viết không tồn tại.');
            }
            $this->execute("UPDATE posts SET {$updates[$action]} WHERE id = :id", ['id' => $postId]);
            if ($action === 'hide_post') {
                $this->execute('UPDATE users SET reputation = reputation - 5 WHERE id = :author_id', ['author_id' => (int) $post['author_id']]);
            } elseif ($action === 'restore_post') {
                $this->execute('UPDATE users SET reputation = reputation + 5 WHERE id = :author_id', ['author_id' => (int) $post['author_id']]);
            }
            $this->log($moderatorId, $action, $reason !== '' ? $reason : null, null, $postId);
            if ((int) $post['author_id'] !== $moderatorId && in_array($action, ['hide_post', 'restore_post'], true)) {
                $message = $action === 'hide_post'
                    ? sprintf('Bài viết "%s" của bạn đã bị Moderator hủy. Lý do: %s', $post['title'], $reason)
                    : sprintf('Bài viết "%s" của bạn đã được Moderator khôi phục.%s', $post['title'], $reason !== '' ? ' Ghi chú: ' . $reason : '');
                $this->execute(
                    'INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (:user_id, :actor_id, :post_id, :type, :message)',
                    [
                        'user_id' => (int) $post['author_id'],
                        'actor_id' => $moderatorId,
                        'post_id' => $postId,
                        'type' => 'moderation',
                        'message' => mb_substr($message, 0, 255),
                    ]
                );
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function editPostTitle(int $postId, int $moderatorId, string $title, ?string $reason = null): void
    {
        $this->db->beginTransaction();
        try {
            $post = $this->one('SELECT title FROM posts WHERE id = :id AND status <> \'deleted\' FOR UPDATE', ['id' => $postId]);
            if ($post === null) {
                throw new DomainException('Bài viết không tồn tại.');
            }
            $this->execute('UPDATE posts SET title = :title WHERE id = :id', ['title' => trim($title), 'id' => $postId]);
            $this->log($moderatorId, 'edit_post_title', $reason ?? 'Chuẩn hóa tiêu đề bài viết', null, $postId);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function moderateComment(int $commentId, int $moderatorId, string $action, ?string $reason = null): void
    {
        if (!in_array($action, ['hide_comment', 'restore_comment'], true)) {
            throw new DomainException('Thao tác kiểm duyệt không hợp lệ.');
        }
        $status = $action === 'hide_comment' ? 'hidden' : 'visible';
        $this->db->beginTransaction();
        try {
            $comment = $this->one('SELECT id, author_id FROM comments WHERE id = :id FOR UPDATE', ['id' => $commentId]);
            if ($comment === null) {
                throw new DomainException('Bình luận không tồn tại.');
            }
            $this->execute('UPDATE comments SET status = :status WHERE id = :id', ['status' => $status, 'id' => $commentId]);
            if ($action === 'hide_comment') {
                $this->execute('UPDATE users SET reputation = reputation - 5 WHERE id = :author_id', ['author_id' => (int) $comment['author_id']]);
            } else {
                $this->execute('UPDATE users SET reputation = reputation + 5 WHERE id = :author_id', ['author_id' => (int) $comment['author_id']]);
            }
            $this->log($moderatorId, $action, $reason, null, null, $commentId);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function hardDeletePost(int $postId): array
    {
        $images = $this->all('SELECT file_path FROM post_images WHERE post_id = :post_id', ['post_id' => $postId]);
        if ($this->execute('DELETE FROM posts WHERE id = :id', ['id' => $postId]) === 0) {
            throw new DomainException('Bài viết không tồn tại.');
        }
        return array_column($images, 'file_path');
    }

    private function log(int $moderatorId, string $action, ?string $reason, ?int $targetUserId = null, ?int $postId = null, ?int $commentId = null): void
    {
        $this->execute('INSERT INTO moderation_logs (moderator_id, target_user_id, post_id, comment_id, action, reason) VALUES (:moderator_id, :target_user_id, :post_id, :comment_id, :action, :reason)', [
            'moderator_id' => $moderatorId, 'target_user_id' => $targetUserId, 'post_id' => $postId,
            'comment_id' => $commentId, 'action' => $action, 'reason' => $reason,
        ]);
    }
}
