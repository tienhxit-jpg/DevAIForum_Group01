<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LoginThrottle extends Model
{
    private function key(string $ip, string $login): string
    {
        return hash('sha256', trim($ip) . '|' . mb_strtolower(trim($login)));
    }

    public function isBlocked(string $ip, string $login, int $maximumAttempts = 5): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS total FROM login_attempts WHERE attempt_key = :attempt_key AND attempted_at >= CURRENT_TIMESTAMP - INTERVAL 5 MINUTE',
            ['attempt_key' => $this->key($ip, $login)]
        );
        return (int) ($row['total'] ?? 0) >= max(1, $maximumAttempts);
    }

    public function recordFailure(string $ip, string $login): void
    {
        $this->execute(
            'INSERT INTO login_attempts (attempt_key, attempted_at) VALUES (:attempt_key, CURRENT_TIMESTAMP)',
            ['attempt_key' => $this->key($ip, $login)]
        );
        $this->execute('DELETE FROM login_attempts WHERE attempted_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY');
    }

    public function clear(string $ip, string $login): void
    {
        $this->execute('DELETE FROM login_attempts WHERE attempt_key = :attempt_key', [
            'attempt_key' => $this->key($ip, $login),
        ]);
    }
}
