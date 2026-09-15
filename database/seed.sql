-- DevAI Hub initial reference data
USE forum_db;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO roles (name, display_name) VALUES
    ('member', 'Thành viên'),
    ('moderator', 'Kiểm duyệt viên'),
    ('admin', 'Quản trị viên')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

INSERT INTO permissions (code, description) VALUES
    ('post.create', 'Tạo bài viết'),
    ('post.update_own', 'Sửa bài viết của chính mình'),
    ('post.delete_own', 'Xóa mềm bài viết của chính mình'),
    ('comment.create', 'Tạo bình luận hoặc câu trả lời'),
    ('comment.update_own', 'Sửa bình luận của chính mình'),
    ('comment.delete_own', 'Xóa mềm bình luận của chính mình'),
    ('post.like', 'Thích hoặc bỏ thích bài viết'),
    ('post.bookmark', 'Lưu hoặc bỏ lưu bài viết'),
    ('content.report', 'Báo cáo bài viết hoặc bình luận'),
    ('post.select_best_answer', 'Chọn câu trả lời hay nhất cho bài của mình'),
    ('post.moderate', 'Ghim, khóa, ẩn và khôi phục bài viết'),
    ('post.delete_permanent', 'Xóa vĩnh viễn bài viết và tệp đính kèm'),
    ('comment.moderate', 'Ẩn và khôi phục bình luận'),
    ('report.review', 'Xử lý hàng đợi báo cáo'),
    ('user.manage', 'Quản lý vai trò và trạng thái tài khoản'),
    ('category.manage', 'Quản lý chuyên mục'),
    ('tag.manage', 'Quản lý thẻ công nghệ'),
    ('dashboard.view', 'Xem dashboard quản trị')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Member permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'post.create', 'post.update_own', 'post.delete_own',
    'comment.create', 'comment.update_own', 'comment.delete_own',
    'post.like', 'post.bookmark', 'content.report', 'post.select_best_answer'
)
WHERE r.name = 'member';

-- Moderator inherits member permissions and moderation permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'post.create', 'post.update_own', 'post.delete_own',
    'comment.create', 'comment.update_own', 'comment.delete_own',
    'post.like', 'post.bookmark', 'content.report', 'post.select_best_answer',
    'post.moderate', 'comment.moderate', 'report.review'
)
WHERE r.name = 'moderator';

-- Admin receives every permission.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin';

INSERT INTO categories (name, slug, description, sort_order) VALUES
    ('Học tập & Lập trình cơ bản', 'hoc-tap-lap-trinh-co-ban', 'Web, cấu trúc dữ liệu, giải thuật và cơ sở dữ liệu.', 10),
    ('AI & Khoa học dữ liệu', 'ai-khoa-hoc-du-lieu', 'Machine Learning, NLP, Computer Vision, API AI và Prompt Engineering.', 20),
    ('Hỏi đáp đồ án & Gỡ lỗi', 'hoi-dap-do-an-go-loi', 'Đặt câu hỏi kỹ thuật và chọn câu trả lời hay nhất.', 30),
    ('Tài nguyên & Cơ hội nghề nghiệp', 'tai-nguyen-co-hoi-nghe-nghiep', 'Khóa học, tài liệu, tuyển dụng và thực tập.', 40)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    sort_order = VALUES(sort_order);

INSERT INTO tags (name, slug, description) VALUES
    ('PHP', 'php', 'Ngôn ngữ PHP'),
    ('JavaScript', 'javascript', 'Ngôn ngữ JavaScript'),
    ('HTML/CSS', 'html-css', 'Giao diện web HTML và CSS'),
    ('MySQL', 'mysql', 'Cơ sở dữ liệu MySQL/MariaDB'),
    ('Python', 'python', 'Ngôn ngữ Python'),
    ('Machine Learning', 'machine-learning', 'Học máy'),
    ('NLP', 'nlp', 'Xử lý ngôn ngữ tự nhiên'),
    ('Computer Vision', 'computer-vision', 'Thị giác máy tính'),
    ('OpenAI API', 'openai-api', 'Phát triển với OpenAI API'),
    ('Gemini API', 'gemini-api', 'Phát triển với Gemini API'),
    ('Prompt Engineering', 'prompt-engineering', 'Kỹ nghệ thiết kế prompt'),
    ('XAMPP', 'xampp', 'Môi trường Apache, PHP và MariaDB cục bộ')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

COMMIT;
