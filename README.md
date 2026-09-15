# DevAI Hub

Backend diễn đàn công nghệ viết bằng PHP 8.1+ theo mô hình MVC, chạy trực tiếp trên XAMPP (Apache + MariaDB 10.4+). Giao diện có thể gọi API JSON trong `routes/web.php`.

## Chức năng

- Đăng ký, đăng nhập, đăng xuất, phiên đăng nhập an toàn và đặt lại mật khẩu.
- RBAC cho Member, Moderator và Admin với 18 quyền.
- Feed phân trang; lọc mới, phổ biến, đã giải quyết, chuyên mục, tag và tìm kiếm full-text.
- CRUD bài viết, Rich Text đã lọc XSS, tối đa 5 ảnh JPG/PNG/WEBP 2MB, tối đa 5 tag.
- Bình luận phân cấp, sửa/xóa mềm và chọn câu trả lời hay nhất.
- Like, bookmark và thông báo AJAX/polling.
- Hồ sơ, avatar, đổi mật khẩu, lịch sử bài viết và bài đã lưu.
- Báo cáo bài viết/bình luận, hàng đợi kiểm duyệt và nhật ký thao tác.
- Ghim, khóa, ẩn/khôi phục bài viết; ẩn/khôi phục bình luận.
- Dashboard thống kê; quản lý tài khoản, vai trò, chuyên mục và tag; gộp tag; xóa bài vĩnh viễn kèm ảnh.
- Prepared statements PDO, CSRF, CSP, cookie `HttpOnly`/`SameSite`, giới hạn đăng nhập và upload an toàn.

## Cài đặt XAMPP

Yêu cầu: XAMPP có PHP 8.1+, MariaDB 10.4+ và bật extension `pdo_mysql`, `mbstring`, `dom`, `fileinfo`.

1. Chép repository vào `C:\xampp\htdocs\forum_project`.
2. Khởi động Apache và MySQL trong XAMPP Control Panel.
3. Import lần lượt:

```bash
C:/xampp/mysql/bin/mysql.exe -u root < database/forum_db.sql
C:/xampp/mysql/bin/mysql.exe -u root < database/seed.sql
C:/xampp/mysql/bin/mysql.exe -u root < database/mock_data.sql
```

4. Mở `http://localhost/forum_project/` hoặc `http://localhost/forum_project/api/feed`.

Nếu Apache không cho phép rewrite, bật `LoadModule rewrite_module` và đặt `AllowOverride All` cho thư mục `htdocs` trong `httpd.conf`.

## Tài khoản mẫu

Mật khẩu chung: `Demo@123`

| Tài khoản | Vai trò |
|---|---|
| `admin_devai` | Admin |
| `mod_linh` | Moderator |
| `an_nguyen` | Member |
| `spammer_demo` | Member bị khóa |

Chỉ dùng các tài khoản này trong môi trường local.

## Cấu hình

`config/database.php` đọc các biến `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Mặc định phù hợp XAMPP: `127.0.0.1:3306`, database `forum_db`, user `root`, mật khẩu rỗng.

- `APP_ENV=production`: không trả reset token trong response.
- `APP_DEBUG=1`: trả chi tiết lỗi máy chủ; không bật ở production.
- `RESET_TOKEN_DEBUG=1`: chỉ dùng local để nhận reset token khi chưa có mail server; mặc định tắt.

## Gọi API

Lấy CSRF token trước khi gửi request thay đổi dữ liệu:

```bash
curl -c cookie.txt http://localhost/forum_project/api/csrf
curl -b cookie.txt -c cookie.txt -H "Content-Type: application/json" -H "X-CSRF-Token: TOKEN" -d '{"login":"an_nguyen","password":"Demo@123"}' http://localhost/forum_project/api/auth/login
```

Danh sách endpoint và payload: [docs/api.md](docs/api.md).

## Kiểm thử

```bash
C:/xampp/php/php.exe tests/run.php
C:/xampp/php/php.exe tests/routes.php
C:/xampp/php/php.exe tests/integration.php
C:/xampp/php/php.exe tests/database-idempotency.php
C:/xampp/php/php.exe tests/http-smoke.php
```

`tests/integration.php` tạo database tạm `forum_db_test`, kiểm thử luồng thật bằng MariaDB rồi tự xóa database. `tests/http-smoke.php` cần server đang chạy tại `http://127.0.0.1:8080` (hoặc biến `DEVAI_BASE_URL`) và kiểm thử HTTP thật từ đăng nhập đến tạo/xóa bài.

## Cấu trúc backend

- `public/index.php`: front controller và security headers.
- `routes/web.php`: khai báo API.
- `app/Core/`: router, request/response, PDO, auth, CSRF, validator, sanitizer.
- `app/Models/`: truy vấn và nghiệp vụ dữ liệu.
- `app/Controllers/`: xác thực request, phân quyền và response.
- `app/Helpers/`: slug tiếng Việt và upload ảnh.
- `database/`: schema, reference seed và mock data.

Chi tiết kiến trúc: [docs/backend-architecture.md](docs/backend-architecture.md).
