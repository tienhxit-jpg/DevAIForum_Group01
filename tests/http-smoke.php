<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';

$baseUrl = getenv('DEVAI_BASE_URL') ?: 'http://127.0.0.1:8080';
$cookieFile = tempnam(sys_get_temp_dir(), 'devai-cookie-');
$test = new TestCase();

$request = static function (string $method, string $path, ?array $body = null, ?string $csrf = null) use ($baseUrl, $cookieFile): array {
    $handle = curl_init($baseUrl . $path);
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($handle);
    if ($raw === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return ['status' => $status, 'body' => json_decode($raw, true, flags: JSON_THROW_ON_ERROR)];
};

$rawGet = static function (string $path) use ($baseUrl, $cookieFile): array {
    $handle = curl_init($baseUrl . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($handle);
    if ($raw === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
};

try {
    $csrfResponse = $request('GET', '/api/csrf');
    $csrf = $csrfResponse['body']['csrf_token'] ?? '';
    $createdPostId = 0;

    $test->test('admin panel redirects unauthenticated visitors', function () use ($test, $rawGet): void {
        $response = $rawGet('/admin');
        $test->assertSame(302, $response['status']);
        $test->assertTrue(str_contains($response['headers'], 'Location:'));
    });

    $test->test('live API serves CSRF and populated feed', function () use ($test, $request, $csrfResponse, $csrf): void {
        $feed = $request('GET', '/api/feed?limit=5');
        $test->assertSame(200, $csrfResponse['status']);
        $test->assertSame(64, strlen($csrf));
        $test->assertSame(200, $feed['status']);
        $test->assertSame(5, count($feed['body']['data']));
    });

    $test->test('live API authenticates member and enforces admin RBAC', function () use ($test, $request, $csrf): void {
        $login = $request('POST', '/api/auth/login', ['login' => 'an_nguyen', 'password' => 'Demo@123'], $csrf);
        $me = $request('GET', '/api/me');
        $dashboard = $request('GET', '/api/admin/dashboard');
        $test->assertSame(200, $login['status']);
        $test->assertSame('an_nguyen', $me['body']['user']['username']);
        $test->assertSame(403, $dashboard['status']);
    });

    $test->test('live API creates a tagged question, comment and like', function () use ($test, $request, $csrf, &$createdPostId): void {
        $categories = $request('GET', '/api/categories');
        $tags = $request('GET', '/api/tags');
        $created = $request('POST', '/api/posts', [
            'category_id' => $categories['body']['data'][0]['id'],
            'title' => 'Câu hỏi smoke test backend DevAI',
            'content_html' => '<p>Nội dung smoke test cho API.</p>',
            'post_type' => 'question',
            'status' => 'published',
            'tag_ids' => [$tags['body']['data'][0]['id']],
        ], $csrf);
        $createdPostId = (int) ($created['body']['id'] ?? 0);
        $comment = $request('POST', "/api/posts/{$createdPostId}/comments", ['content_html' => '<p>Câu trả lời smoke test.</p>'], $csrf);
        $like = $request('POST', "/api/posts/{$createdPostId}/like", [], $csrf);
        $test->assertSame(200, $created['status']);
        $test->assertTrue($createdPostId > 0);
        $test->assertSame(200, $comment['status']);
        $test->assertTrue($like['body']['liked']);
    });

    $test->test('live API grants admin permission and permanently cleans test post', function () use ($test, $request, $csrf, &$createdPostId): void {
        $login = $request('POST', '/api/auth/login', ['login' => 'admin_devai', 'password' => 'Demo@123'], $csrf);
        $dashboard = $request('GET', '/api/admin/dashboard');
        $deleted = $request('DELETE', "/api/admin/posts/{$createdPostId}/permanent", [], $csrf);
        $missing = $request('GET', "/api/posts/{$createdPostId}");
        $test->assertSame(200, $login['status']);
        $test->assertSame(200, $dashboard['status']);
        $test->assertTrue((int) $dashboard['body']['data']['users_total'] >= 9);
        $test->assertSame(200, $deleted['status']);
        $test->assertSame(404, $missing['status']);
    });

    $test->test('admin panel renders standalone shell for admin session', function () use ($test, $rawGet): void {
        $response = $rawGet('/admin');
        $test->assertSame(200, $response['status']);
        $test->assertTrue(str_contains($response['body'], 'admin-app.js'));
        $test->assertTrue(str_contains($response['body'], 'admin-theme.css'));
    });

    $test->test('moderator can review reports without receiving admin user management', function () use ($test, $request, $csrf): void {
        $login = $request('POST', '/api/auth/login', ['login' => 'mod_linh', 'password' => 'Demo@123'], $csrf);
        $me = $request('GET', '/api/me');
        $reports = $request('GET', '/api/admin/reports?status=pending');
        $feed = $request('GET', '/api/feed?sort=newest&limit=1');
        $reviewPostId = (int) ($feed['body']['data'][0]['id'] ?? 0);
        $locked = $request('PATCH', "/api/admin/posts/{$reviewPostId}/moderate", ['action' => 'lock_post'], $csrf);
        $unlocked = $request('PATCH', "/api/admin/posts/{$reviewPostId}/moderate", ['action' => 'unlock_post'], $csrf);
        $users = $request('GET', '/api/admin/users');
        $dashboard = $request('GET', '/api/admin/dashboard');
        $test->assertSame(200, $login['status']);
        $test->assertSame('moderator', $me['body']['user']['role_name']);
        $test->assertSame(200, $reports['status']);
        $test->assertTrue(is_array($reports['body']['data']));
        $test->assertTrue($reviewPostId > 0);
        $test->assertSame(200, $locked['status']);
        $test->assertSame(200, $unlocked['status']);
        $test->assertSame(403, $users['status']);
        $test->assertSame(403, $dashboard['status']);
    });

    $test->test('admin panel redirects non-admin session', function () use ($test, $rawGet): void {
        $response = $rawGet('/admin');
        $test->assertSame(302, $response['status']);
    });
} finally {
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

$test->finish();
