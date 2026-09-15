<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Notification extends Model
{
    private const TYPES = ['like', 'comment', 'reply', 'best_answer', 'moderation', 'system'];

    public function create(int $userId, ?int $actorId, ?int $postId, ?int $commentId, string $type, string $message): int
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'system';
        }
        if ($actorId !== null && $actorId === $userId) {
            return 0;
        }
        $this->execute('INSERT INTO notifications (user_id, actor_id, post_id, comment_id, type, message) VALUES (:user_id, :actor_id, :post_id, :comment_id, :type, :message)', [
            'user_id' => $userId, 'actor_id' => $actorId, 'post_id' => $postId, 'comment_id' => $commentId,
            'type' => $type, 'message' => mb_substr($message, 0, 255),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function inbox(int $userId, int $page = 1, int $limit = 30): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT n.*, u.username AS actor_username, u.display_name AS actor_name, u.avatar_path AS actor_avatar FROM notifications n LEFT JOIN users u ON u.id = n.actor_id WHERE n.user_id = :user_id ORDER BY n.created_at DESC LIMIT {$limit} OFFSET {$offset}", ['user_id' => $userId]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND is_read = 0', ['user_id' => $userId])['total'] ?? 0);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->execute('UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id AND user_id = :user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->execute('UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE user_id = :user_id AND is_read = 0', ['user_id' => $userId]);
    }
}
