<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Upload;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;

final class ProfileController extends Controller
{
    public function show(Request $request, string $id): never
    {
        $user = (new User())->findPublic($id);
        if ($user === null) {
            Response::json(['error' => 'Người dùng không tồn tại.'], 404);
        }
        Response::json(['data' => $user]);
    }

    public function posts(Request $request, string $username): never
    {
        Response::json(['data' => (new Post())->feed([
            'author' => $username, 'page' => $request->query('page', 1), 'limit' => $request->query('limit', 20),
        ], Auth::id())]);
    }

    public function comments(Request $request, string $username): never
    {
        Response::json(['data' => (new Comment())->listByAuthor($username, (int) $request->query('page', 1))]);
    }

    public function liked(Request $request): never
    {
        $userId = Auth::requireLogin();
        Response::json(['data' => (new Interaction())->likedPosts($userId, (int) $request->query('page', 1))]);
    }

    public function disliked(Request $request): never
    {
        $userId = Auth::requireLogin();
        Response::json(['data' => (new Interaction())->dislikedPosts($userId, (int) $request->query('page', 1))]);
    }

    public function update(Request $request): never
    {
        $userId = $this->member($request);
        $data = $this->requireValid($request, ['display_name' => ['required', 'min:2', 'max:100'], 'bio' => ['max:1000']]);
        $this->action(function () use ($userId, $data): array {
            (new User())->updateProfile($userId, $data);
            return ['message' => 'Đã cập nhật hồ sơ.'];
        });
    }

    public function password(Request $request): never
    {
        $userId = $this->member($request);
        $data = $this->requireValid($request, [
            'current_password' => ['required'], 'new_password' => ['required', 'password', 'min:8', 'max:72'],
        ]);
        $this->action(function () use ($userId, $data): array {
            if (!(new User())->changePassword($userId, (string) $data['current_password'], (string) $data['new_password'])) {
                throw new \DomainException('Mật khẩu hiện tại không chính xác.');
            }
            return ['message' => 'Đã đổi mật khẩu.'];
        });
    }

    public function avatar(Request $request): never
    {
        $userId = $this->member($request);
        $this->action(function () use ($request, $userId): array {
            $file = $request->file('avatar');
            if ($file === null) {
                throw new \DomainException('Vui lòng chọn ảnh đại diện.');
            }
            $image = Upload::image($file, dirname(__DIR__, 2) . '/public/uploads/avatars', '/uploads/avatars');
            (new User())->setAvatar($userId, $image['path']);
            return ['message' => 'Đã cập nhật ảnh đại diện.', 'path' => $image['path']];
        });
    }

    public function bookmarks(Request $request): never
    {
        $userId = Auth::requireLogin();
        Response::json(['data' => (new Interaction())->bookmarks($userId, (int) $request->query('page', 1))]);
    }

    public function pins(Request $request): never
    {
        $userId = Auth::requireLogin();
        Response::json(['data' => (new Interaction())->pins($userId, (int) $request->query('page', 1))]);
    }

    public function ownPosts(Request $request): never
    {
        $userId = Auth::requireLogin();
        Response::json(['data' => (new Post())->byOwner($userId, (int) $request->query('page', 1))]);
    }

    public function ownPost(Request $request, string $id): never
    {
        $userId = Auth::requireLogin();
        $post = (new Post())->findOwned((int) $id, $userId);
        if ($post === null) {
            Response::json(['error' => 'Bài viết không tồn tại hoặc không thuộc tài khoản của bạn.'], 404);
        }
        Response::json(['data' => $post]);
    }
}
