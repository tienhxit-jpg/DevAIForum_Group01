<div class="admin-posts-page">

    <div class="admin-page-header">

        <div>
            <h1 class="fw-bold mb-1">
                Quản lý bài viết
            </h1>

            <p class="text-muted mb-0">
                Quản lý, kiểm duyệt và xử lý các bài viết trên diễn đàn.
            </p>
        </div>

        <a href="/admin/posts/pending" class="btn btn-outline-primary">
            <i class="bi bi-hourglass-split me-1"></i>
            Bài viết chờ duyệt
        </a>

    </div>


    <div class="admin-card mb-4">

        <div class="admin-post-filters">

            <div class="admin-post-search">
                <i class="bi bi-search"></i>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Tìm kiếm bài viết..."
                >
            </div>

            <select class="form-select admin-post-filter-select">
                <option selected>Tất cả danh mục</option>
                <option>Học tập & Lập trình</option>
                <option>AI / Data Science</option>
                <option>Q&A / Bug Fixing</option>
                <option>Tài nguyên & Cơ hội</option>
            </select>

            <select class="form-select admin-post-filter-select">
                <option selected>Tất cả trạng thái</option>
                <option>Đã duyệt</option>
                <option>Chờ duyệt</option>
                <option>Đã ẩn</option>
                <option>Đã khóa</option>
            </select>

            <button class="btn btn-outline-primary">
                <i class="bi bi-funnel me-1"></i>
                Lọc
            </button>

        </div>

    </div>


    <div class="admin-card">

        <div class="admin-card-header">

            <div>
                <h5 class="fw-bold mb-1">
                    Danh sách bài viết
                </h5>

                <small class="text-muted">
                    Tổng cộng 3,672 bài viết
                </small>
            </div>

        </div>


        <div class="table-responsive">

            <table class="table admin-table admin-posts-table align-middle mb-0">

                <thead>
                    <tr>
                        <th>Bài viết</th>
                        <th>Tác giả</th>
                        <th>Danh mục</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>

                        <td>
                            <div class="admin-post-cell">
                                <strong>
                                    Hướng dẫn xử lý Array trong JavaScript
                                </strong>

                                <small>
                                    24 lượt thích · 8 bình luận
                                </small>
                            </div>
                        </td>

                        <td>
                            Nguyễn Văn An
                        </td>

                        <td>
                            <span class="badge bg-primary-subtle text-primary">
                                Lập trình
                            </span>
                        </td>

                        <td>
                            <span class="post-status approved">
                                <i class="bi bi-circle-fill"></i>
                                Đã duyệt
                            </span>
                        </td>

                        <td>
                            12/09/2026
                        </td>

                        <td class="text-end">

                            <button
                                class="btn btn-sm btn-light"
                                title="Xem"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                            <button
                                class="btn btn-sm btn-light"
                                title="Chỉnh sửa"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button
                                class="btn btn-sm btn-light text-danger"
                                title="Ẩn bài viết"
                            >
                                <i class="bi bi-eye-slash"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-post-cell">
                                <strong>
                                    AI có thể hỗ trợ lập trình viên như thế nào?
                                </strong>

                                <small>
                                    18 lượt thích · 5 bình luận
                                </small>
                            </div>
                        </td>

                        <td>
                            Thanh Nguyễn
                        </td>

                        <td>
                            <span class="badge bg-info-subtle text-info">
                                AI / Data Science
                            </span>
                        </td>

                        <td>
                            <span class="post-status approved">
                                <i class="bi bi-circle-fill"></i>
                                Đã duyệt
                            </span>
                        </td>

                        <td>
                            11/09/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-danger">
                                <i class="bi bi-eye-slash"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-post-cell">
                                <strong>
                                    Cách xử lý lỗi CORS khi gọi API
                                </strong>

                                <small>
                                    31 lượt thích · 12 bình luận
                                </small>
                            </div>
                        </td>

                        <td>
                            Lê Hoàng
                        </td>

                        <td>
                            <span class="badge bg-warning-subtle text-warning">
                                Q&A / Bug Fixing
                            </span>
                        </td>

                        <td>
                            <span class="post-status pending">
                                <i class="bi bi-circle-fill"></i>
                                Chờ duyệt
                            </span>
                        </td>

                        <td>
                            10/09/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button
                                class="btn btn-sm btn-success"
                                title="Duyệt"
                            >
                                <i class="bi bi-check-lg"></i>
                            </button>

                            <button
                                class="btn btn-sm btn-danger"
                                title="Từ chối"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-post-cell">
                                <strong>
                                    Tổng hợp tài liệu học HTML CSS cho người mới
                                </strong>

                                <small>
                                    42 lượt thích · 16 bình luận
                                </small>
                            </div>
                        </td>

                        <td>
                            Phạm Anh
                        </td>

                        <td>
                            <span class="badge bg-primary-subtle text-primary">
                                Lập trình
                            </span>
                        </td>

                        <td>
                            <span class="post-status approved">
                                <i class="bi bi-circle-fill"></i>
                                Đã duyệt
                            </span>
                        </td>

                        <td>
                            09/09/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-danger">
                                <i class="bi bi-eye-slash"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-post-cell">
                                <strong>
                                    Chia sẻ cơ hội thực tập ngành IT 2026
                                </strong>

                                <small>
                                    15 lượt thích · 3 bình luận
                                </small>
                            </div>
                        </td>

                        <td>
                            Minh Trần
                        </td>

                        <td>
                            <span class="badge bg-success-subtle text-success">
                                Tài nguyên & Cơ hội
                            </span>
                        </td>

                        <td>
                            <span class="post-status hidden">
                                <i class="bi bi-circle-fill"></i>
                                Đã ẩn
                            </span>
                        </td>

                        <td>
                            08/09/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button
                                class="btn btn-sm btn-light text-success"
                                title="Hiện bài viết"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-three-dots"></i>
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <div class="admin-posts-pagination">

            <small class="text-muted">
                Hiển thị 1–5 trên 3,672 bài viết
            </small>

            <nav>

                <ul class="pagination pagination-sm mb-0">

                    <li class="page-item disabled">
                        <a class="page-link" href="#">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">...</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">735</a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>

                </ul>

            </nav>

        </div>

    </div>

</div>