<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Sanitizer;
use App\Helpers\Slugger;
use DomainException;
use Throwable;

final class Post extends Model
{
    public function feed(array $filters = [], ?int $viewerId = null): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $conditions = ["p.status = 'published'"];
        $parameters = [
            'viewer_like_id' => $viewerId ?? 0,
            'viewer_dislike_id' => $viewerId ?? 0,
            'viewer_bookmark_id' => $viewerId ?? 0,
            'viewer_pin_id' => $viewerId ?? 0,
        ];
        if (!empty($filters['category'])) {
            if (is_numeric($filters['category'])) {
                $conditions[] = 'c.id = :category';
            } else {
                $conditions[] = 'c.slug = :category';
            }
            $parameters['category'] = $filters['category'];
        }
        if (!empty($filters['tag'])) {
            if (is_numeric($filters['tag'])) {
                $conditions[] = 'EXISTS (SELECT 1 FROM post_tags fpt WHERE fpt.post_id = p.id AND fpt.tag_id = :tag)';
            } else {
                $conditions[] = 'EXISTS (SELECT 1 FROM post_tags fpt JOIN tags ft ON ft.id = fpt.tag_id WHERE fpt.post_id = p.id AND ft.slug = :tag)';
            }
            $parameters['tag'] = $filters['tag'];
        }
        if (!empty($filters['type'])) {
            $conditions[] = 'p.post_type = :type';
            $parameters['type'] = $filters['type'];
        }
        if (!empty($filters['author'])) {
            $conditions[] = 'u.username = :author';
            $parameters['author'] = $filters['author'];
        }
        if (($filters['solved'] ?? null) === '1') {
            $conditions[] = 'p.best_answer_comment_id IS NOT NULL';
        }
        if (($filters['unanswered'] ?? null) === '1') {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM comments fc WHERE fc.post_id = p.id AND fc.status = 'visible')";
        }
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            preg_match_all('/[\p{L}\p{N}]{2,}/u', (string) $filters['q'], $matches);
            $terms = array_values(array_unique($matches[0] ?? []));
            if ($terms === []) {
                $conditions[] = '1 = 0';
            } else {
                $conditions[] = 'MATCH(p.title, p.content_html) AGAINST (:search IN NATURAL LANGUAGE MODE)';
                $parameters['search'] = implode(' ', $terms);
            }
        }
        $sort = match ($filters['sort'] ?? 'new') {
            'popular' => 'viewer_pinned DESC, like_count DESC, comment_count DESC, p.created_at DESC',
            'oldest' => 'viewer_pinned DESC, p.created_at ASC',
            default => 'viewer_pinned DESC, p.is_pinned DESC, p.created_at DESC',
        };
        $sql = 'SELECT p.id, p.author_id, p.category_id, p.best_answer_comment_id, p.title, p.slug, p.content_html, p.post_type, p.is_pinned, p.is_locked, p.view_count, p.created_at, p.updated_at, u.username, u.display_name, u.avatar_path, u.reputation, r.name AS role_name, c.name AS category_name, c.slug AS category_slug, (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM post_dislikes pd WHERE pd.post_id = p.id) AS dislike_count, (SELECT COUNT(*) FROM comments co WHERE co.post_id = p.id AND co.status = \'visible\') AS comment_count, EXISTS(SELECT 1 FROM post_likes vpl WHERE vpl.post_id = p.id AND vpl.user_id = :viewer_like_id) AS viewer_liked, EXISTS(SELECT 1 FROM post_dislikes vpd WHERE vpd.post_id = p.id AND vpd.user_id = :viewer_dislike_id) AS viewer_disliked, EXISTS(SELECT 1 FROM bookmarks vb WHERE vb.post_id = p.id AND vb.user_id = :viewer_bookmark_id) AS viewer_bookmarked, EXISTS(SELECT 1 FROM post_pins vpp WHERE vpp.post_id = p.id AND vpp.user_id = :viewer_pin_id) AS viewer_pinned FROM posts p JOIN users u ON u.id = p.author_id JOIN roles r ON r.id = u.role_id JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $conditions) . " ORDER BY {$sort} LIMIT {$limit} OFFSET {$offset}";
        $posts = $this->all($sql, $parameters);
        return $this->attachTags($posts);
    }

    public function create(int $authorId, array $data, array $tagIds = [], array $images = []): int
    {
        $this->db->beginTransaction();
        try {
            $slug = $this->uniqueSlug((string) $data['title']);
            $this->execute('INSERT INTO posts (author_id, category_id, title, slug, content_html, post_type, status) VALUES (:author_id, :category_id, :title, :slug, :content_html, :post_type, :status)', [
                'author_id' => $authorId,
                'category_id' => (int) $data['category_id'],
                'title' => trim((string) $data['title']),
                'slug' => $slug,
                'content_html' => Sanitizer::richText((string) $data['content_html']),
                'post_type' => $data['post_type'] ?? 'discussion',
                'status' => $data['status'] ?? 'published',
            ]);
            $postId = (int) $this->db->lastInsertId();
            $this->syncTags($postId, $tagIds);
            foreach ($images as $position => $image) {
                $this->execute('INSERT INTO post_images (post_id, file_path, original_name, mime_type, file_size, sort_order) VALUES (:post_id, :file_path, :original_name, :mime_type, :file_size, :sort_order)', [
                    'post_id' => $postId, 'file_path' => $image['path'], 'original_name' => $image['original_name'],
                    'mime_type' => $image['mime_type'], 'file_size' => $image['file_size'], 'sort_order' => $position,
                ]);
            }
            $this->db->commit();
            return $postId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function findDetailed(int $id, ?int $viewerId = null): ?array
    {
        $post = $this->one('SELECT p.*, u.username, u.display_name, u.avatar_path, u.reputation, r.name AS role_name, c.name AS category_name, c.slug AS category_slug, (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM post_dislikes pd WHERE pd.post_id = p.id) AS dislike_count, (SELECT COUNT(*) FROM comments co WHERE co.post_id = p.id AND co.status = \'visible\') AS comment_count, EXISTS(SELECT 1 FROM post_likes vpl WHERE vpl.post_id = p.id AND vpl.user_id = :viewer_like_id) AS viewer_liked, EXISTS(SELECT 1 FROM post_dislikes vpd WHERE vpd.post_id = p.id AND vpd.user_id = :viewer_dislike_id) AS viewer_disliked, EXISTS(SELECT 1 FROM bookmarks vb WHERE vb.post_id = p.id AND vb.user_id = :viewer_bookmark_id) AS viewer_bookmarked, EXISTS(SELECT 1 FROM post_pins vpp WHERE vpp.post_id = p.id AND vpp.user_id = :viewer_pin_id) AS viewer_pinned FROM posts p JOIN users u ON u.id = p.author_id JOIN roles r ON r.id = u.role_id JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.status IN (\'published\', \'hidden\')', [
            'id' => $id,
            'viewer_like_id' => $viewerId ?? 0,
            'viewer_dislike_id' => $viewerId ?? 0,
            'viewer_bookmark_id' => $viewerId ?? 0,
            'viewer_pin_id' => $viewerId ?? 0,
        ]);
        if ($post === null) {
            return null;
        }
        $post['tags'] = $this->all('SELECT t.id, t.name, t.slug FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = :id ORDER BY t.name', ['id' => $id]);
        $post['images'] = $this->all('SELECT id, file_path, original_name, mime_type, file_size FROM post_images WHERE post_id = :id ORDER BY sort_order, id', ['id' => $id]);
        return $post;
    }

    public function incrementViews(int $id): void
    {
        $this->execute('UPDATE posts SET view_count = view_count + 1 WHERE id = :id AND status = \'published\'', ['id' => $id]);
    }

    public function byOwner(int $ownerId, int $page = 1, int $limit = 20): array
    {
        $limit = min(50, max(1, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        return $this->all("SELECT p.id, p.title, p.slug, p.post_type, p.status, p.is_pinned, p.is_locked, p.view_count, p.created_at, p.updated_at, c.name AS category_name, (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM comments co WHERE co.post_id = p.id AND co.status = 'visible') AS comment_count FROM posts p JOIN categories c ON c.id = p.category_id WHERE p.author_id = :owner_id AND p.status <> 'deleted' ORDER BY p.updated_at DESC LIMIT {$limit} OFFSET {$offset}", ['owner_id' => $ownerId]);
    }

    public function findOwned(int $id, int $ownerId): ?array
    {
        $post = $this->one('SELECT * FROM posts WHERE id = :id AND author_id = :owner_id AND status <> \'deleted\'', [
            'id' => $id,
            'owner_id' => $ownerId,
        ]);
        if ($post === null) {
            return null;
        }
        $post['tags'] = $this->all('SELECT t.id, t.name, t.slug FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = :id ORDER BY t.name', ['id' => $id]);
        $post['images'] = $this->all('SELECT id, file_path, original_name, mime_type, file_size FROM post_images WHERE post_id = :id ORDER BY sort_order, id', ['id' => $id]);
        return $post;
    }

    public function update(int $id, int $actorId, array $data, array $tagIds, bool $canModerate = false): void
    {
        $post = $this->one('SELECT author_id FROM posts WHERE id = :id AND status <> \'deleted\'', ['id' => $id]);
        if ($post === null || (!$canModerate && (int) $post['author_id'] !== $actorId)) {
            throw new DomainException('Bạn không có quyền sửa bài viết này.');
        }
        $this->db->beginTransaction();
        try {
            $candidateStatus = (string) ($data['status'] ?? 'published');
            $status = in_array($candidateStatus, ['draft', 'published'], true) ? $candidateStatus : 'published';
            $this->execute('UPDATE posts SET category_id = :category_id, title = :title, content_html = :content_html, post_type = :post_type, status = :status, deleted_at = NULL WHERE id = :id', [
                'id' => $id, 'category_id' => (int) $data['category_id'], 'title' => trim((string) $data['title']),
                'content_html' => Sanitizer::richText((string) $data['content_html']), 'post_type' => $data['post_type'] ?? 'discussion',
                'status' => $status,
            ]);
            $this->syncTags($id, $tagIds);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function softDelete(int $id, int $actorId, bool $canModerate = false): void
    {
        $post = $this->one('SELECT author_id FROM posts WHERE id = :id', ['id' => $id]);
        if ($post === null || (!$canModerate && (int) $post['author_id'] !== $actorId)) {
            throw new DomainException('Bạn không có quyền xóa bài viết này.');
        }
        $this->execute("UPDATE posts SET status = 'deleted', deleted_at = CURRENT_TIMESTAMP WHERE id = :id", ['id' => $id]);
    }

    public function selectBestAnswer(int $postId, int $authorId, int $commentId): void
    {
        $valid = $this->one('SELECT p.id FROM posts p JOIN comments c ON c.id = :comment_id AND c.post_id = p.id AND c.status = \'visible\' WHERE p.id = :post_id AND p.author_id = :author_id AND p.post_type = \'question\'', ['comment_id' => $commentId, 'post_id' => $postId, 'author_id' => $authorId]);
        if ($valid === null) {
            throw new DomainException('Không thể chọn bình luận này làm câu trả lời hay nhất.');
        }
        $this->db->beginTransaction();
        try {
            $current = $this->one('SELECT best_answer_comment_id FROM posts WHERE id = :id FOR UPDATE', ['id' => $postId]);
            $previousCommentId = $current['best_answer_comment_id'] !== null ? (int) $current['best_answer_comment_id'] : null;
            if ($previousCommentId !== null && $previousCommentId !== $commentId) {
                $this->execute(
                    'UPDATE users u JOIN comments c ON c.author_id = u.id SET u.reputation = u.reputation - 15 WHERE c.id = :id',
                    ['id' => $previousCommentId]
                );
            }
            $this->execute('UPDATE posts SET best_answer_comment_id = :comment_id WHERE id = :post_id', ['comment_id' => $commentId, 'post_id' => $postId]);
            if ($previousCommentId !== $commentId) {
                $this->execute(
                    'UPDATE users u JOIN comments c ON c.author_id = u.id SET u.reputation = u.reputation + 15 WHERE c.id = :id',
                    ['id' => $commentId]
                );
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Slugger::make($title);
        $slug = $base;
        $suffix = 2;
        while ($this->one('SELECT id FROM posts WHERE slug = :slug', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }

    private function syncTags(int $postId, array $tagIds): void
    {
        $this->execute('DELETE FROM post_tags WHERE post_id = :post_id', ['post_id' => $postId]);
        foreach (array_unique(array_map('intval', array_slice($tagIds, 0, 5))) as $tagId) {
            $this->execute('INSERT INTO post_tags (post_id, tag_id) SELECT :post_id, id FROM tags WHERE id = :tag_id', ['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    private function attachTags(array $posts): array
    {
        foreach ($posts as &$post) {
            $post['tags'] = $this->all('SELECT t.id, t.name, t.slug FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = :id ORDER BY t.name', ['id' => $post['id']]);
        }
        return $posts;
    }
}
