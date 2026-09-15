<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;

final class CommentController extends Controller
{
    public function create(Request $request, string $postId): never
    {
        $userId = $this->member($request, 'comment.create');
        $data = $this->requireValid($request, ['content_html' => ['required', 'min:2']]);
        $this->action(function () use ($postId, $userId, $data): array {
            $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
            $commentId = (new Comment())->create((int) $postId, $userId, (string) $data['content_html'], $parentId);
            $post = (new Post())->findDetailed((int) $postId, $userId);
            if ($post !== null) {
                $recipient = (int) $post['author_id'];
                $type = 'comment';
                if ($parentId !== null) {
                    $parent = (new Comment())->find($parentId);
                    if ($parent !== null) {
                        $recipient = (int) $parent['author_id'];
                        $type = 'reply';
                    }
                }
                (new Notification())->create($recipient, $userId, (int) $postId, $commentId, $type, $type === 'reply' ? 'Có phản hồi mới cho bình luận của bạn.' : 'Có bình luận mới cho bài viết của bạn.');
            }
            return ['message' => 'Đã gửi bình luận.', 'id' => $commentId];
        });
    }

    public function update(Request $request, string $id): never
    {
        $userId = $this->member($request, 'comment.update_own');
        $data = $this->requireValid($request, ['content_html' => ['required', 'min:2']]);
        $this->action(function () use ($id, $userId, $data): array {
            (new Comment())->update((int) $id, $userId, (string) $data['content_html']);
            return ['message' => 'Đã cập nhật bình luận.'];
        });
    }

    public function delete(Request $request, string $id): never
    {
        $userId = $this->member($request, 'comment.delete_own');
        $this->action(function () use ($id, $userId): array {
            (new Comment())->softDelete((int) $id, $userId);
            return ['message' => 'Đã xóa bình luận.'];
        });
    }
}
