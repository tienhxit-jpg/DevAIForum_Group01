<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ($fieldRules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $message = self::check($field, $value, $name, $parameter);
                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        return $errors;
    }

    private static function check(string $field, mixed $value, string $rule, ?string $parameter): ?string
    {
        $empty = $value === null || (is_string($value) && trim($value) === '');
        if ($rule !== 'required' && $empty) {
            return null;
        }

        return match ($rule) {
            'required' => $empty ? "{$field} là bắt buộc." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Email không hợp lệ.' : null,
            'username' => preg_match('/^[A-Za-z0-9_]+$/', (string) $value) !== 1
                ? 'Tên đăng nhập chỉ gồm chữ, số và dấu gạch dưới.' : null,
            'min' => mb_strlen((string) $value) < (int) $parameter
                ? "{$field} phải có ít nhất {$parameter} ký tự." : null,
            'max' => mb_strlen((string) $value) > (int) $parameter
                ? "{$field} không được vượt quá {$parameter} ký tự." : null,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false ? "{$field} phải là số nguyên." : null,
            'in' => !in_array((string) $value, explode(',', (string) $parameter), true)
                ? "{$field} có giá trị không hợp lệ." : null,
            'password' => self::validPassword((string) $value) ? null
                : 'Mật khẩu cần chữ hoa, chữ thường, số và ký tự đặc biệt.',
            default => "Quy tắc kiểm tra {$rule} không được hỗ trợ.",
        };
    }

    private static function validPassword(string $password): bool
    {
        return preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}
