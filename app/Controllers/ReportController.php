<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Report;

final class ReportController extends Controller
{
    public function create(Request $request): never
    {
        $userId = $this->member($request, 'content.report');
        $data = $this->requireValid($request, ['reason' => ['required', 'in:spam,harassment,misinformation,copyright,other'], 'details' => ['max:2000']]);
        $this->action(fn (): array => ['message' => 'Đã gửi báo cáo.', 'id' => (new Report())->create($userId, $data)]);
    }

    public function queue(Request $request): never
    {
        Auth::requirePermission('report.review');
        Response::json(['data' => (new Report())->queue((string) $request->query('status', 'pending'), (int) $request->query('page', 1))]);
    }

    public function resolve(Request $request, string $id): never
    {
        $moderatorId = Auth::requirePermission('report.review');
        \App\Core\Csrf::requireValid($request);
        $data = $this->requireValid($request, ['status' => ['required', 'in:resolved,rejected'], 'note' => ['required', 'max:2000']]);
        $this->action(function () use ($id, $moderatorId, $data): array {
            (new Report())->resolve((int) $id, $moderatorId, (string) $data['status'], (string) $data['note']);
            return ['message' => 'Đã xử lý báo cáo.'];
        });
    }
}
