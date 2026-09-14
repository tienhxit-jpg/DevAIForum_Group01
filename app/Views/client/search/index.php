<div class="search-page">

    <div class="mb-4">
        <h1 class="fw-bold mb-2">
            <i class="bi bi-search me-2"></i>
            Tìm kiếm bài viết
        </h1>

        <p class="text-muted mb-0">
            Tìm kiếm các bài viết, câu hỏi và kiến thức trên DevAI Hub.
        </p>
    </div>


    <div class="search-box-card mb-4">

        <form action="/search" method="GET">

            <div class="input-group input-group-lg">

                <span class="input-group-text bg-white">
                    <i class="bi bi-search"></i>
                </span>

                <input
                    type="search"
                    class="form-control"
                    name="q"
                    placeholder="Nhập từ khóa cần tìm..."
                    value=""
                >

                <button
                    class="btn btn-primary px-4"
                    type="submit"
                >
                    Tìm kiếm
                </button>

            </div>

        </form>

    </div>


    <div class="search-filter-card mb-4">

        <div class="row g-3">

            <div class="col-md-6">

                <label
                    for="category"
                    class="form-label fw-semibold"
                >
                    Danh mục
                </label>

                <select
                    class="form-select"
                    id="category"
                    name="category"
                >

                    <option value="">
                        Tất cả danh mục
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
                        Tài nguyên & Cơ hội
                    </option>

                </select>

            </div>


            <div class="col-md-6">

                <label
                    for="sort"
                    class="form-label fw-semibold"
                >
                    Sắp xếp
                </label>

                <select
                    class="form-select"
                    id="sort"
                    name="sort"
                >

                    <option value="newest">
                        Mới nhất
                    </option>

                    <option value="oldest">
                        Cũ nhất
                    </option>

                    <option value="popular">
                        Phổ biến nhất
                    </option>

                </select>

            </div>

        </div>

    </div>


    <div class="search-result-header">

        <div>
            <h4 class="fw-bold mb-1">
                Kết quả tìm kiếm
            </h4>

            <p class="text-muted mb-0">
                Tìm thấy 3 bài viết phù hợp
            </p>
        </div>

    </div>


    <article class="search-result-card">

        <div class="d-flex gap-3">

            <div class="user-avatar">
                A
            </div>

            <div class="flex-grow-1">

                <a
                    href="/posts/1"
                    class="search-result-title"
                >
                    Hướng dẫn xử lý Array trong JavaScript cho người mới
                </a>

                <p class="text-muted mt-2 mb-3">
                    Tìm hiểu những phương thức Array thường được sử dụng
                    trong JavaScript như map(), filter() và find().
                </p>

                <div class="post-tags mb-3">

                    <span class="badge rounded-pill text-bg-light">
                        JavaScript
                    </span>

                    <span class="badge rounded-pill text-bg-light">
                        Beginner
                    </span>

                </div>

                <div class="search-result-meta">

                    <span>
                        <i class="bi bi-person me-1"></i>
                        Nguyễn Văn An
                    </span>

                    <span>
                        <i class="bi bi-hand-thumbs-up me-1"></i>
                        24
                    </span>

                    <span>
                        <i class="bi bi-chat me-1"></i>
                        8
                    </span>

                    <span>
                        2 giờ trước
                    </span>

                </div>

            </div>

        </div>

    </article>


    <article class="search-result-card">

        <div class="d-flex gap-3">

            <div class="user-avatar">
                M
            </div>

            <div class="flex-grow-1">

                <a
                    href="/posts/2"
                    class="search-result-title"
                >
                    Làm thế nào để xử lý lỗi CORS trong JavaScript?
                </a>

                <p class="text-muted mt-2 mb-3">
                    Một số cách kiểm tra và xử lý lỗi CORS thường gặp
                    khi frontend kết nối với API.
                </p>

                <div class="post-tags mb-3">

                    <span class="badge rounded-pill text-bg-light">
                        JavaScript
                    </span>

                    <span class="badge rounded-pill text-bg-light">
                        API
                    </span>

                    <span class="badge rounded-pill text-bg-light">
                        Bug Fixing
                    </span>

                </div>

                <div class="search-result-meta">

                    <span>
                        <i class="bi bi-person me-1"></i>
                        Minh Trần
                    </span>

                    <span>
                        <i class="bi bi-hand-thumbs-up me-1"></i>
                        18
                    </span>

                    <span>
                        <i class="bi bi-chat me-1"></i>
                        5
                    </span>

                    <span>
                        5 giờ trước
                    </span>

                </div>

            </div>

        </div>

    </article>


    <article class="search-result-card">

        <div class="d-flex gap-3">

            <div class="user-avatar">
                T
            </div>

            <div class="flex-grow-1">

                <a
                    href="/posts/3"
                    class="search-result-title"
                >
                    Những tài liệu học AI dành cho người mới bắt đầu
                </a>

                <p class="text-muted mt-2 mb-3">
                    Tổng hợp một số tài liệu và nguồn học AI,
                    Machine Learning phù hợp cho sinh viên.
                </p>

                <div class="post-tags mb-3">

                    <span class="badge rounded-pill text-bg-light">
                        AI
                    </span>

                    <span class="badge rounded-pill text-bg-light">
                        Machine Learning
                    </span>

                </div>

                <div class="search-result-meta">

                    <span>
                        <i class="bi bi-person me-1"></i>
                        Thanh Nguyễn
                    </span>

                    <span>
                        <i class="bi bi-hand-thumbs-up me-1"></i>
                        32
                    </span>

                    <span>
                        <i class="bi bi-chat me-1"></i>
                        11
                    </span>

                    <span>
                        Hôm qua
                    </span>

                </div>

            </div>

        </div>

    </article>


    <nav class="mt-4" aria-label="Search pagination">

        <ul class="pagination justify-content-center">

            <li class="page-item disabled">
                <a class="page-link" href="#">
                    Trước
                </a>
            </li>

            <li class="page-item active">
                <a class="page-link" href="#">
                    1
                </a>
            </li>

            <li class="page-item">
                <a class="page-link" href="#">
                    2
                </a>
            </li>

            <li class="page-item">
                <a class="page-link" href="#">
                    3
                </a>
            </li>

            <li class="page-item">
                <a class="page-link" href="#">
                    Sau
                </a>
            </li>

        </ul>

    </nav>

</div>