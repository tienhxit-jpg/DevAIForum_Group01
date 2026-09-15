<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Sanitizer;
use DomainException;

final class Comment extends Model
{
    public function find(int $id): ?array
    {
        return $this->one('SELECT c.*, u.username, u.display_name FROM comments c JOIN users u ON u.id = c.author_id WHERE c.id = :id', ['id' => $id]);
    }

    public function create(int $postId, int $authorId, string $content, ?int $parentId = null): int
    {
        $post = $this->one("SELECT id, is_locked FROM posts WHERE id = :id AND status = 'published'", ['id' => $postId]);
        if ($post === null || (int) $post['is_locked'] === 1) {
            throw new DomainException('Chủ đề không tồn tại hoặc đã bị khóa.');
        }
        if ($parentId !== null && $this->one('SELECT id FROM comments WHERE id = :id AND post_id = :post_id AND status = \'visible\'', ['id' => $parentId, 'post_id' => $postId]) === null) {
            throw new DomainException('Bình luận cha không hợp lệ.');
        }
        $clean = Sanitizer::richText($content);
        if (trim(strip_tags($clean)) === '') {
            throw new DomainException('Nội dung bình luận không được để trống.');
        }
        $this->execute('INSERT INTO comments (post_id, author_id, parent_id, content_html) VALUES (:post_id, :author_id, :parent_id, :content_html)', [
            'post_id' => $postId, 'author_id' => $authorId, 'parent_id' => $parentId, 'content_html' => $clean,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listByPost(int $postId, ?int $viewerId = null): array
    {
        $comments = $this->all(
            'SELECT c.*, u.username, u.display_name, u.avatar_path, u.reputation, r.name AS role_name, '
            . '(SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count, '
            . '(SELECT COUNT(*) FROM comment_dislikes cd WHERE cd.comment_id = c.id) AS dislike_count, '
            . 'EXISTS(SELECT 1 FROM comment_likes vcl WHERE vcl.comment_id = c.id AND vcl.user_id = :viewer_like_id) AS viewer_liked, '
            . 'EXISTS(SELECT 1 FROM comment_dislikes vcd WHERE vcd.comment_id = c.id AND vcd.user_id = :viewer_dislike_id) AS viewer_disliked '
            . 'FROM comments c JOIN users u ON u.id = c.author_id JOIN roles r ON r.id = u.role_id WHERE c.post_id = :post_id AND c.status <> \'hidden\' ORDER BY c.created_at, c.id',
            ['post_id' => $postId, 'viewer_like_id' => $viewerId ?? 0, 'viewer_dislike_id' => $viewerId ?? 0]
        );
        $byParent = [];
        foreach ($comments as $comment) {
            $key = $comment['parent_id'] === null ? 0 : (int) $comment['parent_id'];
            $byParent[$key][] = $comment;
        }
        $build = function (int $parentId, int $depth = 0) use (&$build, &$byParent): array {
            $result = [];
            foreach ($byParent[$parentId] ?? [] as $comment) {
                $comment['depth'] = $depth;
                $comment['children'] = $build((int) $comment['id'], $depth + 1);
                $result[] = $comment;
            }
            return $result;
        };
        return $build(0);
    }

    public function listByAuthor(string $username, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all(
            'SELECT c.id, c.post_id, c.content_html, c.created_at, p.title AS post_title, p.slug AS post_slug,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count,
                (SELECT COUNT(*) FROM comment_dislikes cd WHERE cd.comment_id = c.id) AS dislike_count
             FROM comments c
             JOIN users u ON u.id = c.author_id
             JOIN posts p ON p.id = c.post_id
             WHERE u.username = :username AND c.status = \'visible\' AND p.status = \'published\'
             ORDER BY c.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            ['username' => $username]
        );
    }

    public function update(int $id, int $actorId, string $content, bool $canModerate = false): void
    {
        $comment = $this->one('SELECT author_id FROM comments WHERE id = :id AND status <> \'deleted\'', ['id' => $id]);
        if ($comment === null || (!$canModerate && (int) $comment['author_id'] !== $actorId)) {
            throw new DomainException('Bạn không có quyền sửa bình luận này.');
        }
        $this->execute('UPDATE comments SET content_html = :content WHERE id = :id', ['content' => Sanitizer::richText($content), 'id' => $id]);
    }

    public function softDelete(int $id, int $actorId, bool $canModerate = false): void
    {
        $comment = $this->one('SELECT author_id, post_id FROM comments WHERE id = :id', ['id' => $id]);
        if ($comment === null || (!$canModerate && (int) $comment['author_id'] !== $actorId)) {
            throw new DomainException('Bạn không có quyền xóa bình luận này.');
        }
        $this->db->beginTransaction();
        try {
            $this->execute("UPDATE posts SET best_answer_comment_id = NULL WHERE best_answer_comment_id = :id", ['id' => $id]);
            $this->execute("UPDATE comments SET status = 'deleted', content_html = '<p>[Bình luận đã bị xóa]</p>', deleted_at = CURRENT_TIMESTAMP WHERE id = :id", ['id' => $id]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
