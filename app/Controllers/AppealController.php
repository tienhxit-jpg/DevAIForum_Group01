<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\PostAppeal;

final class AppealController extends Controller
{
    public function queue(Request $request): never
    {
        Auth::requirePermission('appeal.review');
        Response::json(['data' => (new PostAppeal())->queue((string) $request->query('status', 'pending'), (int) $request->query('page', 1))]);
    }

    public function decide(Request $request, string $id): never
    {
        $adminId = Auth::requirePermission('appeal.review');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['status' => ['required', 'in:approved,rejected'], 'reason' => ['max:2000']]);
        $this->action(function () use ($id, $adminId, $data): array {
            (new PostAppeal())->decide((int) $id, $adminId, (string) $data['status'], $data['reason'] ?? null);
            return ['message' => 'Đã xử lý kháng cáo.'];
        });
    }
}
