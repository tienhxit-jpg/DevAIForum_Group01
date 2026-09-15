-- Extra mock posts for local development of DevAI Hub
-- Adds more diverse content on top of mock_data.sql (does NOT delete existing data).
-- Safe to run multiple times: existing slugs are skipped via INSERT IGNORE.

USE forum_db;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

SELECT id INTO @admin_id FROM users WHERE username = 'admin_devai';
SELECT id INTO @mod_id FROM users WHERE username = 'mod_linh';
SELECT id INTO @an_id FROM users WHERE username = 'an_nguyen';
SELECT id INTO @minh_id FROM users WHERE username = 'minh_tran';
SELECT id INTO @hai_id FROM users WHERE username = 'hai_le';
SELECT id INTO @linh_id FROM users WHERE username = 'thuy_linh';
SELECT id INTO @phuc_id FROM users WHERE username = 'quang_phuc';
SELECT id INTO @lan_id FROM users WHERE username = 'lan_anh';

SELECT id INTO @cat_basic FROM categories WHERE slug = 'hoc-tap-lap-trinh-co-ban';
SELECT id INTO @cat_ai FROM categories WHERE slug = 'ai-khoa-hoc-du-lieu';
SELECT id INTO @cat_qa FROM categories WHERE slug = 'hoi-dap-do-an-go-loi';
SELECT id INTO @cat_resource FROM categories WHERE slug = 'tai-nguyen-co-hoi-nghe-nghiep';

INSERT IGNORE INTO posts (
    author_id, category_id, title, slug, content_html, post_type,
    status, is_pinned, is_locked, view_count, created_at
) VALUES

(@phuc_id, @cat_basic,
 'So sánh Session và JWT khi xây dựng đăng nhập cho ứng dụng PHP',
 'so-sanh-session-jwt-dang-nhap-php',
 '<p>Session lưu trạng thái ở server, còn JWT tự chứa thông tin và không cần lưu trạng thái. Bài viết so sánh ưu nhược điểm khi dùng cho một diễn đàn quy mô nhỏ chạy trên XAMPP.</p>',
 'discussion', 'published', 0, 0, 168, CURRENT_TIMESTAMP - INTERVAL 20 DAY),

(@minh_id, @cat_qa,
 'CSRF token báo lỗi 419 khi submit form bằng fetch',
 'csrf-token-loi-419-fetch',
 '<p>Mình gọi <code>/api/csrf</code> lấy token và gắn vào header <code>X-CSRF-Token</code>, nhưng vẫn bị từ chối. Có ai gặp trường hợp tương tự với session cookie không?</p><pre><code>fetch("/api/posts", { method: "POST", headers: { "X-CSRF-Token": token } });</code></pre>',
 'question', 'published', 0, 0, 95, CURRENT_TIMESTAMP - INTERVAL 19 DAY),

(@hai_id, @cat_ai,
 'Fine-tuning vs Prompt Engineering: khi nào nên chọn cái nào?',
 'fine-tuning-vs-prompt-engineering',
 '<p>Với ngân sách hạn chế, prompt engineering thường là lựa chọn đầu tiên. Fine-tuning chỉ nên cân nhắc khi có tập dữ liệu chất lượng cao và nhu cầu lặp lại ổn định.</p>',
 'discussion', 'published', 0, 0, 302, CURRENT_TIMESTAMP - INTERVAL 18 DAY),

(@linh_id, @cat_ai,
 'Chia sẻ bộ prompt hệ thống cho chatbot hỗ trợ sinh viên IT',
 'bo-prompt-he-thong-chatbot-ho-tro-sinh-vien',
 '<p>Bộ prompt gồm vai trò, giới hạn phạm vi trả lời, định dạng markdown và cách xử lý câu hỏi ngoài chủ đề. Mọi người có thể góp ý thêm.</p>',
 'resource', 'published', 0, 0, 221, CURRENT_TIMESTAMP - INTERVAL 17 DAY),

(@an_id, @cat_basic,
 'Deploy project PHP từ XAMPP local lên hosting như thế nào?',
 'deploy-project-php-tu-xampp-len-hosting',
 '<p>Sau khi hoàn thành đồ án trên XAMPP, mình cần hướng dẫn các bước export database, cấu hình .env và upload source code lên hosting chia sẻ.</p>',
 'question', 'published', 0, 0, 143, CURRENT_TIMESTAMP - INTERVAL 16 DAY),

(@lan_id, @cat_resource,
 'Tổng hợp việc làm thực tập Backend PHP và Data cho sinh viên năm cuối',
 'tong-hop-viec-lam-thuc-tap-backend-php-data',
 '<p>Danh sách vị trí thực tập từ các công ty vừa và nhỏ, kèm yêu cầu kỹ năng phổ biến: PHP, MySQL, Git, và kiến thức cơ bản về API REST.</p>',
 'job', 'published', 0, 0, 187, CURRENT_TIMESTAMP - INTERVAL 15 DAY),

(@minh_id, @cat_qa,
 'File upload báo lỗi "failed to open stream: Permission denied" trên Windows',
 'file-upload-loi-permission-denied-windows',
 '<p>Thư mục <code>public/uploads</code> đã tồn tại nhưng PHP vẫn báo lỗi không ghi được file khi chạy qua Apache của XAMPP trên Windows.</p>',
 'question', 'published', 0, 0, 112, CURRENT_TIMESTAMP - INTERVAL 14 DAY),

(@phuc_id, @cat_basic,
 'Dùng CSS Grid hay Flexbox cho layout diễn đàn kiểu Reddit?',
 'css-grid-hay-flexbox-cho-layout-dien-dan',
 '<p>Layout 3 cột (sidebar - feed - panel phụ) mình đang phân vân giữa CSS Grid và Flexbox kết hợp media query cho responsive.</p>',
 'discussion', 'published', 0, 0, 176, CURRENT_TIMESTAMP - INTERVAL 13 DAY);

SELECT id INTO @ep1 FROM posts WHERE slug = 'so-sanh-session-jwt-dang-nhap-php';
SELECT id INTO @ep2 FROM posts WHERE slug = 'csrf-token-loi-419-fetch';
SELECT id INTO @ep3 FROM posts WHERE slug = 'fine-tuning-vs-prompt-engineering';
SELECT id INTO @ep4 FROM posts WHERE slug = 'bo-prompt-he-thong-chatbot-ho-tro-sinh-vien';
SELECT id INTO @ep5 FROM posts WHERE slug = 'deploy-project-php-tu-xampp-len-hosting';
SELECT id INTO @ep6 FROM posts WHERE slug = 'tong-hop-viec-lam-thuc-tap-backend-php-data';
SELECT id INTO @ep7 FROM posts WHERE slug = 'file-upload-loi-permission-denied-windows';
SELECT id INTO @ep8 FROM posts WHERE slug = 'css-grid-hay-flexbox-cho-layout-dien-dan';

INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep1, id FROM tags WHERE slug IN ('php');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep2, id FROM tags WHERE slug IN ('php', 'javascript', 'xampp');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep3, id FROM tags WHERE slug IN ('prompt-engineering', 'machine-learning', 'openai-api');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep4, id FROM tags WHERE slug IN ('prompt-engineering', 'gemini-api');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep5, id FROM tags WHERE slug IN ('php', 'xampp', 'mysql');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep6, id FROM tags WHERE slug IN ('php', 'python');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep7, id FROM tags WHERE slug IN ('php', 'xampp');
INSERT IGNORE INTO post_tags (post_id, tag_id)
SELECT @ep8, id FROM tags WHERE slug IN ('html-css', 'javascript');

INSERT INTO comments (post_id, author_id, parent_id, content_html, created_at) VALUES
(@ep1, @an_id, NULL, '<p>Với đồ án nhỏ thì session vẫn đơn giản và đủ dùng, JWT phù hợp hơn khi có nhiều service riêng biệt.</p>', CURRENT_TIMESTAMP - INTERVAL 19 DAY),
(@ep2, @mod_id, NULL, '<p>Kiểm tra token có được gửi kèm cookie session tương ứng không, và thời gian sống của token trong session có còn hạn hay không.</p>', CURRENT_TIMESTAMP - INTERVAL 18 DAY),
(@ep3, @linh_id, NULL, '<p>Đồng ý, mình thường chỉ fine-tune khi prompt đã tối ưu hết mức mà vẫn chưa đạt độ ổn định mong muốn.</p>', CURRENT_TIMESTAMP - INTERVAL 17 DAY),
(@ep5, @mod_id, NULL, '<p>Nên bắt đầu từ việc export database bằng mysqldump, sau đó kiểm tra phiên bản PHP trên hosting có khớp với local không.</p>', CURRENT_TIMESTAMP - INTERVAL 15 DAY),
(@ep7, @phuc_id, NULL, '<p>Thử kiểm tra quyền ghi của thư mục uploads và đảm bảo Apache của XAMPP không chạy dưới quyền hạn chế.</p>', CURRENT_TIMESTAMP - INTERVAL 13 DAY);

INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @an_id, @ep1, CURRENT_TIMESTAMP - INTERVAL 19 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @an_id AND post_id = @ep1);
INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @linh_id, @ep1, CURRENT_TIMESTAMP - INTERVAL 18 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @linh_id AND post_id = @ep1);
INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @mod_id, @ep3, CURRENT_TIMESTAMP - INTERVAL 17 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @mod_id AND post_id = @ep3);
INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @an_id, @ep4, CURRENT_TIMESTAMP - INTERVAL 16 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @an_id AND post_id = @ep4);
INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @hai_id, @ep6, CURRENT_TIMESTAMP - INTERVAL 14 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @hai_id AND post_id = @ep6);
INSERT INTO post_likes (user_id, post_id, created_at)
SELECT * FROM (SELECT @phuc_id, @ep8, CURRENT_TIMESTAMP - INTERVAL 13 DAY) AS t
WHERE NOT EXISTS (SELECT 1 FROM post_likes WHERE user_id = @phuc_id AND post_id = @ep8);

COMMIT;
