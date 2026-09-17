<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use Throwable;

final class PostAppeal extends Model
{
    public function create(int $postId, int $userId, string $reason): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 20) {
            throw new DomainException('Vui lòng trình bày lý do kháng cáo chi tiết hơn (tối thiểu 20 ký tự).');
        }
        $post = $this->one('SELECT id, author_id, status FROM posts WHERE id = :id', ['id' => $postId]);
        if ($post === null || $post['status'] !== 'hidden') {
            throw new DomainException('Chỉ có thể kháng cáo bài viết đang bị ẩn.');
        }
        if ((int) $post['author_id'] !== $userId) {
            throw new DomainException('Bạn chỉ có thể kháng cáo bài viết của chính mình.');
        }
        $existing = $this->one("SELECT id FROM post_appeals WHERE post_id = :post_id AND status = 'pending'", ['post_id' => $postId]);
        if ($existing !== null) {
            throw new DomainException('Bài viết này đã có một kháng cáo đang chờ xử lý.');
        }
        $this->execute('INSERT INTO post_appeals (post_id, user_id, reason) VALUES (:post_id, :user_id, :reason)', [
            'post_id' => $postId, 'user_id' => $userId, 'reason' => $reason,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function latestForPost(int $postId): ?array
    {
        return $this->one('SELECT id, reason, status, admin_reason, created_at, handled_at FROM post_appeals WHERE post_id = :post_id ORDER BY created_at DESC LIMIT 1', ['post_id' => $postId]);
    }

    public function queue(string $status = 'pending', int $page = 1): array
    {
        $allowed = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $offset = (max(1, $page) - 1) * 30;
        return $this->all(
            "SELECT a.*, u.username AS user_username, u.display_name AS user_display_name, admin.username AS admin_username, p.title AS post_title, p.status AS post_status, p.hidden_reason FROM post_appeals a JOIN users u ON u.id = a.user_id JOIN posts p ON p.id = a.post_id LEFT JOIN users admin ON admin.id = a.admin_id WHERE a.status = :status ORDER BY a.created_at ASC LIMIT 30 OFFSET {$offset}",
            ['status' => $status]
        );
    }

    public function decide(int $id, int $adminId, string $decision, ?string $adminReason = null): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Quyết định không hợp lệ.');
        }
        $adminReason = trim((string) $adminReason);
        if ($decision === 'rejected' && $adminReason === '') {
            throw new DomainException('Vui lòng nhập lý do từ chối kháng cáo để thông báo cho tác giả.');
        }
        $this->db->beginTransaction();
        try {
            $appeal = $this->one('SELECT a.id, a.post_id, a.user_id, a.status, p.title FROM post_appeals a JOIN posts p ON p.id = a.post_id WHERE a.id = :id FOR UPDATE', ['id' => $id]);
            if ($appeal === null) {
                throw new DomainException('Không tìm thấy kháng cáo.');
            }
            if ($appeal['status'] !== 'pending') {
                throw new DomainException('Kháng cáo này đã được xử lý.');
            }
            $this->execute('UPDATE post_appeals SET status = :status, admin_id = :admin_id, admin_reason = :admin_reason, handled_at = CURRENT_TIMESTAMP WHERE id = :id', [
                'status' => $decision, 'admin_id' => $adminId, 'admin_reason' => $adminReason !== '' ? $adminReason : null, 'id' => $id,
            ]);

            if ($decision === 'approved') {
                $this->execute("UPDATE posts SET status = 'published', hidden_reason = NULL WHERE id = :id", ['id' => $appeal['post_id']]);
                $this->execute('UPDATE users SET reputation = reputation + 5 WHERE id = :author_id', ['author_id' => (int) $appeal['user_id']]);
                $message = sprintf('Kháng cáo cho bài viết "%s" đã được chấp thuận. Bài viết của bạn đã được khôi phục.', $appeal['title']);
            } else {
                $message = sprintf('Kháng cáo cho bài viết "%s" đã bị từ chối. Lý do: %s', $appeal['title'], $adminReason);
            }

            $this->execute(
                'INSERT INTO notifications (user_id, actor_id, post_id, type, message) VALUES (:user_id, :actor_id, :post_id, :type, :message)',
                [
                    'user_id' => (int) $appeal['user_id'], 'actor_id' => $adminId, 'post_id' => (int) $appeal['post_id'],
                    'type' => 'moderation', 'message' => mb_substr($message, 0, 255),
                ]
            );
            $this->execute(
                "INSERT INTO moderation_logs (moderator_id, target_user_id, post_id, action, reason) VALUES (:moderator_id, :target_user_id, :post_id, :action, :reason)",
                [
                    'moderator_id' => $adminId, 'target_user_id' => (int) $appeal['user_id'], 'post_id' => (int) $appeal['post_id'],
                    'action' => $decision === 'approved' ? 'approve_appeal' : 'reject_appeal',
                    'reason' => $adminReason !== '' ? $adminReason : null,
                ]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
