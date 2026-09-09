# Đặc tả cấu trúc dự án DevAIForum

## 1. Mục đích

Tài liệu này mô tả cấu trúc thư mục, trách nhiệm của từng khu vực và quy ước mở rộng dự án DevAIForum. Repository được tổ chức theo mô hình MVC cho ứng dụng diễn đàn AI viết bằng PHP.

## 2. Cây thư mục

```text
DevAIForum_Group01/
├── app/
│   ├── Controllers/
│   ├── Core/
│   ├── Helpers/
│   ├── Models/
│   └── Views/
│       ├── layouts/
│       │   ├── client/
│       │   │   ├── app.php
│       │   │   ├── header.php
│       │   │   ├── navbar.php
│       │   │   ├── sidebar.php
│       │   │   └── footer.php
│       │   └── admin/
│       │       ├── app.php
│       │       ├── header.php
│       │       ├── sidebar.php
│       │       └── footer.php
│       ├── components/
│       │   ├── post-card.php
│       │   ├── comment.php
│       │   ├── user-card.php
│       │   ├── tag.php
│       │   ├── notification.php
│       │   ├── pagination.php
│       │   ├── modal.php
│       │   └── alert.php
│       ├── client/
│       │   ├── home/
│       │   ├── feed/
│       │   ├── auth/
│       │   ├── posts/
│       │   ├── profile/
│       │   ├── search/
│       │   └── notifications/
│       └── admin/
│           ├── dashboard/
│           ├── users/
│           ├── posts/
│           ├── categories/
│           ├── tags/
│           └── reports/
├── config/
├── database/
├── docs/
│   ├── requirements/
│   ├── design/
│   ├── database/
│   ├── report/
│   └── project-structure.md
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   ├── images/
│   │   └── vendor/
│   │       ├── bootstrap/
│   │       ├── bootstrap-icons/
│   │       └── chartjs/
│   └── uploads/
│       ├── avatars/
│       └── posts/
├── routes/
├── tests/
├── .gitignore
├── LICENSE
└── README.md
```

Các file `.gitkeep` trong những thư mục rỗng chỉ có mục đích giúp Git theo dõi cấu trúc thư mục. Có thể xóa chúng khi thư mục đã có file triển khai thực tế.

## 3. Khu vực ứng dụng

### `app/Controllers/`

Chứa các controller tiếp nhận request, kiểm tra dữ liệu đầu vào, gọi model/service phù hợp và chọn view để trả response.

Quy ước đề xuất: mỗi controller quản lý một nhóm nghiệp vụ, ví dụ `PostController.php`, `CommentController.php` hoặc `AuthController.php`.

### `app/Models/`

Chứa model và logic truy cập dữ liệu cho các thực thể như user, post, comment, category, tag, like, bookmark, notification và report.

Model không nên chứa logic hiển thị giao diện. Các truy vấn dùng chung nên được đặt ở model hoặc lớp truy cập dữ liệu phù hợp.

### `app/Core/`

Chứa các thành phần nền tảng của ứng dụng:

- Router: ánh xạ URL và HTTP method tới controller.
- Controller: lớp cơ sở dùng chung cho controller.
- Model: lớp cơ sở dùng chung cho model.
- Database: khởi tạo và quản lý kết nối cơ sở dữ liệu.
- Auth: quản lý phiên đăng nhập, phân quyền và người dùng hiện tại.
- Validator: kiểm tra dữ liệu đầu vào.

### `app/Helpers/`

Chứa các hàm hỗ trợ dùng chung, bao gồm bảo mật, upload file và các hàm tiện ích. Helper không nên chứa nghiệp vụ riêng của một controller.

## 4. Khu vực giao diện

### `app/Views/layouts/`

Chứa khung giao diện dùng chung:

- `client/`: layout cho người dùng thông thường.
- `admin/`: layout cho khu vực quản trị.

File `app.php` là layout tổng thể; các file `header.php`, `navbar.php`, `sidebar.php` và `footer.php` là các phần giao diện được layout gọi vào.

### `app/Views/components/`

Chứa các thành phần giao diện có thể tái sử dụng ở nhiều trang, chẳng hạn thẻ bài viết, bình luận, người dùng, tag, thông báo, phân trang, modal và alert.

Component nên nhận dữ liệu qua biến được truyền từ view gọi nó và không tự thực hiện truy vấn cơ sở dữ liệu.

### `app/Views/client/`

Chứa view cho người dùng thông thường, được chia theo tính năng:

- `home/`: trang chủ.
- `feed/`: bảng tin.
- `auth/`: đăng nhập, đăng ký và khôi phục tài khoản.
- `posts/`: danh sách, chi tiết, tạo và chỉnh sửa bài viết.
- `profile/`: hồ sơ và hoạt động người dùng.
- `search/`: tìm kiếm.
- `notifications/`: thông báo.

### `app/Views/admin/`

Chứa view cho quản trị viên, được chia theo chức năng quản trị dashboard, users, posts, categories, tags và reports.

## 5. Cấu hình, dữ liệu và tài nguyên

### `config/`

Chứa cấu hình môi trường và cấu hình kết nối cơ sở dữ liệu. Thông tin bí mật không được commit trực tiếp lên repository.

### `database/`

Chứa schema, migration hoặc script seed cơ sở dữ liệu. Tên file nên thể hiện rõ mục đích, ví dụ `forum_db.sql` hoặc `seed.sql`.

### `routes/`

Chứa khai báo route của ứng dụng. Route chỉ nên làm nhiệm vụ ánh xạ request; nghiệp vụ cần được xử lý trong controller hoặc lớp phù hợp.

### `public/`

Là thư mục public/web root:

- `assets/css/`: stylesheet.
- `assets/js/`: JavaScript phía trình duyệt.
- `assets/images/`: hình ảnh tĩnh.
- `assets/vendor/`: thư viện frontend bên thứ ba.
- `uploads/avatars/`: avatar người dùng tải lên.
- `uploads/posts/`: file hoặc hình ảnh gắn với bài viết.

File upload cần được kiểm tra loại file, kích thước, tên file và quyền truy cập trước khi lưu.

### `tests/`

Chứa test cho core, model, controller và các luồng nghiệp vụ quan trọng. Test nên được tổ chức gần với phạm vi chức năng được kiểm thử.

### `docs/`

Chứa tài liệu dự án:

- `requirements/`: yêu cầu chức năng và phi chức năng.
- `design/`: thiết kế giao diện và kiến trúc.
- `database/`: tài liệu schema và quan hệ dữ liệu.
- `report/`: báo cáo và kết quả thực hiện.
- `project-structure.md`: đặc tả cấu trúc repository này.

## 6. Luồng xử lý request

```text
HTTP request
    |
    v
public/index.php
    |
    v
routes/web.php -> Core/Router.php
    |
    v
Controller
    |
    +--> Validator/Auth/Helper
    |
    +--> Model -> Database
    |
    v
View trong app/Views
    |
    v
HTTP response
```

## 7. Quy ước phát triển

1. Đặt controller, model và view theo đúng nhóm chức năng.
2. Dùng layout thay vì lặp lại header, navbar, sidebar và footer ở từng trang.
3. Dùng component cho phần giao diện lặp lại từ hai nơi trở lên.
4. Không đặt thông tin kết nối hoặc secret trực tiếp trong mã nguồn được commit.
5. Kiểm tra quyền truy cập ở controller hoặc lớp Auth trước các thao tác quản trị.
6. Kiểm tra và làm sạch dữ liệu đầu vào trước khi truy vấn hoặc render HTML.
7. Cập nhật test và tài liệu khi thêm một module hoặc một route mới.
