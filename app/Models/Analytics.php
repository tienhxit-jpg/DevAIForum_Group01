<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Analytics extends Model
{
    public function record(string $path, ?int $userId): void
    {
        $this->execute('INSERT INTO page_views (path, user_id) VALUES (:path, :user_id)', [
            'path' => mb_substr($path, 0, 255),
            'user_id' => $userId,
        ]);
    }

    public function viewsPerDay(int $days = 14): array
    {
        $days = max(1, $days);
        return $this->all(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM page_views WHERE created_at >= CURRENT_DATE - INTERVAL {$days} DAY GROUP BY DATE(created_at) ORDER BY day"
        );
    }
}
