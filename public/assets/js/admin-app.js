(function () {
    'use strict';

    const config = {
        baseUrl: document.body.getAttribute('data-base-url') || '',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };

    const state = {
        page: 1,
        filters: {},
    };

    // --- Helpers ---

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const container = document.getElementById('admin-toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `admin-toast ${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    async function apiCall(endpoint, method = 'GET', body = null) {
        const url = `${config.baseUrl}${endpoint}`;
        const headers = { Accept: 'application/json' };
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
            headers['X-CSRF-Token'] = config.csrfToken;
        }
        const options = { method: method.toUpperCase(), headers, credentials: 'same-origin' };
        if (body !== null) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(data?.error || data?.message || `Lỗi yêu cầu (${response.status})`);
        }
        return data;
    }

    function statusBadge(status, map) {
        const cls = map[status] || 'admin-badge-muted';
        return `<span class="admin-badge ${cls}">${escapeHtml(status)}</span>`;
    }

    // --- Routing ---

    const ROUTES = [
        { path: '/admin', label: 'Dashboard', icon: Icons.barChart(18), render: renderDashboard },
        { path: '/admin/users', label: 'Tài khoản', icon: Icons.user(18), render: renderUsers },
        { path: '/admin/posts', label: 'Bài viết', icon: Icons.fileText(18), render: renderPosts },
        { path: '/admin/comments', label: 'Bình luận', icon: Icons.messageCircle(18), render: renderComments },
        { path: '/admin/reports', label: 'Báo cáo vi phạm', icon: Icons.flag(18), render: renderReports },
        { path: '/admin/appeals', label: 'Kháng cáo', icon: Icons.alertTriangle(18), render: renderAppeals },
        { path: '/admin/taxonomy', label: 'Danh mục & Thẻ', icon: Icons.tag(18), render: renderTaxonomy },
    ];

    function currentPath() {
        let path = window.location.pathname;
        if (config.baseUrl && path.startsWith(config.baseUrl)) {
            path = path.slice(config.baseUrl.length) || '/';
        }
        return path.replace(/\/$/, '') || '/admin';
    }

    function navigate(path, push = true) {
        if (push) {
            window.history.pushState({}, '', config.baseUrl + path);
        }
        state.page = 1;
        state.filters = {};
        renderApp();
    }

    function renderShell(activeRoute) {
        const app = document.getElementById('admin-app');
        app.innerHTML = `
            <aside class="admin-sidebar">
                <div class="admin-brand"><span class="admin-brand-icon">${Icons.shieldCheck(16)}</span> DevAI Admin</div>
                <nav>
                    ${ROUTES.map(r => `
                        <a href="${config.baseUrl}${r.path}" class="admin-nav-link ${r.path === activeRoute.path ? 'active' : ''}" data-nav="${r.path}">
                            <span>${r.icon}</span><span>${r.label}</span>
                        </a>
                    `).join('')}
                </nav>
                <div class="admin-sidebar-footer">
                    <a href="${config.baseUrl}/">${Icons.arrowLeft(14)} Quay lại diễn đàn</a>
                </div>
            </aside>
            <main class="admin-main" id="admin-main"></main>
        `;
        app.querySelectorAll('[data-nav]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                navigate(link.getAttribute('data-nav'));
            });
        });
    }

    async function renderApp() {
        const path = currentPath();
        const route = ROUTES.find(r => r.path === path) || ROUTES[0];
        renderShell(route);
        const main = document.getElementById('admin-main');
        main.innerHTML = '<div class="admin-loading">Đang tải dữ liệu...</div>';
        try {
            await route.render(main);
        } catch (err) {
            main.innerHTML = `<div class="admin-empty">${escapeHtml(err.message)}</div>`;
        }
    }

    window.addEventListener('popstate', () => renderApp());

    // --- Dashboard ---

    function drawBarChart(canvas, series) {
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        const w = rect.width, h = rect.height;
        ctx.clearRect(0, 0, w, h);
        if (series.length === 0) {
            ctx.fillStyle = '#8b93a8';
            ctx.font = '13px sans-serif';
            ctx.fillText('Chưa có dữ liệu', 10, h / 2);
            return;
        }
        const max = Math.max(1, ...series.map(p => p.total));
        const barWidth = w / series.length;
        ctx.fillStyle = '#4f7cff';
        series.forEach((point, i) => {
            const barH = (point.total / max) * (h - 24);
            ctx.fillRect(i * barWidth + 4, h - barH - 18, Math.max(4, barWidth - 8), barH);
            ctx.fillStyle = '#8b93a8';
            ctx.font = '10px sans-serif';
            const label = (point.day || '').slice(5);
            ctx.fillText(label, i * barWidth + 4, h - 4);
            ctx.fillStyle = '#4f7cff';
        });
    }

    async function renderDashboard(main) {
        const res = await apiCall('/api/admin/dashboard');
        const d = res?.data || {};
        main.innerHTML = `
            <div class="admin-header">
                <div>
                    <h1>Tổng quan hệ thống</h1>
                    <p>Số liệu thành viên, nội dung và lượng truy cập.</p>
                </div>
            </div>
            <div class="admin-stat-grid">
                <div class="admin-stat-card"><div class="admin-stat-value">${d.users_total || 0}</div><div class="admin-stat-label">Thành viên</div></div>
                <div class="admin-stat-card"><div class="admin-stat-value">${d.posts_total || 0}</div><div class="admin-stat-label">Bài viết</div></div>
                <div class="admin-stat-card"><div class="admin-stat-value">${d.comments_total || 0}</div><div class="admin-stat-label">Bình luận</div></div>
                <div class="admin-stat-card"><div class="admin-stat-value">${d.reports_pending || 0}</div><div class="admin-stat-label">Báo cáo chờ</div></div>
                <div class="admin-stat-card"><div class="admin-stat-value">${d.users_new_30d || 0}</div><div class="admin-stat-label">Thành viên mới (30d)</div></div>
                <div class="admin-stat-card"><div class="admin-stat-value">${d.posts_new_30d || 0}</div><div class="admin-stat-label">Bài viết mới (30d)</div></div>
            </div>
            <div class="admin-panel">
                <h2>Lượng truy cập (14 ngày)</h2>
                <div class="admin-chart-wrap"><canvas id="chart-traffic" style="width:100%;height:100%;"></canvas></div>
            </div>
            <div class="admin-panel">
                <h2>Thành viên mới đăng ký (14 ngày)</h2>
                <div class="admin-chart-wrap"><canvas id="chart-growth" style="width:100%;height:100%;"></canvas></div>
            </div>
        `;
        drawBarChart(document.getElementById('chart-traffic'), d.traffic || []);
        drawBarChart(document.getElementById('chart-growth'), (d.user_growth || []).map(p => ({ day: p.day, total: p.total })));
    }

    // --- Users ---

    async function renderUsers(main) {
        renderListToolbar(main, 'Quản lý tài khoản', 'Tìm theo username, email hoặc tên hiển thị...', [
            { value: '', label: 'Tất cả trạng thái' },
            { value: 'active', label: 'Đang hoạt động' },
            { value: 'banned', label: 'Đã khóa' },
            { value: 'inactive', label: 'Không hoạt động' },
        ], loadUsers);
        await loadUsers();
    }

    async function loadUsers() {
        const body = document.getElementById('admin-list-body');
        const params = new URLSearchParams({ q: state.filters.q || '', status: state.filters.status || '', page: state.page });
        const res = await apiCall(`/api/admin/users?${params}`);
        const users = res?.data || [];
        if (users.length === 0) {
            body.innerHTML = '<div class="admin-empty">Không tìm thấy tài khoản nào.</div>';
            return;
        }
        body.innerHTML = `
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>ID</th><th>Tài khoản</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Hành động</th></tr></thead>
                <tbody>
                    ${users.map(u => `
                        <tr>
                            <td>#${u.id}</td>
                            <td><strong>${escapeHtml(u.username)}</strong><br><span style="color:var(--admin-text-muted);font-size:12px;">${escapeHtml(u.email || '')}</span></td>
                            <td>
                                <select class="admin-select" data-role-select="${u.id}" ${u.role_name === 'admin' ? 'disabled' : ''}>
                                    <option value="member" ${u.role_name === 'member' ? 'selected' : ''}>Member</option>
                                    <option value="moderator" ${u.role_name === 'moderator' ? 'selected' : ''}>Moderator</option>
                                    ${u.role_name === 'admin' ? '<option value="admin" selected>Admin</option>' : ''}
                                </select>
                            </td>
                            <td>${statusBadge(u.status, { active: 'admin-badge-success', banned: 'admin-badge-danger', inactive: 'admin-badge-muted' })}</td>
                            <td>${escapeHtml((u.created_at || '').slice(0, 10))}</td>
                            <td>
                                ${u.role_name === 'admin' ? '<span style="color:var(--admin-text-muted);font-size:12px;">—</span>' : (
                                    u.status === 'active'
                                        ? `<button class="admin-btn admin-btn-danger" data-ban="${u.id}">Khóa</button>`
                                        : `<button class="admin-btn admin-btn-success" data-unban="${u.id}">Mở khóa</button>`
                                )}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table></div>
            <div class="admin-pagination">
                <button class="admin-btn" id="admin-prev-page" ${state.page <= 1 ? 'disabled' : ''}>${Icons.arrowLeft(14)} Trước</button>
                <button class="admin-btn" id="admin-next-page" ${users.length < 30 ? 'disabled' : ''}>Sau ${Icons.arrowRight(14)}</button>
            </div>
        `;
        body.querySelectorAll('[data-role-select]').forEach(select => {
            select.addEventListener('change', async () => {
                try {
                    await apiCall(`/api/admin/users/${select.getAttribute('data-role-select')}/role`, 'PATCH', { role: select.value });
                    showToast('Đã cập nhật vai trò.', 'success');
                    loadUsers();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        body.querySelectorAll('[data-ban]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const reason = window.prompt('Lý do khóa tài khoản:', 'Vi phạm quy định diễn đàn');
                if (reason === null) return;
                try {
                    await apiCall(`/api/admin/users/${btn.getAttribute('data-ban')}/status`, 'PATCH', { status: 'banned', reason });
                    showToast('Đã khóa tài khoản.', 'success');
                    loadUsers();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        body.querySelectorAll('[data-unban]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await apiCall(`/api/admin/users/${btn.getAttribute('data-unban')}/status`, 'PATCH', { status: 'active' });
                    showToast('Đã mở khóa tài khoản.', 'success');
                    loadUsers();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        bindPagination(loadUsers, users.length);
    }

    // --- Posts ---

    async function renderPosts(main) {
        renderListToolbar(main, 'Quản lý bài viết', 'Tìm theo tiêu đề...', [
            { value: '', label: 'Tất cả trạng thái' },
            { value: 'published', label: 'Đã đăng' },
            { value: 'hidden', label: 'Đã ẩn' },
            { value: 'draft', label: 'Nháp' },
            { value: 'deleted', label: 'Đã xóa' },
        ], loadPosts);
        await loadPosts();
    }

    async function loadPosts() {
        const body = document.getElementById('admin-list-body');
        const params = new URLSearchParams({ q: state.filters.q || '', status: state.filters.status || '', page: state.page });
        const res = await apiCall(`/api/admin/posts?${params}`);
        const posts = res?.data || [];
        if (posts.length === 0) {
            body.innerHTML = '<div class="admin-empty">Không tìm thấy bài viết nào.</div>';
            return;
        }
        body.innerHTML = `
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Tiêu đề</th><th>Tác giả</th><th>Danh mục</th><th>Trạng thái</th><th>Lượt xem</th><th>Hành động</th></tr></thead>
                <tbody>
                    ${posts.map(p => `
                        <tr>
                            <td class="admin-text-truncate" title="${escapeHtml(p.title)}">${escapeHtml(p.title)} ${p.is_pinned ? `<span class="admin-row-icon" title="Đã ghim">${Icons.pin(14)}</span>` : ''} ${p.is_locked ? `<span class="admin-row-icon" title="Đã khóa">${Icons.lock(14)}</span>` : ''}</td>
                            <td>${escapeHtml(p.author_username)}</td>
                            <td>${escapeHtml(p.category_name || '—')}</td>
                            <td>${statusBadge(p.status, { published: 'admin-badge-success', hidden: 'admin-badge-danger', draft: 'admin-badge-muted', deleted: 'admin-badge-muted' })}</td>
                            <td>${p.view_count || 0}</td>
                            <td>
                                ${p.status === 'hidden'
                                    ? `<button class="admin-btn admin-btn-success" data-post-action="restore_post" data-id="${p.id}">Khôi phục</button>`
                                    : `<button class="admin-btn admin-btn-danger" data-post-action="hide_post" data-id="${p.id}">Ẩn</button>`
                                }
                                <button class="admin-btn" data-post-action="${p.is_pinned ? 'unpin_post' : 'pin_post'}" data-id="${p.id}">${p.is_pinned ? 'Bỏ ghim' : 'Ghim'}</button>
                                <button class="admin-btn" data-post-action="${p.is_locked ? 'unlock_post' : 'lock_post'}" data-id="${p.id}">${p.is_locked ? 'Mở khóa' : 'Khóa'}</button>
                                <button class="admin-btn admin-btn-danger" data-post-delete="${p.id}">Xóa vĩnh viễn</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table></div>
            <div class="admin-pagination">
                <button class="admin-btn" id="admin-prev-page" ${state.page <= 1 ? 'disabled' : ''}>${Icons.arrowLeft(14)} Trước</button>
                <button class="admin-btn" id="admin-next-page" ${posts.length < 30 ? 'disabled' : ''}>Sau ${Icons.arrowRight(14)}</button>
            </div>
        `;
        body.querySelectorAll('[data-post-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.getAttribute('data-post-action');
                const id = btn.getAttribute('data-id');
                let reason = null;
                if (action === 'hide_post') {
                    reason = window.prompt('Lý do ẩn bài viết:', '');
                    if (reason === null) return;
                }
                try {
                    await apiCall(`/api/admin/posts/${id}/moderate`, 'PATCH', { action, reason });
                    showToast('Đã cập nhật bài viết.', 'success');
                    loadPosts();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        body.querySelectorAll('[data-post-delete]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!window.confirm('Xóa vĩnh viễn bài viết này? Hành động không thể hoàn tác.')) return;
                try {
                    await apiCall(`/api/admin/posts/${btn.getAttribute('data-post-delete')}/permanent`, 'DELETE');
                    showToast('Đã xóa vĩnh viễn bài viết.', 'success');
                    loadPosts();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        bindPagination(loadPosts, posts.length);
    }

    // --- Comments ---

    async function renderComments(main) {
        renderListToolbar(main, 'Kiểm duyệt bình luận', 'Tìm theo nội dung...', [
            { value: '', label: 'Tất cả trạng thái' },
            { value: 'visible', label: 'Hiển thị' },
            { value: 'hidden', label: 'Đã ẩn' },
        ], loadComments);
        await loadComments();
    }

    async function loadComments() {
        const body = document.getElementById('admin-list-body');
        const params = new URLSearchParams({ q: state.filters.q || '', status: state.filters.status || '', page: state.page });
        const res = await apiCall(`/api/admin/comments?${params}`);
        const comments = res?.data || [];
        if (comments.length === 0) {
            body.innerHTML = '<div class="admin-empty">Không tìm thấy bình luận nào.</div>';
            return;
        }
        body.innerHTML = `
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Nội dung</th><th>Tác giả</th><th>Bài viết</th><th>Trạng thái</th><th>Hành động</th></tr></thead>
                <tbody>
                    ${comments.map(c => `
                        <tr>
                            <td class="admin-text-truncate" title="${escapeHtml(c.content_html)}">${escapeHtml(c.content_html)}</td>
                            <td>${escapeHtml(c.author_username)}</td>
                            <td class="admin-text-truncate">${escapeHtml(c.post_title || '')}</td>
                            <td>${statusBadge(c.status, { visible: 'admin-badge-success', hidden: 'admin-badge-danger' })}</td>
                            <td>
                                ${c.status === 'hidden'
                                    ? `<button class="admin-btn admin-btn-success" data-comment-action="restore_comment" data-id="${c.id}">Khôi phục</button>`
                                    : `<button class="admin-btn admin-btn-danger" data-comment-action="hide_comment" data-id="${c.id}">Ẩn</button>`
                                }
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table></div>
            <div class="admin-pagination">
                <button class="admin-btn" id="admin-prev-page" ${state.page <= 1 ? 'disabled' : ''}>${Icons.arrowLeft(14)} Trước</button>
                <button class="admin-btn" id="admin-next-page" ${comments.length < 30 ? 'disabled' : ''}>Sau ${Icons.arrowRight(14)}</button>
            </div>
        `;
        body.querySelectorAll('[data-comment-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await apiCall(`/api/admin/comments/${btn.getAttribute('data-id')}/moderate`, 'PATCH', { action: btn.getAttribute('data-comment-action') });
                    showToast('Đã cập nhật bình luận.', 'success');
                    loadComments();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        bindPagination(loadComments, comments.length);
    }

    // --- Reports ---

    async function renderReports(main) {
        main.innerHTML = `
            <div class="admin-header"><div><h1>Báo cáo vi phạm</h1><p>Danh sách báo cáo đang chờ xử lý.</p></div></div>
            <div class="admin-panel" id="admin-reports-panel"><div class="admin-loading">Đang tải...</div></div>
        `;
        await loadReports();
    }

    async function loadReports() {
        const panel = document.getElementById('admin-reports-panel');
        const res = await apiCall('/api/admin/reports?status=pending');
        const reports = res?.data || [];
        if (reports.length === 0) {
            panel.innerHTML = '<div class="admin-empty">Không có báo cáo nào đang chờ xử lý.</div>';
            return;
        }
        panel.innerHTML = reports.map(r => `
            <div style="padding:12px 0;border-bottom:1px solid var(--admin-border);display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                <div>
                    <span class="admin-badge admin-badge-danger">${escapeHtml(r.reason)}</span>
                    <strong style="margin-left:6px;">${r.post_id ? `Bài viết #${r.post_id}` : `Bình luận #${r.comment_id}`}</strong>
                    <div style="color:var(--admin-text-muted);font-size:12px;margin-top:4px;">${escapeHtml(r.details || 'Không có mô tả thêm')}</div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button class="admin-btn admin-btn-success" data-report-resolve="${r.id}">Duyệt</button>
                    <button class="admin-btn admin-btn-danger" data-report-reject="${r.id}">Bác bỏ</button>
                </div>
            </div>
        `).join('');
        panel.querySelectorAll('[data-report-resolve]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await apiCall(`/api/admin/reports/${btn.getAttribute('data-report-resolve')}`, 'PATCH', { status: 'resolved', note: 'Đã xử lý nội dung vi phạm.' });
                    showToast('Đã duyệt báo cáo.', 'success');
                    loadReports();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        panel.querySelectorAll('[data-report-reject]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await apiCall(`/api/admin/reports/${btn.getAttribute('data-report-reject')}`, 'PATCH', { status: 'rejected', note: 'Báo cáo không hợp lệ.' });
                    showToast('Đã bác bỏ báo cáo.', 'info');
                    loadReports();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
    }

    // --- Appeals ---

    async function renderAppeals(main) {
        main.innerHTML = `
            <div class="admin-header"><div><h1>Kháng cáo</h1><p>Kháng cáo từ tác giả các bài viết đã bị kiểm duyệt viên ẩn.</p></div></div>
            <div class="admin-panel" id="admin-appeals-panel"><div class="admin-loading">Đang tải...</div></div>
        `;
        await loadAppeals();
    }

    async function loadAppeals() {
        const panel = document.getElementById('admin-appeals-panel');
        const res = await apiCall('/api/admin/appeals?status=pending');
        const appeals = res?.data || [];
        if (appeals.length === 0) {
            panel.innerHTML = '<div class="admin-empty">Không có kháng cáo nào đang chờ xử lý.</div>';
            return;
        }
        panel.innerHTML = appeals.map(a => `
            <div style="padding:14px 0;border-bottom:1px solid var(--admin-border);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                    <div>
                        <strong>Bài viết #${a.post_id}: ${escapeHtml(a.post_title || '')}</strong>
                        <div style="color:var(--admin-text-muted);font-size:12px;margin-top:2px;">Tác giả: u/${escapeHtml(a.user_username)} (${escapeHtml(a.user_display_name || '')})</div>
                        <div style="color:var(--admin-text-muted);font-size:12px;margin-top:6px;"><strong>Lý do bị ẩn:</strong> ${escapeHtml(a.hidden_reason || 'Không có ghi chú.')}</div>
                        <div style="font-size:13px;margin-top:8px;padding:8px 10px;background:var(--admin-surface-alt, rgba(255,255,255,0.04));border-radius:6px;"><strong>Lý do kháng cáo:</strong> ${escapeHtml(a.reason)}</div>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <button class="admin-btn admin-btn-success" data-appeal-approve="${a.id}">Duyệt</button>
                        <button class="admin-btn admin-btn-danger" data-appeal-reject="${a.id}">Từ chối</button>
                    </div>
                </div>
            </div>
        `).join('');
        panel.querySelectorAll('[data-appeal-approve]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!window.confirm('Duyệt kháng cáo này? Bài viết sẽ được khôi phục ngay lập tức.')) return;
                try {
                    await apiCall(`/api/admin/appeals/${btn.getAttribute('data-appeal-approve')}`, 'PATCH', { status: 'approved' });
                    showToast('Đã duyệt kháng cáo, bài viết đã được khôi phục.', 'success');
                    loadAppeals();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        panel.querySelectorAll('[data-appeal-reject]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const reason = window.prompt('Nhập lý do từ chối kháng cáo. Lý do này sẽ được thông báo cho tác giả:');
                if (reason === null) return;
                if (!reason.trim()) {
                    showToast('Vui lòng nhập lý do từ chối.', 'error');
                    return;
                }
                try {
                    await apiCall(`/api/admin/appeals/${btn.getAttribute('data-appeal-reject')}`, 'PATCH', { status: 'rejected', reason: reason.trim() });
                    showToast('Đã từ chối kháng cáo.', 'info');
                    loadAppeals();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
    }

    // --- Taxonomy ---

    async function renderTaxonomy(main) {
        main.innerHTML = `
            <div class="admin-header"><div><h1>Danh mục & Thẻ</h1><p>Quản lý danh mục và thẻ công nghệ của diễn đàn.</p></div></div>
            <div class="admin-panel">
                <h2>Danh mục</h2>
                <div class="admin-toolbar">
                    <input class="admin-input" id="new-category-name" placeholder="Tên danh mục mới...">
                    <button class="admin-btn admin-btn-primary" id="add-category">Thêm danh mục</button>
                </div>
                <div id="category-list"></div>
            </div>
            <div class="admin-panel">
                <h2>Thẻ</h2>
                <div class="admin-toolbar">
                    <input class="admin-input" id="new-tag-name" placeholder="Tên thẻ mới...">
                    <button class="admin-btn admin-btn-primary" id="add-tag">Thêm thẻ</button>
                </div>
                <div id="tag-list"></div>
            </div>
        `;
        document.getElementById('add-category').addEventListener('click', async () => {
            const input = document.getElementById('new-category-name');
            if (!input.value.trim()) return;
            try {
                await apiCall('/api/admin/categories', 'POST', { name: input.value.trim() });
                input.value = '';
                showToast('Đã thêm danh mục.', 'success');
                loadTaxonomy();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
        document.getElementById('add-tag').addEventListener('click', async () => {
            const input = document.getElementById('new-tag-name');
            if (!input.value.trim()) return;
            try {
                await apiCall('/api/admin/tags', 'POST', { name: input.value.trim() });
                input.value = '';
                showToast('Đã thêm thẻ.', 'success');
                loadTaxonomy();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
        await loadTaxonomy();
    }

    async function loadTaxonomy() {
        const [catRes, tagRes] = await Promise.all([apiCall('/api/categories'), apiCall('/api/tags')]);
        const categories = catRes?.data || [];
        const tags = tagRes?.data || [];
        document.getElementById('category-list').innerHTML = categories.length === 0
            ? '<div class="admin-empty">Chưa có danh mục nào.</div>'
            : `<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tên</th><th>Hành động</th></tr></thead><tbody>
                ${categories.map(c => `<tr><td>${escapeHtml(c.name)}</td><td><button class="admin-btn admin-btn-danger" data-del-category="${c.id}">Xóa</button></td></tr>`).join('')}
            </tbody></table></div>`;
        document.getElementById('tag-list').innerHTML = tags.length === 0
            ? '<div class="admin-empty">Chưa có thẻ nào.</div>'
            : `<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Tên</th><th>Hành động</th></tr></thead><tbody>
                ${tags.map(t => `<tr><td>${escapeHtml(t.name)}</td><td><button class="admin-btn admin-btn-danger" data-del-tag="${t.id}">Xóa</button></td></tr>`).join('')}
            </tbody></table></div>`;
        document.querySelectorAll('[data-del-category]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!window.confirm('Xóa danh mục này?')) return;
                try {
                    await apiCall(`/api/admin/categories/${btn.getAttribute('data-del-category')}`, 'DELETE');
                    showToast('Đã xóa danh mục.', 'success');
                    loadTaxonomy();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
        document.querySelectorAll('[data-del-tag]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!window.confirm('Xóa thẻ này?')) return;
                try {
                    await apiCall(`/api/admin/tags/${btn.getAttribute('data-del-tag')}`, 'DELETE');
                    showToast('Đã xóa thẻ.', 'success');
                    loadTaxonomy();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });
    }

    // --- Shared list toolbar/pagination ---

    function renderListToolbar(main, title, searchPlaceholder, statusOptions, loadFn) {
        main.innerHTML = `
            <div class="admin-header"><div><h1>${title}</h1></div></div>
            <div class="admin-panel">
                <div class="admin-toolbar">
                    <input class="admin-input" id="admin-search-input" placeholder="${searchPlaceholder}" style="flex:1;min-width:220px;">
                    <select class="admin-select" id="admin-status-filter">
                        ${statusOptions.map(o => `<option value="${o.value}">${o.label}</option>`).join('')}
                    </select>
                </div>
                <div id="admin-list-body"><div class="admin-loading">Đang tải...</div></div>
            </div>
        `;
        const searchInput = document.getElementById('admin-search-input');
        const statusFilter = document.getElementById('admin-status-filter');
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                state.filters.q = searchInput.value.trim();
                state.page = 1;
                loadFn();
            }, 350);
        });
        statusFilter.addEventListener('change', () => {
            state.filters.status = statusFilter.value;
            state.page = 1;
            loadFn();
        });
    }

    function bindPagination(loadFn, resultCount) {
        document.getElementById('admin-prev-page')?.addEventListener('click', () => {
            if (state.page > 1) {
                state.page -= 1;
                loadFn();
            }
        });
        document.getElementById('admin-next-page')?.addEventListener('click', () => {
            if (resultCount >= 30) {
                state.page += 1;
                loadFn();
            }
        });
    }

    // --- Init ---

    renderApp();
})();
