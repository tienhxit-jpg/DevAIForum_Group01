<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Interaction;
use App\Models\Notification;
use App\Models\Post;

final class InteractionController extends Controller
{
    public function like(Request $request, string $postId): never
    {
        $userId = $this->member($request, 'post.like');
        $this->action(function () use ($postId, $userId): array {
            $result = (new Interaction())->toggleLike($userId, (int) $postId);
            if ($result['liked']) {
                $post = (new Post())->findDetailed((int) $postId, $userId);
                if ($post !== null) {
                    (new Notification())->create((int) $post['author_id'], $userId, (int) $postId, null, 'like', 'Có người thích bài viết của bạn.');
                }
            }
            return $result;
        });
    }

    public function dislike(Request $request, string $postId): never
    {
        $userId = $this->member($request, 'post.like');
        $this->action(fn (): array => (new Interaction())->toggleDislike($userId, (int) $postId));
    }

    public function bookmark(Request $request, string $postId): never
    {
        $userId = $this->member($request, 'post.bookmark');
        $this->action(fn (): array => (new Interaction())->toggleBookmark($userId, (int) $postId));
    }

    public function pin(Request $request, string $postId): never
    {
        $userId = $this->member($request, 'post.bookmark');
        $this->action(fn (): array => (new Interaction())->togglePin($userId, (int) $postId));
    }

    public function commentLike(Request $request, string $commentId): never
    {
        $userId = $this->member($request, 'post.like');
        $this->action(fn (): array => (new Interaction())->toggleCommentLike($userId, (int) $commentId));
    }

    public function commentDislike(Request $request, string $commentId): never
    {
        $userId = $this->member($request, 'post.like');
        $this->action(fn (): array => (new Interaction())->toggleCommentDislike($userId, (int) $commentId));
    }
}
