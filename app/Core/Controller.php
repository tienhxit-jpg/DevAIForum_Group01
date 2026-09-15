<?php

declare(strict_types=1);

namespace App\Core;

use DomainException;
use PDOException;
use Throwable;

abstract class Controller
{
    protected function action(callable $callback): never
    {
        try {
            $result = $callback();
            Response::json(is_array($result) ? $result : ['data' => $result]);
        } catch (DomainException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $status = $exception->getCode() === '23000' ? 409 : 500;
            Response::json(['error' => $status === 409 ? 'Dữ liệu bị trùng hoặc đang được sử dụng.' : 'Không thể xử lý dữ liệu.'], $status);
        } catch (Throwable $exception) {
            $debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL);
            Response::json(['error' => 'Lỗi máy chủ.', 'detail' => $debug ? $exception->getMessage() : null], 500);
        }
    }

    protected function requireValid(Request $request, array $rules): array
    {
        $data = $request->input();
        $errors = Validator::validate($data, $rules);
        if ($errors !== []) {
            Response::json(['error' => 'Dữ liệu không hợp lệ.', 'errors' => $errors], 422);
        }
        return $data;
    }

    protected function member(Request $request, ?string $permission = null): int
    {
        $id = $permission === null ? Auth::requireLogin() : Auth::requirePermission($permission);
        Csrf::requireValid($request);
        return $id;
    }
}
