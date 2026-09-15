<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DomainException;
use Throwable;

final class Report extends Model
{
    private const REASONS = ['spam', 'harassment', 'misinformation', 'copyright', 'other'];

    public function create(int $reporterId, array $data): int
    {
        $postId = !empty($data['post_id']) ? (int) $data['post_id'] : null;
        $commentId = !empty($data['comment_id']) ? (int) $data['comment_id'] : null;
        if (($postId === null) === ($commentId === null)) {
            throw new DomainException('Báo cáo phải nhắm đến đúng một nội dung.');
        }
        $reason = (string) ($data['reason'] ?? 'other');
        if (!in_array($reason, self::REASONS, true)) {
            throw new DomainException('Lý do báo cáo không hợp lệ.');
        }
        $existing = $this->one('SELECT id FROM reports WHERE reporter_id = :reporter_id AND ((post_id = :post_id) OR (comment_id = :comment_id)) AND status IN (\'pending\', \'reviewing\')', [
            'reporter_id' => $reporterId, 'post_id' => $postId, 'comment_id' => $commentId,
        ]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->execute('INSERT INTO reports (reporter_id, post_id, comment_id, reason, details) VALUES (:reporter_id, :post_id, :comment_id, :reason, :details)', [
            'reporter_id' => $reporterId, 'post_id' => $postId, 'comment_id' => $commentId,
            'reason' => $reason, 'details' => trim((string) ($data['details'] ?? '')) ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function queue(string $status = 'pending', int $page = 1): array
    {
        $allowed = ['pending', 'reviewing', 'resolved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $offset = (max(1, $page) - 1) * 30;
        return $this->all("SELECT r.*, reporter.username AS reporter_username, handler.username AS handler_username, p.title AS post_title, c.content_html AS comment_content, COALESCE(r.post_id, c.post_id) AS target_post_id FROM reports r JOIN users reporter ON reporter.id = r.reporter_id LEFT JOIN users handler ON handler.id = r.handled_by LEFT JOIN posts p ON p.id = r.post_id LEFT JOIN comments c ON c.id = r.comment_id WHERE r.status = :status ORDER BY r.created_at ASC LIMIT 30 OFFSET {$offset}", ['status' => $status]);
    }

    public function resolve(int $id, int $moderatorId, string $status, string $note): void
    {
        if (!in_array($status, ['resolved', 'rejected'], true)) {
            throw new DomainException('Trạng thái xử lý không hợp lệ.');
        }
        $this->db->beginTransaction();
        try {
            $this->execute('UPDATE reports SET status = :status, handled_by = :handled_by, resolution_note = :note, handled_at = CURRENT_TIMESTAMP WHERE id = :id', [
                'status' => $status, 'handled_by' => $moderatorId, 'note' => trim($note), 'id' => $id,
            ]);
            $this->execute("INSERT INTO moderation_logs (moderator_id, action, reason) VALUES (:moderator_id, 'resolve_report', :reason)", ['moderator_id' => $moderatorId, 'reason' => "Report #{$id}: {$note}"]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
