<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CommentController;
use App\Controllers\HomeController;
use App\Controllers\InteractionController;
use App\Controllers\NotificationController;
use App\Controllers\PostController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\TaxonomyController;
use App\Core\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/posts/{id}', [HomeController::class, 'index']);
$router->get('/moderation', [HomeController::class, 'index']);

$router->get('/admin', [AdminController::class, 'panel']);
$router->get('/admin/users', [AdminController::class, 'panel']);
$router->get('/admin/posts', [AdminController::class, 'panel']);
$router->get('/admin/comments', [AdminController::class, 'panel']);
$router->get('/admin/reports', [AdminController::class, 'panel']);
$router->get('/admin/taxonomy', [AdminController::class, 'panel']);

$router->get('/api/csrf', [AuthController::class, 'csrf']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/api/me', [AuthController::class, 'me']);

$router->get('/api/feed', [PostController::class, 'feed']);
$router->get('/api/posts/{id}', [PostController::class, 'show']);
$router->post('/api/posts', [PostController::class, 'create']);
$router->put('/api/posts/{id}', [PostController::class, 'update']);
$router->delete('/api/posts/{id}', [PostController::class, 'delete']);
$router->post('/api/posts/{id}/best-answer', [PostController::class, 'bestAnswer']);
$router->post('/api/posts/{id}/comments', [CommentController::class, 'create']);
$router->put('/api/comments/{id}', [CommentController::class, 'update']);
$router->delete('/api/comments/{id}', [CommentController::class, 'delete']);
$router->post('/api/posts/{id}/like', [InteractionController::class, 'like']);
$router->post('/api/posts/{id}/dislike', [InteractionController::class, 'dislike']);
$router->post('/api/posts/{id}/bookmark', [InteractionController::class, 'bookmark']);
$router->post('/api/posts/{id}/pin', [InteractionController::class, 'pin']);
$router->post('/api/comments/{id}/like', [InteractionController::class, 'commentLike']);
$router->post('/api/comments/{id}/dislike', [InteractionController::class, 'commentDislike']);

$router->get('/api/categories', [TaxonomyController::class, 'categories']);
$router->get('/api/tags', [TaxonomyController::class, 'tags']);
$router->get('/api/users/{id}', [ProfileController::class, 'show']);
$router->get('/api/users/{username}/posts', [ProfileController::class, 'posts']);
$router->get('/api/users/{username}/comments', [ProfileController::class, 'comments']);
$router->put('/api/profile', [ProfileController::class, 'update']);
$router->put('/api/profile/password', [ProfileController::class, 'password']);
$router->post('/api/profile/avatar', [ProfileController::class, 'avatar']);
$router->get('/api/profile/bookmarks', [ProfileController::class, 'bookmarks']);
$router->get('/api/profile/pins', [ProfileController::class, 'pins']);
$router->get('/api/profile/liked', [ProfileController::class, 'liked']);
$router->get('/api/profile/disliked', [ProfileController::class, 'disliked']);
$router->get('/api/profile/posts', [ProfileController::class, 'ownPosts']);
$router->get('/api/profile/posts/{id}', [ProfileController::class, 'ownPost']);
$router->get('/api/notifications', [NotificationController::class, 'index']);
$router->patch('/api/notifications/read-all', [NotificationController::class, 'readAll']);
$router->patch('/api/notifications/{id}/read', [NotificationController::class, 'read']);
$router->post('/api/reports', [ReportController::class, 'create']);

$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard']);
$router->get('/api/admin/users', [AdminController::class, 'users']);
$router->get('/api/admin/posts', [AdminController::class, 'posts']);
$router->get('/api/admin/comments', [AdminController::class, 'comments']);
$router->patch('/api/admin/users/{id}/status', [AdminController::class, 'userStatus']);
$router->patch('/api/admin/users/{id}/role', [AdminController::class, 'userRole']);
$router->patch('/api/admin/posts/{id}/moderate', [AdminController::class, 'moderatePost']);
$router->patch('/api/admin/posts/{id}/title', [AdminController::class, 'editPostTitle']);
$router->patch('/api/admin/comments/{id}/moderate', [AdminController::class, 'moderateComment']);
$router->delete('/api/admin/posts/{id}/permanent', [AdminController::class, 'hardDeletePost']);
$router->get('/api/admin/reports', [ReportController::class, 'queue']);
$router->patch('/api/admin/reports/{id}', [ReportController::class, 'resolve']);
$router->post('/api/admin/categories', [TaxonomyController::class, 'saveCategory']);
$router->put('/api/admin/categories/{id}', [TaxonomyController::class, 'saveCategory']);
$router->delete('/api/admin/categories/{id}', [TaxonomyController::class, 'deleteCategory']);
$router->post('/api/admin/tags', [TaxonomyController::class, 'saveTag']);
$router->put('/api/admin/tags/{id}', [TaxonomyController::class, 'saveTag']);
$router->delete('/api/admin/tags/{id}', [TaxonomyController::class, 'deleteTag']);
$router->post('/api/admin/tags/{id}/merge', [TaxonomyController::class, 'mergeTag']);

return $router;
