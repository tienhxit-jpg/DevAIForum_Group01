<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use Throwable;

final class Interaction extends Model
{
    public function toggleLike(int $userId, int $postId): array
    {
        $this->db->beginTransaction();
        try {
            $hadDislike = $this->one('SELECT 1 FROM post_dislikes WHERE user_id = :u AND post_id = :p FOR UPDATE', ['u' => $userId, 'p' => $postId]);
            if ($hadDislike !== null) {
                $this->execute('DELETE FROM post_dislikes WHERE user_id = :u AND post_id = :p', ['u' => $userId, 'p' => $postId]);
                $this->adjustPostAuthorReputation($postId, $userId, 1);
            }

            $liked = $this->togglePivot('post_likes', 'post_id', $userId, $postId, "SELECT :user_id, id FROM posts WHERE id = :entity_id AND status = 'published'");
            $this->adjustPostAuthorReputation($postId, $userId, $liked ? 2 : -2);

            [$likeCount, $dislikeCount] = $this->postVoteCounts($postId);
            $this->db->commit();
            return ['liked' => $liked, 'disliked' => false, 'like_count' => $likeCount, 'dislike_count' => $dislikeCount, 'count' => $likeCount];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleDislike(int $userId, int $postId): array
    {
        $this->db->beginTransaction();
        try {
            $hadLike = $this->one('SELECT 1 FROM post_likes WHERE user_id = :u AND post_id = :p FOR UPDATE', ['u' => $userId, 'p' => $postId]);
            if ($hadLike !== null) {
                $this->execute('DELETE FROM post_likes WHERE user_id = :u AND post_id = :p', ['u' => $userId, 'p' => $postId]);
                $this->adjustPostAuthorReputation($postId, $userId, -2);
            }

            $disliked = $this->togglePivot('post_dislikes', 'post_id', $userId, $postId, "SELECT :user_id, id FROM posts WHERE id = :entity_id AND status = 'published'");
            $this->adjustPostAuthorReputation($postId, $userId, $disliked ? -1 : 1);

            [$likeCount, $dislikeCount] = $this->postVoteCounts($postId);
            $this->db->commit();
            return ['liked' => false, 'disliked' => $disliked, 'like_count' => $likeCount, 'dislike_count' => $dislikeCount, 'count' => $likeCount];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleCommentLike(int $userId, int $commentId): array
    {
        $this->db->beginTransaction();
        try {
            $hadDislike = $this->one('SELECT 1 FROM comment_dislikes WHERE user_id = :u AND comment_id = :c FOR UPDATE', ['u' => $userId, 'c' => $commentId]);
            if ($hadDislike !== null) {
                $this->execute('DELETE FROM comment_dislikes WHERE user_id = :u AND comment_id = :c', ['u' => $userId, 'c' => $commentId]);
                $this->adjustCommentAuthorReputation($commentId, $userId, 1);
            }

            $liked = $this->togglePivot('comment_likes', 'comment_id', $userId, $commentId, "SELECT :user_id, id FROM comments WHERE id = :entity_id AND status = 'visible'");
            $this->adjustCommentAuthorReputation($commentId, $userId, $liked ? 1 : -1);

            [$likeCount, $dislikeCount] = $this->commentVoteCounts($commentId);
            $this->db->commit();
            return ['liked' => $liked, 'disliked' => false, 'like_count' => $likeCount, 'dislike_count' => $dislikeCount];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleCommentDislike(int $userId, int $commentId): array
    {
        $this->db->beginTransaction();
        try {
            $hadLike = $this->one('SELECT 1 FROM comment_likes WHERE user_id = :u AND comment_id = :c FOR UPDATE', ['u' => $userId, 'c' => $commentId]);
            if ($hadLike !== null) {
                $this->execute('DELETE FROM comment_likes WHERE user_id = :u AND comment_id = :c', ['u' => $userId, 'c' => $commentId]);
                $this->adjustCommentAuthorReputation($commentId, $userId, -1);
            }

            $disliked = $this->togglePivot('comment_dislikes', 'comment_id', $userId, $commentId, "SELECT :user_id, id FROM comments WHERE id = :entity_id AND status = 'visible'");
            $this->adjustCommentAuthorReputation($commentId, $userId, $disliked ? -1 : 1);

            [$likeCount, $dislikeCount] = $this->commentVoteCounts($commentId);
            $this->db->commit();
            return ['liked' => false, 'disliked' => $disliked, 'like_count' => $likeCount, 'dislike_count' => $dislikeCount];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleBookmark(int $userId, int $postId): array
    {
        return $this->toggle('bookmarks', $userId, $postId, 'bookmarked');
    }

    public function bookmarks(int $userId, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT p.id, p.title, p.slug, p.post_type, p.created_at, u.username, u.display_name, b.created_at AS bookmarked_at FROM bookmarks b JOIN posts p ON p.id = b.post_id JOIN users u ON u.id = p.author_id WHERE b.user_id = :user_id AND p.status = 'published' ORDER BY b.created_at DESC LIMIT {$limit} OFFSET {$offset}", ['user_id' => $userId]);
    }

    public function togglePin(int $userId, int $postId): array
    {
        return $this->toggle('post_pins', $userId, $postId, 'pinned');
    }

    public function pins(int $userId, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT p.id, p.title, p.slug, p.post_type, p.created_at, u.username, u.display_name, pp.created_at AS pinned_at FROM post_pins pp JOIN posts p ON p.id = pp.post_id JOIN users u ON u.id = p.author_id WHERE pp.user_id = :user_id AND p.status = 'published' ORDER BY pp.created_at DESC LIMIT {$limit} OFFSET {$offset}", ['user_id' => $userId]);
    }

    public function likedPosts(int $userId, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT p.id, p.title, p.slug, p.post_type, p.created_at, u.username, u.display_name, pl.created_at AS voted_at FROM post_likes pl JOIN posts p ON p.id = pl.post_id JOIN users u ON u.id = p.author_id WHERE pl.user_id = :user_id AND p.status = 'published' ORDER BY pl.created_at DESC LIMIT {$limit} OFFSET {$offset}", ['user_id' => $userId]);
    }

    public function dislikedPosts(int $userId, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT p.id, p.title, p.slug, p.post_type, p.created_at, u.username, u.display_name, pd.created_at AS voted_at FROM post_dislikes pd JOIN posts p ON p.id = pd.post_id JOIN users u ON u.id = p.author_id WHERE pd.user_id = :user_id AND p.status = 'published' ORDER BY pd.created_at DESC LIMIT {$limit} OFFSET {$offset}", ['user_id' => $userId]);
    }

    private function togglePivot(string $table, string $entityColumn, int $userId, int $entityId, string $insertSelectSql): bool
    {
        $exists = $this->one("SELECT user_id FROM {$table} WHERE user_id = :user_id AND {$entityColumn} = :entity_id FOR UPDATE", ['user_id' => $userId, 'entity_id' => $entityId]);
        if ($exists === null) {
            $inserted = $this->execute("INSERT INTO {$table} (user_id, {$entityColumn}) {$insertSelectSql}", ['user_id' => $userId, 'entity_id' => $entityId]);
            if ($inserted === 0) {
                throw new DomainException('Nội dung không tồn tại.');
            }
            return true;
        }
        $this->execute("DELETE FROM {$table} WHERE user_id = :user_id AND {$entityColumn} = :entity_id", ['user_id' => $userId, 'entity_id' => $entityId]);
        return false;
    }

    private function postVoteCounts(int $postId): array
    {
        $likeCount = (int) ($this->one('SELECT COUNT(*) AS total FROM post_likes WHERE post_id = :id', ['id' => $postId])['total'] ?? 0);
        $dislikeCount = (int) ($this->one('SELECT COUNT(*) AS total FROM post_dislikes WHERE post_id = :id', ['id' => $postId])['total'] ?? 0);
        return [$likeCount, $dislikeCount];
    }

    private function commentVoteCounts(int $commentId): array
    {
        $likeCount = (int) ($this->one('SELECT COUNT(*) AS total FROM comment_likes WHERE comment_id = :id', ['id' => $commentId])['total'] ?? 0);
        $dislikeCount = (int) ($this->one('SELECT COUNT(*) AS total FROM comment_dislikes WHERE comment_id = :id', ['id' => $commentId])['total'] ?? 0);
        return [$likeCount, $dislikeCount];
    }

    private function adjustPostAuthorReputation(int $postId, int $voterId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $this->execute(
            'UPDATE users u JOIN posts p ON p.id = :post_id SET u.reputation = u.reputation + :delta WHERE u.id = p.author_id AND p.author_id <> :voter_id',
            ['post_id' => $postId, 'delta' => $delta, 'voter_id' => $voterId]
        );
    }

    private function adjustCommentAuthorReputation(int $commentId, int $voterId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $this->execute(
            'UPDATE users u JOIN comments c ON c.id = :comment_id SET u.reputation = u.reputation + :delta WHERE u.id = c.author_id AND c.author_id <> :voter_id',
            ['comment_id' => $commentId, 'delta' => $delta, 'voter_id' => $voterId]
        );
    }

    private function toggle(string $table, int $userId, int $postId, string $stateKey): array
    {
        $this->db->beginTransaction();
        try {
            $exists = $this->one("SELECT user_id FROM {$table} WHERE user_id = :user_id AND post_id = :post_id FOR UPDATE", ['user_id' => $userId, 'post_id' => $postId]);
            if ($exists === null) {
                $inserted = $this->execute("INSERT INTO {$table} (user_id, post_id) SELECT :user_id, id FROM posts WHERE id = :post_id AND status = 'published'", ['user_id' => $userId, 'post_id' => $postId]);
                if ($inserted === 0) {
                    throw new DomainException('Bài viết không tồn tại.');
                }
                $state = true;
            } else {
                $this->execute("DELETE FROM {$table} WHERE user_id = :user_id AND post_id = :post_id", ['user_id' => $userId, 'post_id' => $postId]);
                $state = false;
            }
            $count = (int) ($this->one("SELECT COUNT(*) AS total FROM {$table} WHERE post_id = :post_id", ['post_id' => $postId])['total'] ?? 0);
            $this->db->commit();
            return [$stateKey => $state, 'count' => $count];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
