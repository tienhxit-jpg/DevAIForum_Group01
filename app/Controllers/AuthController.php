<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\LoginThrottle;
use App\Models\User;
use DomainException;

final class AuthController extends Controller
{
    public function csrf(Request $request): never
    {
        Response::json(['csrf_token' => Csrf::token()]);
    }

    public function register(Request $request): never
    {
        Csrf::requireValid($request);
        $data = $this->requireValid($request, [
            'username' => ['required', 'username', 'min:3', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'password', 'min:8', 'max:72'],
            'display_name' => ['required', 'min:2', 'max:100'],
        ]);
        $this->action(function () use ($data): array {
            $id = (new User())->create($data);
            Auth::login($id);
            return ['message' => 'Đăng ký thành công.', 'user' => $this->withKarma(Auth::user())];
        });
    }

    public function login(Request $request): never
    {
        Csrf::requireValid($request);
        $data = $this->requireValid($request, [
            'login' => ['required', 'max:255'],
            'password' => ['required', 'max:72'],
        ]);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $throttle = new LoginThrottle();
        $this->guardLoginAttempts($throttle, $ip, (string) $data['login']);
        $this->action(function () use ($data, $ip, $throttle): array {
            $user = (new User())->authenticate((string) $data['login'], (string) $data['password']);
            if ($user === null) {
                $throttle->recordFailure($ip, (string) $data['login']);
                throw new DomainException('Thông tin đăng nhập không chính xác hoặc tài khoản đã bị khóa.');
            }
            $throttle->clear($ip, (string) $data['login']);
            Auth::login((int) $user['id']);
            return ['message' => 'Đăng nhập thành công.', 'user' => $this->withKarma(Auth::user())];
        });
    }

    public function logout(Request $request): never
    {
        $this->member($request);
        Auth::logout();
        Response::json(['message' => 'Đã đăng xuất.']);
    }

    public function me(Request $request): never
    {
        Response::json(['user' => $this->withKarma(Auth::user()), 'permissions' => Auth::check() ? (new User())->permissions(Auth::id()) : []]);
    }

    private function withKarma(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }
        $user += (new User())->karmaFor((int) $user['id']);
        return $user;
    }

    public function forgotPassword(Request $request): never
    {
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['email' => ['required', 'email', 'max:255']]);
        $this->action(function () use ($data): array {
            $token = (new User())->createPasswordReset((string) $data['email']);
            $response = ['message' => 'Nếu email tồn tại, hệ thống đã tạo hướng dẫn đặt lại mật khẩu.'];
            if ($token !== null
                && (getenv('APP_ENV') ?: 'development') !== 'production'
                && filter_var(getenv('RESET_TOKEN_DEBUG') ?: false, FILTER_VALIDATE_BOOL)) {
                $response['reset_token'] = $token;
            }
            return $response;
        });
    }

    public function resetPassword(Request $request): never
    {
        Csrf::requireValid($request);
        $data = $this->requireValid($request, [
            'token' => ['required', 'min:64', 'max:64'],
            'password' => ['required', 'password', 'min:8', 'max:72'],
        ]);
        $this->action(function () use ($data): array {
            if (!(new User())->resetPassword((string) $data['token'], (string) $data['password'])) {
                throw new DomainException('Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            }
            return ['message' => 'Đã đặt lại mật khẩu.'];
        });
    }

    private function guardLoginAttempts(LoginThrottle $throttle, string $ip, string $login): void
    {
        if ($throttle->isBlocked($ip, $login)) {
            Response::json(['error' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 5 phút.'], 429);
        }
    }
}
