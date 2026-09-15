# Cơ sở dữ liệu DevAI Hub

## Yêu cầu

- XAMPP với MariaDB 10.4+ hoặc MySQL 8.0+
- MySQL chạy trên `127.0.0.1:3306`
- Cấu hình mặc định XAMPP: user `root`, mật khẩu rỗng

## Cài đặt bằng phpMyAdmin

1. Khởi động **MySQL** trong XAMPP Control Panel.
2. Mở `http://localhost/phpmyadmin`.
3. Chọn **Import** và nhập `database/forum_db.sql`.
4. Tiếp tục nhập `database/seed.sql`.

`forum_db.sql` tự tạo database `forum_db` với charset `utf8mb4` và collation `utf8mb4_unicode_ci`.

## Cài đặt bằng dòng lệnh trên Windows

```bash
C:/xampp/mysql/bin/mysql.exe -u root < database/forum_db.sql
C:/xampp/mysql/bin/mysql.exe -u root < database/seed.sql
```

### Nạp dữ liệu mẫu

Sau khi chạy hai file trên, nhập thêm:

```bash
C:/xampp/mysql/bin/mysql.exe -u root < database/mock_data.sql
```

Hoặc chọn `database/mock_data.sql` trong mục **Import** của phpMyAdmin. File có thể chạy lại an toàn; bộ dữ liệu mock cũ dùng email `@devai.local` sẽ được thay thế.

Tất cả tài khoản mẫu dùng mật khẩu:

```text
Demo@123
```

Tài khoản thường dùng để kiểm thử:

```text
admin_devai   — Admin
mod_linh      — Moderator
an_nguyen     — Member
spammer_demo  — Member đang bị khóa
```

Có thể chạy lại cả hai file cấu trúc và seed mà không tạo dữ liệu seed trùng lặp.

## Kết nối từ PHP

Cấu hình nằm tại `config/database.php`. Lấy kết nối PDO bằng:

```php
use App\Core\Database;

$pdo = Database::connection();
```

Các biến môi trường có thể ghi đè cấu hình mặc định:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
```

Không commit mật khẩu thật vào repository.

## Nội dung khởi tạo

- 17 bảng nghiệp vụ
- 3 vai trò: Member, Moderator và Admin
- 18 quyền RBAC
- 4 chuyên mục chính theo tài liệu yêu cầu
- 12 thẻ công nghệ mẫu

Không tạo sẵn tài khoản Admin có mật khẩu yếu. Tài khoản cần được tạo bằng `password_hash()` trong PHP, sau đó gán vai trò `admin` khi triển khai chức năng quản lý người dùng.

## Quy tắc quan trọng

- `posts.best_answer_comment_id` lưu câu trả lời hay nhất.
- `post_tags` biểu diễn quan hệ nhiều–nhiều giữa bài viết và thẻ.
- `post_likes` và `bookmarks` có khóa chính kép để ngăn thao tác trùng.
- `reports` chỉ được trỏ đến một trong hai loại đối tượng: bài viết hoặc bình luận.
- Bài viết và bình luận hỗ trợ xóa mềm bằng `status` và `deleted_at`.
- Backend vẫn phải dùng PDO prepared statements, kiểm tra MIME/kích thước ảnh và làm sạch Rich Text.
