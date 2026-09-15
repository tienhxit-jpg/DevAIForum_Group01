<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Helpers\Slugger;
use DomainException;
use Throwable;

final class Taxonomy extends Model
{
    public function categories(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE c.is_active = 1' : '';
        return $this->all("SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id AND p.status = 'published') AS post_count FROM categories c {$where} ORDER BY c.sort_order, c.name");
    }

    public function tags(): array
    {
        return $this->all("SELECT t.*, (SELECT COUNT(*) FROM post_tags pt JOIN posts p ON p.id = pt.post_id WHERE pt.tag_id = t.id AND p.status = 'published') AS post_count FROM tags t ORDER BY t.name");
    }

    public function saveCategory(?int $id, array $data): int
    {
        $slug = Slugger::make((string) $data['name']);
        if ($id === null) {
            $this->execute('INSERT INTO categories (parent_id, name, slug, description, sort_order, is_active) VALUES (:parent_id, :name, :slug, :description, :sort_order, :is_active)', [
                'parent_id' => $data['parent_id'] ?? null,
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (int) ($data['is_active'] ?? 1),
            ]);
            return (int) $this->db->lastInsertId();
        }
        $this->execute('UPDATE categories SET parent_id = :parent_id, name = :name, slug = :slug, description = :description, sort_order = :sort_order, is_active = :is_active WHERE id = :id', [
            'id' => $id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (int) ($data['is_active'] ?? 1),
        ]);
        return $id;
    }

    public function deleteCategory(int $id): void
    {
        $count = (int) ($this->one('SELECT COUNT(*) AS total FROM posts WHERE category_id = :id', ['id' => $id])['total'] ?? 0);
        if ($count > 0) {
            throw new DomainException('Chỉ có thể xóa chuyên mục trống.');
        }
        $this->execute('DELETE FROM categories WHERE id = :id', ['id' => $id]);
    }

    public function saveTag(?int $id, array $data): int
    {
        $values = ['name' => trim((string) $data['name']), 'slug' => Slugger::make((string) $data['name']), 'description' => trim((string) ($data['description'] ?? '')) ?: null];
        if ($id === null) {
            $this->execute('INSERT INTO tags (name, slug, description) VALUES (:name, :slug, :description)', $values);
            return (int) $this->db->lastInsertId();
        }
        $values['id'] = $id;
        $this->execute('UPDATE tags SET name = :name, slug = :slug, description = :description WHERE id = :id', $values);
        return $id;
    }

    public function deleteTag(int $id): void
    {
        $this->execute('DELETE FROM tags WHERE id = :id', ['id' => $id]);
    }

    public function mergeTag(int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            throw new DomainException('Thẻ nguồn và thẻ đích phải khác nhau.');
        }
        $this->db->beginTransaction();
        try {
            if ($this->one('SELECT id FROM tags WHERE id = :id', ['id' => $sourceId]) === null
                || $this->one('SELECT id FROM tags WHERE id = :id', ['id' => $targetId]) === null) {
                throw new DomainException('Thẻ nguồn hoặc thẻ đích không tồn tại.');
            }
            $this->execute('INSERT IGNORE INTO post_tags (post_id, tag_id) SELECT post_id, :target_id FROM post_tags WHERE tag_id = :source_id', [
                'target_id' => $targetId,
                'source_id' => $sourceId,
            ]);
            $this->execute('DELETE FROM tags WHERE id = :id', ['id' => $sourceId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
