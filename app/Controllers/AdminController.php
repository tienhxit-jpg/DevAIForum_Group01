<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Admin;
use App\Models\Analytics;

final class AdminController extends Controller
{
    public function panel(Request $request): never
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $baseUrl = preg_replace('#/public$#', '', $scriptDir);
        if ($baseUrl === '/' || $baseUrl === null) {
            $baseUrl = '';
        }

        $user = Auth::user();
        if ($user === null || $user['role_name'] !== 'admin') {
            Response::redirect($baseUrl . '/');
        }

        $csrfToken = Csrf::token();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Quản Trị - DevAI Hub</title>
    <meta name="base-url" content="{$baseUrl}">
    <meta name="csrf-token" content="{$csrfToken}">
    <link rel="icon" type="image/svg+xml" href="{$baseUrl}/assets/img/logo-icon.svg">
    <link rel="stylesheet" href="{$baseUrl}/assets/css/admin-theme.css">
</head>
<body data-base-url="{$baseUrl}">
    <div id="admin-app"></div>
    <div id="admin-modal-root"></div>
    <div id="admin-toast-container" class="admin-toast-container" aria-live="polite"></div>

    <script src="{$baseUrl}/assets/js/icons.js"></script>
    <script src="{$baseUrl}/assets/js/admin-app.js" defer></script>
</body>
</html>
HTML;

        Response::html($html);
    }

    public function dashboard(Request $request): never
    {
        Auth::requirePermission('dashboard.view');
        $data = (new Admin())->dashboard();
        $data['traffic'] = (new Analytics())->viewsPerDay(14);
        Response::json(['data' => $data]);
    }

    public function users(Request $request): never
    {
        Auth::requirePermission('user.manage');
        Response::json(['data' => (new Admin())->users(
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
            (int) $request->query('page', 1)
        )]);
    }

    public function posts(Request $request): never
    {
        Auth::requirePermission('post.moderate');
        Response::json(['data' => (new Admin())->posts(
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
            (int) $request->query('page', 1)
        )]);
    }

    public function comments(Request $request): never
    {
        Auth::requirePermission('comment.moderate');
        Response::json(['data' => (new Admin())->comments(
            (string) $request->query('q', ''),
            (string) $request->query('status', ''),
            (int) $request->query('page', 1)
        )]);
    }

    public function userStatus(Request $request, string $id): never
    {
        $adminId = Auth::requirePermission('user.manage');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['status' => ['required', 'in:active,banned,inactive'], 'reason' => ['max:500']]);
        $this->action(function () use ($id, $adminId, $data): array {
            (new Admin())->setUserStatus((int) $id, (string) $data['status'], $adminId, $data['reason'] ?? null, $data['until'] ?? null);
            return ['message' => 'Đã cập nhật trạng thái người dùng.'];
        });
    }

    public function userRole(Request $request, string $id): never
    {
        $adminId = Auth::requirePermission('user.manage');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['role' => ['required', 'in:member,moderator']]);
        $this->action(function () use ($id, $adminId, $data): array {
            (new Admin())->setUserRole((int) $id, (string) $data['role'], $adminId);
            return ['message' => 'Đã cập nhật vai trò người dùng.'];
        });
    }

    public function moderatePost(Request $request, string $id): never
    {
        $moderatorId = Auth::requirePermission('post.moderate');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['action' => ['required', 'in:pin_post,unpin_post,lock_post,unlock_post,hide_post,restore_post'], 'reason' => ['max:2000']]);
        $this->action(function () use ($id, $moderatorId, $data): array {
            (new Admin())->moderatePost((int) $id, $moderatorId, (string) $data['action'], $data['reason'] ?? null);
            return ['message' => 'Đã kiểm duyệt bài viết.'];
        });
    }

    public function editPostTitle(Request $request, string $id): never
    {
        $moderatorId = Auth::requirePermission('post.moderate');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['title' => ['required', 'min:10', 'max:255'], 'reason' => ['max:2000']]);
        $this->action(function () use ($id, $moderatorId, $data): array {
            (new Admin())->editPostTitle((int) $id, $moderatorId, (string) $data['title'], $data['reason'] ?? null);
            return ['message' => 'Đã cập nhật tiêu đề bài viết.'];
        });
    }

    public function moderateComment(Request $request, string $id): never
    {
        $moderatorId = Auth::requirePermission('comment.moderate');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['action' => ['required', 'in:hide_comment,restore_comment'], 'reason' => ['max:2000']]);
        $this->action(function () use ($id, $moderatorId, $data): array {
            (new Admin())->moderateComment((int) $id, $moderatorId, (string) $data['action'], $data['reason'] ?? null);
            return ['message' => 'Đã kiểm duyệt bình luận.'];
        });
    }

    public function hardDeletePost(Request $request, string $id): never
    {
        Auth::requirePermission('post.delete_permanent');
        Csrf::requireValid($request);
        $this->action(function () use ($id): array {
            $paths = (new Admin())->hardDeletePost((int) $id);
            $uploadRoot = realpath(dirname(__DIR__, 2) . '/public/uploads/posts');
            if ($uploadRoot !== false) {
                foreach ($paths as $path) {
                    $candidate = $uploadRoot . DIRECTORY_SEPARATOR . basename((string) $path);
                    if (is_file($candidate)) {
                        unlink($candidate);
                    }
                }
            }
            return ['message' => 'Đã xóa vĩnh viễn bài viết và tệp đính kèm.'];
        });
    }
}
