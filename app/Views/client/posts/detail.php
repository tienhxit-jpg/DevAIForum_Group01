<div class="post-detail-page">

    <div class="mb-3">
        <a href="/feed" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            Quay lại danh sách bài viết
        </a>
    </div>


    <article class="post-detail-card">

        <div class="d-flex justify-content-between align-items-start">

            <div class="d-flex align-items-center gap-3">

                <div class="user-avatar">
                    A
                </div>

                <div>
                    <h6 class="mb-1 fw-semibold">
                        Nguyễn Văn An
                    </h6>

                    <small class="text-muted">
                        Đăng 2 giờ trước
                    </small>
                </div>

            </div>

            <button class="btn btn-light btn-sm">
                <i class="bi bi-three-dots"></i>
            </button>

        </div>


        <div class="mt-4 mb-3">

            <span class="badge bg-primary-subtle text-primary">
                Học tập & Lập trình
            </span>

        </div>


        <h1 class="post-detail-title">
            Hướng dẫn xử lý Array trong JavaScript cho người mới
        </h1>


        <div class="post-content">

            <p>
                Array là một trong những cấu trúc dữ liệu được sử dụng
                rất thường xuyên trong JavaScript. Nếu bạn mới bắt đầu
                học JavaScript, việc hiểu cách làm việc với Array sẽ
                giúp ích rất nhiều khi xây dựng các ứng dụng thực tế.
            </p>

            <p>
                Dưới đây là một số phương thức Array thường gặp:
            </p>

            <h4>1. map()</h4>

            <p>
                Phương thức <strong>map()</strong> được sử dụng để tạo
                ra một mảng mới từ các phần tử của mảng ban đầu.
            </p>

            <pre><code>const numbers = [1, 2, 3, 4];

const result = numbers.map(number => number * 2);

console.log(result);</code></pre>


            <h4>2. filter()</h4>

            <p>
                Phương thức <strong>filter()</strong> giúp lọc các phần tử
                thỏa mãn một điều kiện nhất định.
            </p>

            <pre><code>const numbers = [1, 2, 3, 4, 5];

const result = numbers.filter(number => number > 3);

console.log(result);</code></pre>


            <h4>3. find()</h4>

            <p>
                Phương thức <strong>find()</strong> trả về phần tử đầu tiên
                thỏa mãn điều kiện.
            </p>

            <pre><code>const numbers = [10, 20, 30, 40];

const result = numbers.find(number => number > 20);

console.log(result);</code></pre>

        </div>


        <div class="post-tags mt-4">

            <span class="badge rounded-pill text-bg-light">
                JavaScript
            </span>

            <span class="badge rounded-pill text-bg-light">
                Beginner
            </span>

            <span class="badge rounded-pill text-bg-light">
                Programming
            </span>

        </div>


        <div class="post-detail-actions">

            <button class="btn btn-outline-primary">
                <i class="bi bi-hand-thumbs-up me-1"></i>
                Thích
                <span class="ms-1">24</span>
            </button>

            <button class="btn btn-outline-secondary">
                <i class="bi bi-bookmark me-1"></i>
                Lưu bài viết
            </button>

            <button class="btn btn-outline-danger">
                <i class="bi bi-flag me-1"></i>
                Báo cáo
            </button>

        </div>

    </article>


    <section class="comments-section mt-4">

        <h4 class="fw-bold mb-3">
            Bình luận
            <span class="text-muted fs-6">(8)</span>
        </h4>


        <div class="comment-form-card mb-4">

            <textarea
                class="form-control mb-3"
                rows="4"
                placeholder="Viết bình luận của bạn..."
            ></textarea>

            <div class="text-end">

                <button class="btn btn-primary">
                    <i class="bi bi-send me-1"></i>
                    Gửi bình luận
                </button>

            </div>

        </div>


        <div class="comment-card">

            <div class="d-flex gap-3">

                <div class="user-avatar">
                    M
                </div>

                <div class="flex-grow-1">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h6 class="fw-semibold mb-1">
                                Minh Trần
                            </h6>

                            <small class="text-muted">
                                1 giờ trước
                            </small>
                        </div>

                        <button class="btn btn-sm btn-light">
                            <i class="bi bi-three-dots"></i>
                        </button>

                    </div>

                    <p class="mt-2 mb-2">
                        Bài viết rất dễ hiểu. Phần map() và filter()
                        giải thích khá rõ cho người mới.
                    </p>

                    <button class="btn btn-sm btn-link p-0">
                        <i class="bi bi-hand-thumbs-up me-1"></i>
                        Thích
                    </button>

                </div>

            </div>

        </div>


        <div class="comment-card">

            <div class="d-flex gap-3">

                <div class="user-avatar">
                    T
                </div>

                <div class="flex-grow-1">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h6 class="fw-semibold mb-1">
                                Thanh Nguyễn
                            </h6>

                            <small class="text-muted">
                                30 phút trước
                            </small>
                        </div>

                        <button class="btn btn-sm btn-light">
                            <i class="bi bi-three-dots"></i>
                        </button>

                    </div>

                    <p class="mt-2 mb-2">
                        Cho mình hỏi nếu muốn tìm nhiều phần tử cùng lúc
                        thì nên sử dụng phương thức nào?
                    </p>

                    <button class="btn btn-sm btn-link p-0">
                        <i class="bi bi-hand-thumbs-up me-1"></i>
                        Thích
                    </button>

                </div>

            </div>

        </div>


    </section>

</div>