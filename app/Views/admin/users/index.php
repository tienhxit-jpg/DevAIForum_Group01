<div class="admin-users-page">

    <div class="admin-page-header">

        <div>
            <h1 class="fw-bold mb-1">
                Quản lý người dùng
            </h1>

            <p class="text-muted mb-0">
                Quản lý tài khoản và quyền truy cập của thành viên.
            </p>
        </div>

        <button class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Thêm người dùng
        </button>

    </div>


    <div class="admin-card mb-4">

        <div class="admin-user-filters">

            <div class="admin-user-search">
                <i class="bi bi-search"></i>

                <input
                    type="text"
                    class="form-control"
                    placeholder="Tìm theo tên hoặc email..."
                >
            </div>

            <select class="form-select admin-user-filter-select">
                <option selected>Tất cả vai trò</option>
                <option>Admin</option>
                <option>Moderator</option>
                <option>Member</option>
            </select>

            <select class="form-select admin-user-filter-select">
                <option selected>Tất cả trạng thái</option>
                <option>Hoạt động</option>
                <option>Bị khóa</option>
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
                    Danh sách người dùng
                </h5>

                <small class="text-muted">
                    Tổng cộng 1,248 người dùng
                </small>
            </div>

        </div>


        <div class="table-responsive">

            <table class="table admin-table admin-users-table align-middle mb-0">

                <thead>
                    <tr>
                        <th>Người dùng</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tham gia</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                    <tr>

                        <td>
                            <div class="admin-user-cell">

                                <div class="admin-user-table-avatar">
                                    A
                                </div>

                                <div>
                                    <strong>Nguyễn Văn An</strong>
                                    <small>
                                        nguyenvanan@example.com
                                    </small>
                                </div>

                            </div>
                        </td>

                        <td>
                            <span class="badge bg-primary-subtle text-primary">
                                Member
                            </span>
                        </td>

                        <td>
                            <span class="user-status active">
                                <i class="bi bi-circle-fill"></i>
                                Hoạt động
                            </span>
                        </td>

                        <td>
                            15/03/2026
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
                                title="Khóa tài khoản"
                            >
                                <i class="bi bi-lock"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-user-cell">

                                <div class="admin-user-table-avatar">
                                    M
                                </div>

                                <div>
                                    <strong>Trần Minh</strong>
                                    <small>
                                        tranminh@example.com
                                    </small>
                                </div>

                            </div>
                        </td>

                        <td>
                            <span class="badge bg-warning-subtle text-warning">
                                Moderator
                            </span>
                        </td>

                        <td>
                            <span class="user-status active">
                                <i class="bi bi-circle-fill"></i>
                                Hoạt động
                            </span>
                        </td>

                        <td>
                            02/04/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-danger">
                                <i class="bi bi-lock"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-user-cell">

                                <div class="admin-user-table-avatar">
                                    L
                                </div>

                                <div>
                                    <strong>Lê Hoàng</strong>
                                    <small>
                                        lehoang@example.com
                                    </small>
                                </div>

                            </div>
                        </td>

                        <td>
                            <span class="badge bg-primary-subtle text-primary">
                                Member
                            </span>
                        </td>

                        <td>
                            <span class="user-status blocked">
                                <i class="bi bi-circle-fill"></i>
                                Bị khóa
                            </span>
                        </td>

                        <td>
                            18/04/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-success">
                                <i class="bi bi-unlock"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-user-cell">

                                <div class="admin-user-table-avatar">
                                    P
                                </div>

                                <div>
                                    <strong>Phạm Anh</strong>
                                    <small>
                                        phamanh@example.com
                                    </small>
                                </div>

                            </div>
                        </td>

                        <td>
                            <span class="badge bg-danger-subtle text-danger">
                                Admin
                            </span>
                        </td>

                        <td>
                            <span class="user-status active">
                                <i class="bi bi-circle-fill"></i>
                                Hoạt động
                            </span>
                        </td>

                        <td>
                            01/05/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-danger">
                                <i class="bi bi-lock"></i>
                            </button>

                        </td>

                    </tr>


                    <tr>

                        <td>
                            <div class="admin-user-cell">

                                <div class="admin-user-table-avatar">
                                    T
                                </div>

                                <div>
                                    <strong>Thanh Nguyễn</strong>
                                    <small>
                                        thanhnguyen@example.com
                                    </small>
                                </div>

                            </div>
                        </td>

                        <td>
                            <span class="badge bg-primary-subtle text-primary">
                                Member
                            </span>
                        </td>

                        <td>
                            <span class="user-status active">
                                <i class="bi bi-circle-fill"></i>
                                Hoạt động
                            </span>
                        </td>

                        <td>
                            20/05/2026
                        </td>

                        <td class="text-end">

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button class="btn btn-sm btn-light">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button class="btn btn-sm btn-light text-danger">
                                <i class="bi bi-lock"></i>
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <div class="admin-users-pagination">

            <small class="text-muted">
                Hiển thị 1–5 trên 1,248 người dùng
            </small>

            <nav>

                <ul class="pagination pagination-sm mb-0">

                    <li class="page-item disabled">
                        <a class="page-link" href="#">
                            <i class="bi bi-chevron-left"></i>
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
                            ...
                        </a>
                    </li>

                    <li class="page-item">
                        <a class="page-link" href="#">
                            250
                        </a>
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