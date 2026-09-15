<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): never
    {
        $userId = Auth::requireLogin();
        $model = new Notification();
        Response::json([
            'data' => $model->inbox($userId, (int) $request->query('page', 1)),
            'unread_count' => $model->unreadCount($userId),
        ]);
    }

    public function read(Request $request, string $id): never
    {
        $userId = $this->member($request);
        (new Notification())->markRead((int) $id, $userId);
        Response::json(['message' => 'Đã đọc thông báo.']);
    }

    public function readAll(Request $request): never
    {
        $userId = $this->member($request);
        (new Notification())->markAllRead($userId);
        Response::json(['message' => 'Đã đọc tất cả thông báo.']);
    }
}
