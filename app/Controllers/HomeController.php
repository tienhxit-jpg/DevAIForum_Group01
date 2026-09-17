<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Analytics;
use Throwable;

final class HomeController extends Controller
{
    public function index(Request $request): never
    {
        try {
            (new Analytics())->record($request->path(), Auth::id());
        } catch (Throwable) {
        }

        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $baseUrl = preg_replace('#/public$#', '', $scriptDir);
        if ($baseUrl === '/' || $baseUrl === null) {
            $baseUrl = '';
        }

        $csrfToken = Csrf::token();
        $cssVer = @filemtime(dirname(__DIR__, 2) . '/public/assets/css/reddit-theme.css') ?: time();
        $jsVer = @filemtime(dirname(__DIR__, 2) . '/public/assets/js/devai-app.js') ?: time();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevAI Hub - Cộng đồng Lập trình & Trí tuệ Nhân tạo</title>
    <meta name="description" content="Diễn đàn trao đổi công nghệ AI, Machine Learning, Web Development theo phong cách Reddit.">
    <meta name="base-url" content="{$baseUrl}">
    <meta name="csrf-token" content="{$csrfToken}">
    <link rel="icon" type="image/svg+xml" href="{$baseUrl}/assets/img/logo-icon.svg">
    <link rel="stylesheet" href="{$baseUrl}/assets/css/reddit-theme.css?v={$cssVer}">
</head>
<body data-base-url="{$baseUrl}">
    <!-- App Root -->
    <div id="app"></div>

    <!-- Modals Container -->
    <div id="modal-root"></div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container" aria-live="polite"></div>

    <script src="{$baseUrl}/assets/js/icons.js"></script>
    <script src="{$baseUrl}/assets/js/devai-app.js?v={$jsVer}" defer></script>
</body>
</html>
HTML;

        Response::html($html);
    }
}
