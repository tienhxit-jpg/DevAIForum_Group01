# Kiến trúc Backend DevAI Hub

## Luồng request

```text
Apache/.htaccess
  -> public/index.php
  -> Core/Request + Core/Router
  -> Controller (validate, CSRF, Auth/RBAC)
  -> Model (PDO prepared statements, transaction)
  -> MariaDB
  -> Core/Response JSON
```

Controllers không chứa SQL. Models không đọc request hoặc gửi response. `Database` là điểm duy nhất tạo kết nối PDO; test có thể tiêm kết nối database tạm bằng `Database::setConnection()`.

## Miền chức năng

- **Identity:** user, role, permission, session, mật khẩu và reset token băm SHA-256.
- **Content:** chuyên mục, bài, tag, ảnh, bình luận phân cấp và best answer.
- **Engagement:** like, bookmark, reputation và notifications.
- **Trust & Safety:** report, soft delete, khóa/ghim/ẩn nội dung, ban user và moderation log.
- **Administration:** dashboard, user CMS, category/tag CMS, merge tag và hard delete.

## Bất biến dữ liệu

- Username/email/slug là duy nhất.
- Một user chỉ like hoặc bookmark một bài một lần nhờ khóa chính kép.
- Best answer là một FK duy nhất trên `posts`; controller/model chỉ cho tác giả bài question chọn comment thuộc chính bài đó.
- Report trỏ đúng một trong post hoặc comment bằng CHECK constraint.
- Bài và bình luận người dùng xóa theo cơ chế soft delete; admin có endpoint xóa bài vĩnh viễn.
- Ảnh chỉ lưu metadata và đường dẫn tương đối trong DB; file nằm dưới `public/uploads`.

## Kiểm soát bảo mật

- Mọi giá trị truy vấn đi qua PDO prepared statements; chỉ `ORDER BY`, tên bảng nội bộ và `LIMIT/OFFSET` đã whitelist/ép số được nội suy.
- Password dùng BCRYPT; reset token thô không lưu trong DB và chỉ dùng một lần.
- Giới hạn đăng nhập lưu theo hash IP + định danh trong MariaDB, nên không thể né bằng cách xóa cookie phiên.
- Session đổi ID sau login/logout; cookie `HttpOnly`, `SameSite=Lax`, tự bật `Secure` trên HTTPS.
- Mọi mutation dùng CSRF token, kể cả request JSON/AJAX.
- Rich Text được parse DOM theo allowlist; loại script, iframe, event handler và URL nguy hiểm.
- Upload xác minh MIME thực bằng `finfo`, giới hạn 2MB, đổi tên bằng 128-bit random và không cho thực thi qua web server.
- RBAC lấy trực tiếp từ `role_permissions`; controller kiểm tra quyền trước thao tác quản trị.
- `public/index.php` phát CSP, X-Frame-Options, Referrer-Policy và Permissions-Policy.

## Chiến lược thông báo

Backend tạo notification khi có like mới, comment/reply và best answer. Frontend gọi `GET /api/notifications` theo chu kỳ 10–30 giây hoặc khi tab nhận focus. Thiết kế polling phù hợp XAMPP/Apache thuần PHP, không yêu cầu WebSocket daemon.

## Mở rộng production

- Đặt web root trực tiếp tại `public/` thay vì repository root.
- Đặt `APP_ENV=production`, tắt `APP_DEBUG` và cấu hình HTTPS.
- Tích hợp SMTP/API email cho reset password; không hiển thị reset token.
- Chuyển rate limiter từ MariaDB sang Redis nếu triển khai lưu lượng lớn hoặc nhiều máy chủ.
- Dùng cron để xóa reset token hết hạn và file upload mồ côi.
- Sao lưu MariaDB và thư mục uploads cùng một mốc thời gian.
