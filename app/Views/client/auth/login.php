<div class="auth-page">

    <div class="auth-card">

        <div class="text-center mb-4">
            <div class="auth-icon">
                <i class="bi bi-code-slash"></i>
            </div>

            <h2 class="fw-bold mt-3">Đăng nhập</h2>

            <p class="text-muted">
                Đăng nhập vào tài khoản DevAI Hub
            </p>
        </div>

        <form action="/login" method="POST">

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

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="remember"
                        name="remember"
                    >

                    <label class="form-check-label" for="remember">
                        Ghi nhớ đăng nhập
                    </label>
                </div>

                <a href="#" class="auth-link">
                    Quên mật khẩu?
                </a>

            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Đăng nhập
            </button>

        </form>

        <div class="text-center mt-4">

            <span class="text-muted">
                Chưa có tài khoản?
            </span>

            <a href="/register" class="auth-link fw-semibold">
                Đăng ký ngay
            </a>

        </div>

    </div>

</div>