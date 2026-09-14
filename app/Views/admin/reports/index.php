<div class="admin-reports-page">

    <div class="admin-page-header mb-4">

        <div>
            <h1 class="admin-page-title">
                Quản lý báo cáo
            </h1>

            <p class="admin-page-subtitle">
                Kiểm tra và xử lý các báo cáo vi phạm từ người dùng.
            </p>
        </div>

        <div class="d-flex gap-2">

            <button class="btn btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i>
                Đánh dấu đã xử lý
            </button>

        </div>

    </div>


    <div class="row g-4 mb-4">

        <div class="col-md-4">

            <div class="card admin-card report-stat-card">

                <div class="card-body">

                    <div class="report-stat-icon pending">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div>
                        <small>Báo cáo chờ xử lý</small>
                        <h3>12</h3>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card admin-card report-stat-card">

                <div class="card-body">

                    <div class="report-stat-icon warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>

                    <div>
                        <small>Báo cáo nghiêm trọng</small>
                        <h3>5</h3>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card admin-card report-stat-card">

                <div class="card-body">

                    <div class="report-stat-icon resolved">
                        <i class="bi bi-check-circle"></i>
                    </div>

                    <div>
                        <small>Đã xử lý</small>
                        <h3>84</h3>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="card admin-card mb-4">

        <div class="admin-report-filters">

            <div class="admin-report-search">

                <i class="bi bi-search"></i>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Tìm kiếm báo cáo..."
                >

            </div>

            <select class="form-select admin-report-filter-select">

                <option selected>Tất cả loại báo cáo</option>
                <option>Spam</option>
                <option>Nội dung không phù hợp</option>
                <option>Thông tin sai lệch</option>
                <option>Khác</option>

            </select>

            <select class="form-select admin-report-filter-select">

                <option selected>Tất cả trạng thái</option>
                <option>Chờ xử lý</option>
                <option>Đang xử lý</option>
                <option>Đã xử lý</option>

            </select>

            <button class="btn btn-primary">
                <i class="bi bi-funnel me-1"></i>
                Lọc
            </button>

        </div>

    </div>


    <div class="card admin-card">

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table admin-reports-table align-middle mb-0">

                    <thead>

                        <tr>

                            <th style="width: 70px;">#</th>

                            <th>Nội dung bị báo cáo</th>

                            <th>Người báo cáo</th>

                            <th>Loại</th>

                            <th>Ngày báo cáo</th>

                            <th>Trạng thái</th>

                            <th style="width: 100px;">
                                Thao tác
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <tr>

                            <td>1</td>

                            <td>

                                <div class="admin-report-content">

                                    <strong>
                                        Hướng dẫn cài đặt PHP trên Windows
                                    </strong>

                                    <small>
                                        Bài viết #1024
                                    </small>

                                </div>

                            </td>

                            <td>
                                Nguyễn Văn An
                            </td>

                            <td>
                                <span class="report-type">
                                    Spam
                                </span>
                            </td>

                            <td>
                                12/09/2026
                            </td>

                            <td>
                                <span class="report-status pending">
                                    <i class="bi bi-circle-fill"></i>
                                    Chờ xử lý
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-light"
                                    title="Xem báo cáo"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>2</td>

                            <td>

                                <div class="admin-report-content">

                                    <strong>
                                        Cách hack tài khoản Facebook
                                    </strong>

                                    <small>
                                        Bài viết #1021
                                    </small>

                                </div>

                            </td>

                            <td>
                                Trần Minh Tú
                            </td>

                            <td>
                                <span class="report-type danger">
                                    Nội dung không phù hợp
                                </span>
                            </td>

                            <td>
                                11/09/2026
                            </td>

                            <td>
                                <span class="report-status pending">
                                    <i class="bi bi-circle-fill"></i>
                                    Chờ xử lý
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-light"
                                    title="Xem báo cáo"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>3</td>

                            <td>

                                <div class="admin-report-content">

                                    <strong>
                                        Download tài liệu Java miễn phí
                                    </strong>

                                    <small>
                                        Bài viết #1018
                                    </small>

                                </div>

                            </td>

                            <td>
                                Lê Hoàng Nam
                            </td>

                            <td>
                                <span class="report-type">
                                    Spam
                                </span>
                            </td>

                            <td>
                                10/09/2026
                            </td>

                            <td>
                                <span class="report-status processing">
                                    <i class="bi bi-circle-fill"></i>
                                    Đang xử lý
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-light"
                                    title="Xem báo cáo"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>4</td>

                            <td>

                                <div class="admin-report-content">

                                    <strong>
                                        Tutorial Machine Learning
                                    </strong>

                                    <small>
                                        Bài viết #1015
                                    </small>

                                </div>

                            </td>

                            <td>
                                Phạm Minh Thư
                            </td>

                            <td>
                                <span class="report-type">
                                    Thông tin sai lệch
                                </span>
                            </td>

                            <td>
                                09/09/2026
                            </td>

                            <td>
                                <span class="report-status resolved">
                                    <i class="bi bi-circle-fill"></i>
                                    Đã xử lý
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-light"
                                    title="Xem báo cáo"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </td>

                        </tr>


                        <tr>

                            <td>5</td>

                            <td>

                                <div class="admin-report-content">

                                    <strong>
                                        Tài liệu Python cho người mới
                                    </strong>

                                    <small>
                                        Bài viết #1011
                                    </small>

                                </div>

                            </td>

                            <td>
                                Hoàng Anh
                            </td>

                            <td>
                                <span class="report-type">
                                    Khác
                                </span>
                            </td>

                            <td>
                                08/09/2026
                            </td>

                            <td>
                                <span class="report-status resolved">
                                    <i class="bi bi-circle-fill"></i>
                                    Đã xử lý
                                </span>
                            </td>

                            <td>

                                <button
                                    class="btn btn-light"
                                    title="Xem báo cáo"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="admin-reports-pagination">

            <small class="text-muted">
                Hiển thị 1–5 trong tổng số 101 báo cáo
            </small>

            <nav>

                <ul class="pagination pagination-sm mb-0">

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

    </div>

</div>