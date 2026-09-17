<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostAppeal;
use App\Helpers\Upload;

final class PostController extends Controller
{
    public function feed(Request $request): never
    {
        $filters = [
            'page' => $request->query('page', 1), 'limit' => $request->query('limit', 20),
            'sort' => $request->query('sort', 'new'), 'category' => $request->query('category'),
            'tag' => $request->query('tag'), 'type' => $request->query('type'),
            'solved' => $request->query('solved'), 'q' => $request->query('q'),
            'author' => $request->query('author'), 'unanswered' => $request->query('unanswered'),
        ];
        $posts = (new Post())->feed($filters, Auth::id());
        Response::json(['data' => $posts, 'page' => max(1, (int) $filters['page']), 'has_more' => count($posts) === min(50, max(1, (int) $filters['limit']))]);
    }

    public function show(Request $request, string $id): never
    {
        $post = new Post();
        $post->incrementViews((int) $id);
        $viewerId = Auth::id();
        $detail = $post->findDetailed((int) $id, $viewerId);
        $isOwner = $detail !== null && $viewerId !== null && (int) $detail['author_id'] === $viewerId;
        if ($detail === null || ($detail['status'] === 'hidden' && !$isOwner && !Auth::can('post.moderate'))) {
            Response::json(['error' => 'Bài viết không tồn tại.'], 404);
        }
        if ($detail['status'] === 'hidden' && $isOwner) {
            $detail['appeal'] = (new PostAppeal())->latestForPost((int) $id);
        }
        $detail['comments'] = (new Comment())->listByPost((int) $id, $viewerId);
        Response::json(['data' => $detail]);
    }

    public function appeal(Request $request, string $id): never
    {
        $userId = $this->member($request, 'post.appeal');
        $data = $this->requireValid($request, ['reason' => ['required', 'min:20', 'max:2000']]);
        $this->action(function () use ($id, $userId, $data): array {
            $appealId = (new PostAppeal())->create((int) $id, $userId, (string) $data['reason']);
            return ['message' => 'Đã gửi kháng cáo. Quản trị viên sẽ xem xét sớm nhất.', 'id' => $appealId];
        });
    }

    public function create(Request $request): never
    {
        $userId = $this->member($request, 'post.create');
        $data = $this->requireValid($request, [
            'category_id' => ['required', 'integer'], 'title' => ['required', 'min:10', 'max:255'],
            'content_html' => ['required', 'min:10'], 'post_type' => ['required', 'in:discussion,question,resource,job'],
            'status' => ['in:draft,published'],
        ]);
        $this->action(function () use ($request, $userId, $data): array {
            $images = [];
            if ($request->file('images') !== null) {
                $images = Upload::images($request->file('images'), dirname(__DIR__, 2) . '/public/uploads/posts', '/uploads/posts');
            }
            try {
                $postId = (new Post())->create($userId, $data, $this->tagIds($data), $images);
            } catch (\Throwable $exception) {
                $uploadRoot = dirname(__DIR__, 2) . '/public/uploads/posts';
                foreach ($images as $image) {
                    $candidate = $uploadRoot . DIRECTORY_SEPARATOR . basename((string) $image['path']);
                    if (is_file($candidate)) {
                        unlink($candidate);
                    }
                }
                throw $exception;
            }
            return ['message' => 'Đã tạo bài viết.', 'id' => $postId];
        });
    }

    public function update(Request $request, string $id): never
    {
        $userId = $this->member($request, 'post.update_own');
        $data = $this->requireValid($request, [
            'category_id' => ['required', 'integer'], 'title' => ['required', 'min:10', 'max:255'],
            'content_html' => ['required', 'min:10'], 'post_type' => ['required', 'in:discussion,question,resource,job'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $this->action(function () use ($id, $userId, $data): array {
            (new Post())->update((int) $id, $userId, $data, $this->tagIds($data));
            return ['message' => 'Đã cập nhật bài viết.'];
        });
    }

    public function delete(Request $request, string $id): never
    {
        $userId = $this->member($request, 'post.delete_own');
        $this->action(function () use ($id, $userId): array {
            (new Post())->softDelete((int) $id, $userId);
            return ['message' => 'Đã xóa bài viết.'];
        });
    }

    public function bestAnswer(Request $request, string $id): never
    {
        $userId = $this->member($request, 'post.select_best_answer');
        $data = $this->requireValid($request, ['comment_id' => ['required', 'integer']]);
        $this->action(function () use ($id, $userId, $data): array {
            $commentId = (int) $data['comment_id'];
            (new Post())->selectBestAnswer((int) $id, $userId, $commentId);
            $comment = (new Comment())->find($commentId);
            if ($comment !== null) {
                (new Notification())->create((int) $comment['author_id'], $userId, (int) $id, $commentId, 'best_answer', 'Câu trả lời của bạn đã được chọn là hay nhất.');
            }
            return ['message' => 'Đã chọn câu trả lời hay nhất.'];
        });
    }

    private function tagIds(array $data): array
    {
        $tags = $data['tag_ids'] ?? [];
        if (is_string($tags)) {
            $tags = array_filter(explode(',', $tags));
        }
        return is_array($tags) ? array_map('intval', $tags) : [];
    }
}
