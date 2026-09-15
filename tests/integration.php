<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\LoginThrottle;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
use App\Models\Taxonomy;
use App\Models\User;
$test = new TestCase();
$root = dirname(__DIR__);
$databaseName = 'forum_db_test';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("DROP DATABASE IF EXISTS {$databaseName}");
$schema = str_replace('forum_db', $databaseName, (string) file_get_contents($root . '/database/forum_db.sql'));
$seed = str_replace('forum_db', $databaseName, (string) file_get_contents($root . '/database/seed.sql'));
$pdo->exec($schema);
$pdo->exec($seed);
$pdo->exec("USE {$databaseName}");
Database::setConnection($pdo);

try {
    $users = new User();
    $posts = new Post();
    $comments = new Comment();
    $interactions = new Interaction();
    $notifications = new Notification();
    $reports = new Report();
    $taxonomy = new Taxonomy();
    $admin = new Admin();
    $throttle = new LoginThrottle();

    $test->test('login throttling survives session replacement and clears on success', function () use ($test, $throttle): void {
        $ip = '127.0.0.91';
        $login = 'throttled@example.test';
        $throttle->clear($ip, $login);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $throttle->recordFailure($ip, $login);
        }
        $test->assertTrue($throttle->isBlocked($ip, $login));
        $throttle->clear($ip, $login);
        $test->assertFalse($throttle->isBlocked($ip, $login));
    });

    $test->test('member lifecycle supports register and authenticate', function () use ($test, $users): void {
        $id = $users->create([
            'username' => 'tester_devai',
            'email' => 'tester@example.test',
            'password' => 'Strong@123',
            'display_name' => 'Tester DevAI',
        ]);
        $authenticated = $users->authenticate('tester_devai', 'Strong@123');
        $test->assertSame($id, (int) $authenticated['id']);
        $test->assertSame(null, $users->authenticate('tester_devai', 'wrong'));
    });

    $test->test('password reset tokens are hashed, expiring and single use', function () use ($test, $pdo, $users): void {
        $token = $users->createPasswordReset('tester@example.test');
        $stored = (string) $pdo->query("SELECT token_hash FROM password_reset_tokens ORDER BY id DESC LIMIT 1")->fetchColumn();
        $test->assertFalse(hash_equals($stored, $token), 'Raw reset token must not be stored.');
        $test->assertTrue($users->resetPassword($token, 'Changed@123'));
        $test->assertFalse($users->resetPassword($token, 'Another@123'));
        $test->assertTrue($users->authenticate('tester_devai', 'Changed@123') !== null);
    });

    $test->test('forum lifecycle creates tagged post, nested comments and best answer', function () use (
        $test, $pdo, $users, $posts, $comments, $taxonomy
    ): void {
        $author = $users->findByUsername('tester_devai');
        $category = $taxonomy->categories()[0];
        $tag = $taxonomy->tags()[0];
        $postId = $posts->create((int) $author['id'], [
            'category_id' => (int) $category['id'],
            'title' => 'Câu hỏi tích hợp backend',
            'content_html' => '<p>Giúp mình với <script>x</script><code>PHP</code></p>',
            'post_type' => 'question',
            'status' => 'published',
        ], [(int) $tag['id']]);
        $answerId = $comments->create($postId, (int) $author['id'], '<p>Câu trả lời</p>');
        $replyId = $comments->create($postId, (int) $author['id'], '<p>Phản hồi</p>', $answerId);
        $posts->selectBestAnswer($postId, (int) $author['id'], $answerId);

        $detail = $posts->findDetailed($postId, (int) $author['id']);
        $thread = $comments->listByPost($postId, (int) $author['id']);
        $test->assertSame($answerId, (int) $detail['best_answer_comment_id']);
        $test->assertSame(1, count($thread));
        $test->assertSame($replyId, (int) $thread[0]['children'][0]['id']);
        $test->assertNotContains('<script', $detail['content_html']);
        $test->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM post_tags WHERE post_id = {$postId}")->fetchColumn());
    });

    $test->test('author can load a draft and publish it through update', function () use ($test, $users, $posts, $taxonomy): void {
        $author = $users->findByUsername('tester_devai');
        $category = $taxonomy->categories()[0];
        $draftId = $posts->create((int) $author['id'], [
            'category_id' => (int) $category['id'],
            'title' => 'Bản nháp cần được xuất bản',
            'content_html' => '<p>Nội dung bản nháp hợp lệ.</p>',
            'post_type' => 'discussion',
            'status' => 'draft',
        ]);
        $draft = $posts->findOwned($draftId, (int) $author['id']);
        $posts->update($draftId, (int) $author['id'], [
            'category_id' => (int) $category['id'],
            'title' => 'Bản nháp đã được xuất bản',
            'content_html' => '<p>Nội dung sau khi cập nhật.</p>',
            'post_type' => 'discussion',
            'status' => 'published',
        ], []);
        $published = $posts->findDetailed($draftId);

        $test->assertSame('draft', $draft['status']);
        $test->assertSame('published', $published['status']);
    });

    $test->test('feed search treats boolean operators as plain input', function () use ($test, $posts): void {
        $test->assertSame([], $posts->feed(['q' => '+']));
        $test->assertTrue(is_array($posts->feed(['q' => 'PHP + "'])));
    });

    $test->test('likes bookmarks reports and notifications are idempotent', function () use (
        $test, $users, $posts, $interactions, $notifications, $reports
    ): void {
        $user = $users->findByUsername('tester_devai');
        $post = $posts->feed(['limit' => 1], (int) $user['id'])[0];
        $liked = $interactions->toggleLike((int) $user['id'], (int) $post['id']);
        $unliked = $interactions->toggleLike((int) $user['id'], (int) $post['id']);
        $saved = $interactions->toggleBookmark((int) $user['id'], (int) $post['id']);
        $reportId = $reports->create((int) $user['id'], [
            'post_id' => (int) $post['id'],
            'reason' => 'other',
            'details' => 'Kiểm thử',
        ]);
        $notifications->create((int) $user['id'], null, (int) $post['id'], null, 'system', 'Thông báo thử');
        $notifications->markAllRead((int) $user['id']);

        $test->assertTrue($liked['liked']);
        $test->assertFalse($unliked['liked']);
        $test->assertTrue($saved['bookmarked']);
        $test->assertTrue($reportId > 0);
        $queuedReport = array_values(array_filter(
            $reports->queue('pending'),
            static fn (array $report): bool => (int) $report['id'] === $reportId
        ))[0] ?? null;
        $test->assertTrue($queuedReport !== null);
        $test->assertSame((int) $post['id'], (int) $queuedReport['target_post_id']);
        $test->assertSame(0, $notifications->unreadCount((int) $user['id']));
    });

    $test->test('session auth loads permissions and admin actions are audited', function () use (
        $test, $pdo, $users, $posts, $admin
    ): void {
        $member = $users->findByUsername('tester_devai');
        $adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();
        $pdo->prepare('UPDATE users SET role_id = :role_id WHERE id = :id')->execute([
            'role_id' => $adminRoleId,
            'id' => $member['id'],
        ]);
        Auth::login((int) $member['id']);
        $test->assertTrue(Auth::check());
        $test->assertTrue(Auth::can('dashboard.view'));
        $test->assertTrue(Auth::can('post.delete_permanent'));

        $targetId = $users->create([
            'username' => 'moderated_user',
            'email' => 'moderated@example.test',
            'password' => 'Strong@123',
            'display_name' => 'Moderated User',
        ]);
        $admin->setUserStatus($targetId, 'banned', (int) $member['id'], 'Vi phạm kiểm thử');
        $postForTitle = $posts->feed(['limit' => 1])[0];
        $admin->editPostTitle((int) $postForTitle['id'], (int) $member['id'], 'Tiêu đề đã được kiểm duyệt');
        $dashboard = $admin->dashboard();

        $test->assertTrue((int) $dashboard['users_total'] >= 2);
        $test->assertSame('banned', $users->findByUsername('moderated_user')['status']);
        $test->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM moderation_logs WHERE target_user_id = {$targetId} AND action = 'ban_user'")->fetchColumn());
        $test->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM moderation_logs WHERE post_id = {$postForTitle['id']} AND action = 'edit_post_title'")->fetchColumn());
        Auth::logout();
        $test->assertFalse(Auth::check());
    });

    $test->test('hiding a post requires a reason and notifies its author', function () use (
        $test, $pdo, $users, $posts, $notifications, $admin
    ): void {
        $moderatorId = $users->create([
            'username' => 'inline_moderator',
            'email' => 'inline-moderator@example.test',
            'password' => 'Moderator@123',
            'display_name' => 'Inline Moderator',
        ]);
        $pdo->prepare("UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'moderator') WHERE id = :id")
            ->execute(['id' => $moderatorId]);
        $post = $posts->feed(['limit' => 1])[0];
        $authorId = (int) $post['author_id'];

        $test->assertThrows(
            static fn () => $admin->moderatePost((int) $post['id'], $moderatorId, 'hide_post'),
            DomainException::class
        );

        $reason = 'Nội dung vi phạm quy tắc cộng đồng.';
        $admin->moderatePost((int) $post['id'], $moderatorId, 'hide_post', $reason);
        $notice = array_values(array_filter(
            $notifications->inbox($authorId, 1, 30),
            static fn (array $item): bool => $item['type'] === 'moderation'
                && (int) $item['post_id'] === (int) $post['id']
        ))[0] ?? null;

        $test->assertTrue($notice !== null);
        $test->assertSame('moderation', $notice['type']);
        $test->assertSame((int) $post['id'], (int) $notice['post_id']);
        $test->assertSame($moderatorId, (int) $notice['actor_id']);
        $test->assertContains($reason, $notice['message']);
    });

    $test->test('admin can merge tags and permanently remove a post', function () use (
        $test, $pdo, $posts, $taxonomy, $admin
    ): void {
        $sourceId = $taxonomy->saveTag(null, ['name' => 'Nguồn gộp']);
        $targetId = $taxonomy->saveTag(null, ['name' => 'Đích gộp']);
        $post = $posts->feed(['limit' => 1])[0];
        $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)')->execute([
            'post_id' => $post['id'], 'tag_id' => $sourceId,
        ]);
        $taxonomy->mergeTag($sourceId, $targetId);
        $paths = $admin->hardDeletePost((int) $post['id']);

        $test->assertSame([], $paths);
        $test->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM tags WHERE id = {$sourceId}")->fetchColumn());
        $test->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE id = {$post['id']}")->fetchColumn());
    });

    $test->test('admin can list and filter posts and comments for moderation', function () use ($test, $admin): void {
        $allPosts = $admin->posts('', '', 1);
        $test->assertTrue(count($allPosts) > 0);
        $publishedOnly = $admin->posts('', 'published', 1);
        foreach ($publishedOnly as $row) {
            $test->assertSame('published', $row['status']);
        }
        $byTitle = $admin->posts((string) $allPosts[0]['title'], '', 1);
        $test->assertTrue(count($byTitle) > 0);

        $allComments = $admin->comments('', '', 1);
        $test->assertTrue(count($allComments) > 0);
        $visibleOnly = $admin->comments('', 'visible', 1);
        foreach ($visibleOnly as $row) {
            $test->assertSame('visible', $row['status']);
        }
    });

    $test->test('admin actions reject missing content and protected admin accounts', function () use ($test, $pdo, $users, $admin): void {
        $actor = $users->findByUsername('tester_devai');
        $protectedId = $users->create([
            'username' => 'protected_admin', 'email' => 'protected-admin@example.test',
            'password' => 'Protected@123', 'display_name' => 'Protected Admin',
        ]);
        $pdo->prepare("UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin') WHERE id = :id")
            ->execute(['id' => $protectedId]);
        $test->assertThrows(
            static fn () => $admin->moderatePost(99999999, (int) $actor['id'], 'hide_post'),
            DomainException::class
        );
        $test->assertThrows(
            static fn () => $admin->setUserStatus($protectedId, 'banned', (int) $actor['id']),
            DomainException::class
        );
        $test->assertThrows(
            static fn () => $admin->setUserRole($protectedId, 'member', (int) $actor['id']),
            DomainException::class
        );
    });
} finally {
    Database::setConnection(null);
    $pdo->exec("DROP DATABASE IF EXISTS {$databaseName}");
}

$test->finish();
