<?php

require_once __DIR__ . '/../Helpers/Auth.php';

class AuthMiddleware
{
    public static function handle()
    {
        if (!Auth::check()) {
            http_response_code(401);
            echo "401 - Bạn chưa đăng nhập!";
            exit;
        }
    }
}