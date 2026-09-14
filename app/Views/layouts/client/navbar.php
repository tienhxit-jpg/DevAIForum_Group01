<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">

        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-code-slash"></i>
            DevAI Hub
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link" href="/">
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/feed">
                        Bài viết
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/search">
                        Tìm kiếm
                    </a>
                </li>

            </ul>

            <form class="d-flex me-3" action="/search" method="GET">
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    placeholder="Tìm kiếm bài viết..."
                    aria-label="Tìm kiếm"
                >

                <button class="btn btn-primary ms-2" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <div class="d-flex gap-2">

                <a href="/login" class="btn btn-outline-primary">
                    Đăng nhập
                </a>

                <a href="/register" class="btn btn-primary">
                    Đăng ký
                </a>

            </div>

        </div>
    </div>
</nav>
