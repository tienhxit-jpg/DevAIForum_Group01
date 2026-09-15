<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';

$baseUrl = getenv('DEVAI_BASE_URL') ?: 'http://127.0.0.1:8080';
$cookieFile = tempnam(sys_get_temp_dir(), 'devai-full-test-');
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
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
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
    $decoded = json_decode($raw, true);
    return ['status' => $status, 'body' => $decoded ?? $raw];
};

try {
    // 1. CSRF & Security Headers
    $csrfRes = $request('GET', '/api/csrf');
    $csrf = $csrfRes['body']['csrf_token'] ?? '';

    $test->test('01. CSRF token generation and validation', function () use ($test, $csrfRes, $csrf): void {
        $test->assertSame(200, $csrfRes['status']);
        $test->assertSame(64, strlen($csrf));
    });

    // 2. Categories & Tags Public Query
    $catsRes = $request('GET', '/api/categories');
    $tagsRes = $request('GET', '/api/tags');
    $test->test('02. Categories and Tags public retrieval', function () use ($test, $catsRes, $tagsRes): void {
        $test->assertSame(200, $catsRes['status']);
        $test->assertSame(200, $tagsRes['status']);
        $test->assertTrue(count($catsRes['body']['data']) >= 4);
        $test->assertTrue(count($tagsRes['body']['data']) >= 10);
    });

    // 3. User Auth (Member Login, Me Query)
    $loginMember = $request('POST', '/api/auth/login', ['login' => 'an_nguyen', 'password' => 'Demo@123'], $csrf);
    $meMember = $request('GET', '/api/me');
    $test->test('03. Member login and session authentication', function () use ($test, $loginMember, $meMember): void {
        $test->assertSame(200, $loginMember['status']);
        $test->assertSame(200, $meMember['status']);
        $test->assertSame('an_nguyen', $meMember['body']['user']['username']);
    });

    // 4. Feed Query with Filters (Popular, Newest, Solved, Category, Tag, Search)
    $feedPopular = $request('GET', '/api/feed?sort=popular&limit=5');
    $feedNewest = $request('GET', '/api/feed?sort=newest&limit=5');
    $feedSolved = $request('GET', '/api/feed?sort=solved&limit=5');
    $feedCat = $request('GET', '/api/feed?category=' . $catsRes['body']['data'][0]['id']);
    $feedTag = $request('GET', '/api/feed?tag=' . $tagsRes['body']['data'][0]['id']);
    $feedSearch = $request('GET', '/api/feed?q=PHP');

    $test->test('04. Feed sorting, filtering and full-text search', function () use ($test, $feedPopular, $feedNewest, $feedSolved, $feedCat, $feedTag, $feedSearch): void {
        $test->assertSame(200, $feedPopular['status']);
        $test->assertSame(200, $feedNewest['status']);
        $test->assertSame(200, $feedSolved['status']);
        $test->assertSame(200, $feedCat['status']);
        $test->assertSame(200, $feedTag['status']);
        $test->assertSame(200, $feedSearch['status']);
    });

    // 5. Post Creation & Detail
    $createdPost = $request('POST', '/api/posts', [
        'category_id' => $catsRes['body']['data'][0]['id'],
        'title' => 'Bài viết kiểm thử tính năng tự động E2E',
        'content_html' => '<p>Nội dung kiểm thử chi tiết hệ thống API.</p>',
        'post_type' => 'question',
        'status' => 'published',
        'tag_ids' => [$tagsRes['body']['data'][0]['id'], $tagsRes['body']['data'][1]['id']],
    ], $csrf);
    $postId = (int) ($createdPost['body']['id'] ?? 0);

    $postDetail = $request('GET', "/api/posts/{$postId}");
    $test->test('05. Post creation with tags and detail view', function () use ($test, $createdPost, $postId, $postDetail): void {
        $test->assertSame(200, $createdPost['status']);
        $test->assertTrue($postId > 0);
        $test->assertSame(200, $postDetail['status']);
        $test->assertSame('Bài viết kiểm thử tính năng tự động E2E', $postDetail['body']['data']['title']);
    });

    // 6. Commenting & Reply Threading
    $comment1 = $request('POST', "/api/posts/{$postId}/comments", ['content_html' => '<p>Bình luận gốc cấp 1.</p>'], $csrf);
    $c1Id = (int) ($comment1['body']['id'] ?? 0);

    $comment2 = $request('POST', "/api/posts/{$postId}/comments", ['content_html' => '<p>Phản hồi lồng cấp 2.</p>', 'parent_id' => $c1Id], $csrf);
    $c2Id = (int) ($comment2['body']['id'] ?? 0);

    $test->test('06. Comment creation and nested replies', function () use ($test, $comment1, $comment2, $c1Id, $c2Id): void {
        $test->assertSame(200, $comment1['status']);
        $test->assertSame(200, $comment2['status']);
        $test->assertTrue($c1Id > 0);
        $test->assertTrue($c2Id > 0);
    });

    // 7. Select Best Answer
    $bestAnswerRes = $request('POST', "/api/posts/{$postId}/best-answer", ['comment_id' => $c1Id], $csrf);
    $test->test('07. Best Answer selection for question post', function () use ($test, $bestAnswerRes): void {
        $test->assertSame(200, $bestAnswerRes['status']);
        $test->assertSame('Đã chọn câu trả lời hay nhất.', $bestAnswerRes['body']['message']);
    });

    // 8. Like & Bookmark & Report
    $likeRes = $request('POST', "/api/posts/{$postId}/like", [], $csrf);
    $bookmarkRes = $request('POST', "/api/posts/{$postId}/bookmark", [], $csrf);
    $reportRes = $request('POST', '/api/reports', ['post_id' => $postId, 'reason' => 'other', 'details' => 'Kiểm thử báo cáo.'], $csrf);

    $test->test('08. Like, Bookmark and Content Report actions', function () use ($test, $likeRes, $bookmarkRes, $reportRes): void {
        $test->assertSame(200, $likeRes['status']);
        $test->assertTrue($likeRes['body']['liked']);
        $test->assertSame(200, $bookmarkRes['status']);
        $test->assertTrue($bookmarkRes['body']['bookmarked']);
        $test->assertSame(200, $reportRes['status']);
    });

    // 9. Profile & Notifications
    $profileBookmarks = $request('GET', '/api/profile/bookmarks');
    $profilePosts = $request('GET', '/api/profile/posts');
    $notificationsRes = $request('GET', '/api/notifications');

    $test->test('09. User profile bookmarks, own posts and notifications', function () use ($test, $profileBookmarks, $profilePosts, $notificationsRes): void {
        $test->assertSame(200, $profileBookmarks['status']);
        $test->assertSame(200, $profilePosts['status']);
        $test->assertSame(200, $notificationsRes['status']);
    });

    // 10. Admin Operations (Dashboard, Users, Moderation, Hard Delete)
    $loginAdmin = $request('POST', '/api/auth/login', ['login' => 'admin_devai', 'password' => 'Demo@123'], $csrf);
    $dashRes = $request('GET', '/api/admin/dashboard');
    $usersAdminRes = $request('GET', '/api/admin/users');
    $reportsAdminRes = $request('GET', '/api/admin/reports');

    // Admin Category CRUD
    $newCatRes = $request('POST', '/api/admin/categories', ['name' => 'Chuyên mục Test', 'slug' => 'chuyen-muc-test', 'description' => 'Mô tả test.'], $csrf);
    $newCatId = (int) ($newCatRes['body']['id'] ?? 0);
    $delCatRes = $request('DELETE', "/api/admin/categories/{$newCatId}", [], $csrf);

    // Hard Delete Test Post
    $hardDeleteRes = $request('DELETE', "/api/admin/posts/{$postId}/permanent", [], $csrf);

    $test->test('10. Admin Dashboard, User Management, Category CRUD and Hard Delete', function () use ($test, $loginAdmin, $dashRes, $usersAdminRes, $reportsAdminRes, $newCatRes, $delCatRes, $hardDeleteRes): void {
        $test->assertSame(200, $loginAdmin['status']);
        $test->assertSame(200, $dashRes['status']);
        $test->assertSame(200, $usersAdminRes['status']);
        $test->assertSame(200, $reportsAdminRes['status']);
        $test->assertSame(200, $newCatRes['status']);
        $test->assertSame(200, $delCatRes['status']);
        $test->assertSame(200, $hardDeleteRes['status']);
    });

    // 11. Front-end Web SPA & Static Assets Delivery
    $homeRes = $request('GET', '/');
    $postWebRes = $request('GET', '/posts/1');
    $cssRes = $request('GET', '/assets/css/reddit-theme.css');
    $jsRes = $request('GET', '/assets/js/reddit-app.js');

    $test->test('11. Frontend SPA routes and static assets delivery', function () use ($test, $homeRes, $postWebRes, $cssRes, $jsRes): void {
        $test->assertSame(200, $homeRes['status']);
        $test->assertSame(200, $postWebRes['status']);
        $test->assertSame(200, $cssRes['status']);
        $test->assertSame(200, $jsRes['status']);
    });

} finally {
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

$test->finish();
