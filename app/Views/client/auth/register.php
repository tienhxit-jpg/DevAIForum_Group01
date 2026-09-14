<div class="auth-page">

    <div class="auth-card">

        <div class="text-center mb-4">
            <div class="auth-icon">
                <i class="bi bi-person-plus"></i>
            </div>

            <h2 class="fw-bold mt-3">Đăng ký</h2>

            <p class="text-muted">
                Tạo tài khoản và tham gia cộng đồng DevAI Hub
            </p>
        </div>

        <form action="/register" method="POST">

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">
                    Họ và tên
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="name"
                    name="name"
                    placeholder="Nhập họ và tên"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">
                    Email
                </label>

                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Nhập email của bạn"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">
                    Mật khẩu
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="Nhập mật khẩu"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label fw-semibold">
                    Xác nhận mật khẩu
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="Nhập lại mật khẩu"
                    required
                >
            </div>

            <div class="form-check mb-4">

                <input
                    class="form-check-input"
                    type="checkbox"
                    id="terms"
                    name="terms"
                    required
                >

                <label class="form-check-label" for="terms">
                    Tôi đồng ý với các điều khoản sử dụng
                </label>

            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-person-plus me-1"></i>
                Tạo tài khoản
            </button>

        </form>

        <div class="text-center mt-4">

            <span class="text-muted">
                Đã có tài khoản?
            </span>

            <a href="/login" class="auth-link fw-semibold">
                Đăng nhập
            </a>

        </div>

    </div>

</div>