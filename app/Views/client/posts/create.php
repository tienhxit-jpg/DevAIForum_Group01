<div class="create-post-page">

    <div class="mb-4">
        <a href="/feed" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            Quay lại bài viết
        </a>

        <h1 class="fw-bold mt-3 mb-1">
            Tạo bài viết
        </h1>

        <p class="text-muted mb-0">
            Chia sẻ kiến thức và kinh nghiệm với cộng đồng DevAI Hub.
        </p>
    </div>


    <form action="/posts/create" method="POST" enctype="multipart/form-data">

        <div class="create-post-card">

            <div class="mb-4">

                <label for="title" class="form-label fw-semibold">
                    Tiêu đề bài viết
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="title"
                    name="title"
                    placeholder="Nhập tiêu đề bài viết..."
                    required
                >

            </div>


            <div class="mb-4">

                <label for="category" class="form-label fw-semibold">
                    Danh mục
                </label>

                <select
                    class="form-select"
                    id="category"
                    name="category"
                    required
                >

                    <option value="">
                        -- Chọn danh mục --
                    </option>

                    <option value="programming">
                        Học tập & Lập trình
                    </option>

                    <option value="ai-data">
                        AI / Data Science
                    </option>

                    <option value="qa">
                        Q&A / Bug Fixing
                    </option>

                    <option value="resources">
                        Tài nguyên & Cơ hội nghề nghiệp
                    </option>

                </select>

            </div>


            <div class="mb-4">

                <label for="content" class="form-label fw-semibold">
                    Nội dung bài viết
                </label>

                <textarea
                    class="form-control"
                    id="content"
                    name="content"
                    rows="12"
                    placeholder="Viết nội dung bài viết của bạn..."
                    required
                ></textarea>

                <div class="form-text">
                    Bạn có thể chia sẻ kiến thức, đặt câu hỏi hoặc hướng dẫn
                    giải quyết một vấn đề lập trình.
                </div>

            </div>


            <div class="mb-4">

                <label for="code" class="form-label fw-semibold">
                    <i class="bi bi-code-slash me-1"></i>
                    Đoạn code
                </label>

                <textarea
                    class="form-control code-editor"
                    id="code"
                    name="code"
                    rows="8"
                    placeholder="// Dán đoạn code của bạn vào đây..."
                ></textarea>

                <div class="form-text">
                    Có thể thêm code nếu bài viết liên quan đến lập trình.
                </div>

            </div>


            <div class="mb-4">

                <label for="tags" class="form-label fw-semibold">
                    Tags
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="tags"
                    name="tags"
                    placeholder="Ví dụ: JavaScript, React, PHP"
                >

                <div class="form-text">
                    Nhập các tag, cách nhau bằng dấu phẩy.
                </div>

            </div>


            <div class="mb-4">

                <label for="images" class="form-label fw-semibold">
                    <i class="bi bi-images me-1"></i>
                    Hình ảnh
                </label>

                <input
                    type="file"
                    class="form-control"
                    id="images"
                    name="images[]"
                    accept="image/*"
                    multiple
                >

                <div class="form-text">
                    Bạn có thể chọn nhiều hình ảnh.
                </div>

            </div>


            <div class="border-top pt-4">

                <div class="form-check mb-3">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="allow_comments"
                        name="allow_comments"
                        checked
                    >

                    <label
                        class="form-check-label"
                        for="allow_comments"
                    >
                        Cho phép thành viên bình luận
                    </label>

                </div>

            </div>


            <div class="create-post-actions">

                <a
                    href="/feed"
                    class="btn btn-outline-secondary"
                >
                    Hủy
                </a>

                <button
                    type="button"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-file-earmark me-1"></i>
                    Lưu nháp
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="bi bi-send me-1"></i>
                    Đăng bài
                </button>

            </div>

        </div>

    </form>

</div>