# API Backend DevAI Hub

Base path: `/api`. Response là JSON UTF-8. Endpoint thay đổi dữ liệu yêu cầu cookie phiên đăng nhập (trừ đăng ký/đăng nhập/reset) và header `X-CSRF-Token` lấy từ `GET /api/csrf`.

## Xác thực

| Method | Endpoint | Chức năng |
|---|---|---|
| GET | `/csrf` | Lấy CSRF token |
| POST | `/auth/register` | Đăng ký: `username`, `email`, `password`, `display_name` |
| POST | `/auth/login` | Đăng nhập: `login`, `password` |
| POST | `/auth/logout` | Đăng xuất |
| POST | `/auth/forgot-password` | Tạo reset token: `email` |
| POST | `/auth/reset-password` | Đặt mật khẩu mới: `token`, `password` |
| GET | `/me` | Người dùng hiện tại và danh sách quyền |

Endpoint forgot-password luôn trả thông báo chung để không lộ email tồn tại. Chỉ khi chủ dự án chủ động đặt `RESET_TOKEN_DEBUG=1` trong môi trường khác production, response mới có `reset_token` để kiểm thử XAMPP không có mail server. Không bật tùy chọn này trên máy có người dùng thật; production phải tích hợp dịch vụ email và đặt `APP_ENV=production`.

## Feed và bài viết

| Method | Endpoint | Chức năng |
|---|---|---|
| GET | `/feed` | Feed và tìm kiếm |
| GET | `/posts/{id}` | Chi tiết bài và cây bình luận |
| POST | `/posts` | Tạo bài |
| PUT | `/posts/{id}` | Sửa bài của mình hoặc kiểm duyệt |
| DELETE | `/posts/{id}` | Xóa mềm bài |
| POST | `/posts/{id}/best-answer` | Chọn `comment_id` cho bài dạng question |
| POST | `/posts/{id}/like` | Like/unlike |
| POST | `/posts/{id}/bookmark` | Lưu/bỏ lưu |

Query feed: `page`, `limit` (tối đa 50), `sort=new|popular|oldest`, `category`, `tag`, `solved=1`, `q`, `author`.

Payload bài viết: `category_id`, `title`, `content_html`, `post_type=discussion|question|resource|job`, `status=draft|published`, `tag_ids[]`. Tạo bài có thể gửi multipart `images[]`, tối đa 5 ảnh và 2MB/ảnh.

## Bình luận

| Method | Endpoint | Chức năng |
|---|---|---|
| POST | `/posts/{postId}/comments` | Tạo bình luận: `content_html`, tùy chọn `parent_id` |
| PUT | `/comments/{id}` | Sửa bình luận: `content_html` |
| DELETE | `/comments/{id}` | Xóa mềm bình luận |

Chủ đề bị khóa không nhận bình luận mới. Khi xóa best answer, bài tự bỏ trạng thái đã giải quyết.

## Hồ sơ, taxonomy và thông báo

| Method | Endpoint | Chức năng |
|---|---|---|
| GET | `/users/{id}` | Hồ sơ công khai |
| GET | `/users/{username}/posts` | Bài công khai của thành viên |
| PUT | `/profile` | Sửa `display_name`, `bio` |
| PUT | `/profile/password` | Đổi `current_password`, `new_password` |
| POST | `/profile/avatar` | Upload `avatar` |
| GET | `/profile/posts` | Bài và bản nháp của chính mình |
| GET | `/profile/posts/{id}` | Nội dung bài/bản nháp của mình để chỉnh sửa |
| GET | `/profile/bookmarks` | Bài đã lưu |
| GET | `/categories` | Chuyên mục hoạt động |
| GET | `/tags` | Tag và số bài |
| GET | `/notifications` | Hộp thư; frontend có thể polling 10–30 giây |
| PATCH | `/notifications/{id}/read` | Đánh dấu đã đọc |
| PATCH | `/notifications/read-all` | Đánh dấu tất cả đã đọc |
| POST | `/reports` | Báo cáo: đúng một `post_id`/`comment_id`, `reason`, `details` |

## Moderator/Admin

| Method | Endpoint | Quyền |
|---|---|---|
| GET | `/admin/dashboard` | `dashboard.view` |
| GET | `/admin/users` | `user.manage` |
| PATCH | `/admin/users/{id}/status` | `user.manage`; `status`, `reason`, `until` |
| PATCH | `/admin/users/{id}/role` | `user.manage`; `role` |
| PATCH | `/admin/posts/{id}/moderate` | `post.moderate`; `action`, `reason` |
| PATCH | `/admin/posts/{id}/title` | `post.moderate`; `title`, `reason` |
| PATCH | `/admin/comments/{id}/moderate` | `comment.moderate`; `action`, `reason` |
| DELETE | `/admin/posts/{id}/permanent` | `post.delete_permanent` (chỉ Admin) |
| GET | `/admin/reports` | `report.review` |
| PATCH | `/admin/reports/{id}` | `report.review`; `status`, `note` |
| POST/PUT/DELETE | `/admin/categories[/{id}]` | `category.manage` |
| POST/PUT/DELETE | `/admin/tags[/{id}]` | `tag.manage` |
| POST | `/admin/tags/{id}/merge` | `tag.manage`; `target_tag_id` |

Post moderation actions: `pin_post`, `unpin_post`, `lock_post`, `unlock_post`, `hide_post`, `restore_post`.
`hide_post` bắt buộc có `reason`; thao tác sẽ tạo thông báo `moderation` cho tác giả bài viết. `restore_post` cũng thông báo khi bài được khôi phục.

Comment moderation actions: `hide_comment`, `restore_comment`.

## Mã lỗi

- `401`: chưa đăng nhập.
- `403`: thiếu quyền RBAC.
- `404`: route hoặc tài nguyên không tồn tại.
- `409`: dữ liệu trùng hoặc vi phạm ràng buộc.
- `419`: CSRF token sai.
- `422`: payload hoặc nghiệp vụ không hợp lệ.
- `429`: vượt giới hạn đăng nhập.
- `500`: lỗi máy chủ/DB.
