/**
 * DevAI Hub - Reddit-Style Single Page Application
 * Pure Vanilla JS, Fast & Lightweight, 100% CSP Compliant
 */

(function () {
    'use strict';

    // --- State & Configuration ---
    const config = {
        baseUrl: document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };

    const state = {
        currentUser: null,
        categories: [],
        tags: [],
        feed: [],
        feedPage: 1,
        feedHasMore: false,
        feedLoading: false,
        activeView: 'feed', // 'feed' | 'post-detail' | 'moderation'
        currentPostId: null,
        currentPostDetail: null,
        filters: {
            sort: 'popular', // 'popular' | 'newest'
            type: '',        // '' | 'discussion' | 'question' | 'resource' | 'job'
            category: '',    // id or slug
            tag: '',         // id or slug
            q: '',
            solved: '',      // '1' to filter solved questions
            unanswered: '',  // '1' to filter posts with no comments
        },
        notifications: [],
        unreadNotificationsCount: 0,
        theme: localStorage.getItem('devai_theme') || 'dark',

        viewedProfile: null,
        profileTab: 'overview',
        profileTabItems: [],
        profileTabPage: 1,
        profileTabHasMore: false,
        profileTabLoading: false,
    };

    // Apply theme immediately
    document.documentElement.setAttribute('data-theme', state.theme);

    // --- Utility Functions ---
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // --- Simple WYSIWYG rich text editor (no markdown knowledge required) ---
    function richTextEditorHtml(id, placeholder) {
        const btn = (cmd, label, title) =>
            `<button type="button" class="richtext-toolbar-btn" data-cmd="${cmd}" data-richtext-target="${id}" title="${escapeHtml(title)}">${label}</button>`;
        const sep = '<div class="richtext-toolbar-sep"></div>';
        return `
            <div class="richtext-wrapper">
                <div class="richtext-toolbar">
                    ${btn('bold', '<b>B</b>', 'Đậm')}
                    ${btn('italic', '<i>I</i>', 'Nghiêng')}
                    ${btn('underline', '<u>U</u>', 'Gạch chân')}
                    ${btn('strike', '<s>S</s>', 'Gạch ngang')}
                    ${sep}
                    ${btn('h2', 'H2', 'Tiêu đề')}
                    ${btn('quote', '&ldquo;&rdquo;', 'Trích dẫn')}
                    ${btn('code', '&lt;/&gt;', 'Code (inline)')}
                    ${btn('codeblock', '{ }', 'Khối code')}
                    ${sep}
                    ${btn('ul', '&bull; —', 'Danh sách dấu chấm')}
                    ${btn('ol', '1. —', 'Danh sách số')}
                    ${sep}
                    ${btn('link', '&#128279;', 'Chèn liên kết')}
                    ${btn('clear', 'Tx', 'Xóa định dạng')}
                </div>
                <div class="richtext-editor" id="${id}" contenteditable="true" data-placeholder="${escapeHtml(placeholder || '')}"></div>
            </div>
        `;
    }

    function bindRichTextToolbar(id) {
        const editor = document.getElementById(id);
        if (!editor) return;

        const updateToolbarState = () => {
            document.querySelectorAll(`[data-richtext-target="${id}"]`).forEach(button => {
                const cmd = button.getAttribute('data-cmd');
                const stateMap = { bold: 'bold', italic: 'italic', underline: 'underline', strike: 'strikeThrough', ul: 'insertUnorderedList', ol: 'insertOrderedList' };
                let active = false;
                try {
                    if (stateMap[cmd]) {
                        active = document.queryCommandState(stateMap[cmd]);
                    } else if (cmd === 'h2') {
                        active = document.queryCommandValue('formatBlock').toLowerCase() === 'h2';
                    } else if (cmd === 'quote') {
                        active = document.queryCommandValue('formatBlock').toLowerCase() === 'blockquote';
                    } else if (cmd === 'codeblock') {
                        active = document.queryCommandValue('formatBlock').toLowerCase() === 'pre';
                    }
                } catch (e) { /* queryCommandState can throw on some browsers before focus */ }
                button.classList.toggle('active', !!active);
            });
        };

        document.querySelectorAll(`[data-richtext-target="${id}"]`).forEach(button => {
            button.addEventListener('mousedown', (e) => e.preventDefault());
            button.addEventListener('click', () => {
                editor.focus();
                const cmd = button.getAttribute('data-cmd');
                switch (cmd) {
                    case 'bold': document.execCommand('bold'); break;
                    case 'italic': document.execCommand('italic'); break;
                    case 'underline': document.execCommand('underline'); break;
                    case 'strike': document.execCommand('strikeThrough'); break;
                    case 'h2': {
                        const isActive = document.queryCommandValue('formatBlock').toLowerCase() === 'h2';
                        document.execCommand('formatBlock', false, isActive ? 'p' : 'h2');
                        break;
                    }
                    case 'quote': {
                        const isActive = document.queryCommandValue('formatBlock').toLowerCase() === 'blockquote';
                        document.execCommand('formatBlock', false, isActive ? 'p' : 'blockquote');
                        break;
                    }
                    case 'codeblock': {
                        const isActive = document.queryCommandValue('formatBlock').toLowerCase() === 'pre';
                        document.execCommand('formatBlock', false, isActive ? 'p' : 'pre');
                        break;
                    }
                    case 'code': {
                        const sel = window.getSelection();
                        const text = sel && sel.toString() ? sel.toString() : 'code';
                        document.execCommand('insertHTML', false, `<code>${escapeHtml(text)}</code>`);
                        break;
                    }
                    case 'ul': document.execCommand('insertUnorderedList'); break;
                    case 'ol': document.execCommand('insertOrderedList'); break;
                    case 'link': {
                        const url = window.prompt('Nhập đường dẫn liên kết (https://...)');
                        if (url && url.trim()) {
                            let safeUrl = url.trim();
                            if (!/^https?:\/\//i.test(safeUrl)) safeUrl = 'https://' + safeUrl;
                            document.execCommand('createLink', false, safeUrl);
                        }
                        break;
                    }
                    case 'clear': document.execCommand('removeFormat'); break;
                }
                updateToolbarState();
            });
        });

        editor.addEventListener('keyup', updateToolbarState);
        editor.addEventListener('mouseup', updateToolbarState);
        editor.addEventListener('focus', updateToolbarState);
    }

    function normalizeRichHtml(html) {
        const container = document.createElement('div');
        container.innerHTML = html;
        const renameMap = { B: 'strong', I: 'em', STRIKE: 's', DIV: 'p' };
        const walk = (node) => {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType !== 1) return;
                child.removeAttribute('style');
                child.removeAttribute('class');
                const newTag = renameMap[child.tagName];
                if (newTag) {
                    const replacement = document.createElement(newTag);
                    while (child.firstChild) replacement.appendChild(child.firstChild);
                    child.replaceWith(replacement);
                    walk(replacement);
                    return;
                }
                walk(child);
            });
        };
        walk(container);
        return container.innerHTML.trim();
    }

    function applyPostVoteResult(postId, res) {
        const netScore = (res.like_count || 0) - (res.dislike_count || 0);

        const scoreEl = document.getElementById(`score-${postId}`);
        if (scoreEl) {
            scoreEl.textContent = netScore;
            scoreEl.classList.toggle('upvoted', !!res.liked);
            scoreEl.classList.toggle('downvoted', !!res.disliked);
        }

        const scoreTextEl = document.getElementById(`score-text-${postId}`);
        if (scoreTextEl) {
            scoreTextEl.textContent = `${netScore} Thích`;
        }

        document.querySelectorAll(`[data-action="like"][data-post-id="${postId}"]`).forEach(btn => {
            btn.classList.toggle('active', !!res.liked);
            btn.setAttribute('aria-pressed', String(!!res.liked));
        });
        document.querySelectorAll(`[data-action="downvote"][data-post-id="${postId}"]`).forEach(btn => {
            btn.classList.toggle('active', !!res.disliked);
            btn.setAttribute('aria-pressed', String(!!res.disliked));
        });

        const numericPostId = parseInt(postId, 10);
        const feedItem = state.feed.find(item => parseInt(item.id, 10) === numericPostId);
        if (feedItem) {
            feedItem.like_count = res.like_count;
            feedItem.dislike_count = res.dislike_count;
            feedItem.viewer_liked = res.liked;
            feedItem.viewer_disliked = res.disliked;
        }
        if (state.currentPostDetail && parseInt(state.currentPostDetail.id, 10) === numericPostId) {
            state.currentPostDetail.like_count = res.like_count;
            state.currentPostDetail.dislike_count = res.dislike_count;
            state.currentPostDetail.viewer_liked = res.liked;
            state.currentPostDetail.viewer_disliked = res.disliked;
        }
    }

    function timeAgo(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString.replace(' ', 'T'));
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        if (seconds < 60) return 'vừa xong';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes} phút trước`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} giờ trước`;
        const days = Math.floor(hours / 24);
        if (days < 30) return `${days} ngày trước`;
        const months = Math.floor(days / 30);
        if (months < 12) return `${months} tháng trước`;
        return `${Math.floor(months / 12)} năm trước`;
    }

    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = Icons.info(16);
        if (type === 'success') icon = Icons.checkCircle(16);
        if (type === 'error') icon = Icons.alertTriangle(16);
        
        toast.innerHTML = `<span>${icon}</span><span>${escapeHtml(message)}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
            toast.style.transition = 'all 200ms ease';
            setTimeout(() => toast.remove(), 200);
        }, 3200);
    }

    // --- API Service ---
    async function apiCall(endpoint, method = 'GET', body = null, isFormData = false) {
        const url = `${config.baseUrl}${endpoint}`;
        const headers = {
            'Accept': 'application/json',
        };

        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
            headers['X-CSRF-Token'] = config.csrfToken;
        }

        const options = {
            method: method.toUpperCase(),
            headers: headers,
            credentials: 'same-origin',
        };

        if (body !== null) {
            if (isFormData) {
                options.body = body;
            } else {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(body);
            }
        }

        try {
            const response = await fetch(url, options);
            const data = await response.json().catch(() => null);

            if (!response.ok) {
                let errorMsg = data?.error || data?.message || `Lỗi yêu cầu (${response.status})`;
                if (data?.errors && typeof data.errors === 'object') {
                    const detail = Object.values(data.errors).flat().join(' ');
                    if (detail) errorMsg = detail;
                }
                throw new Error(errorMsg);
            }
            return data;
        } catch (error) {
            console.error(`API Error on ${method} ${endpoint}:`, error);
            throw error;
        }
    }

    async function refreshCsrf() {
        try {
            const res = await apiCall('/api/csrf');
            if (res && res.csrf_token) {
                config.csrfToken = res.csrf_token;
            }
        } catch (e) {
            console.warn('Could not refresh CSRF token');
        }
    }

    // --- App Initialization ---
    async function initApp() {
        renderAppShell();
        bindGlobalEvents();

        // 1. Fetch current authenticated user
        try {
            const meRes = await apiCall('/api/me');
            if (meRes && meRes.user) {
                state.currentUser = meRes.user;
                state.currentUser.permissions = meRes.permissions || [];
            }
        } catch (e) {
            state.currentUser = null;
        }

        // 2. Fetch taxonomies (Categories and Tags)
        try {
            const [catRes, tagRes] = await Promise.all([
                apiCall('/api/categories'),
                apiCall('/api/tags'),
            ]);
            state.categories = catRes?.data || [];
            state.tags = tagRes?.data || [];
        } catch (e) {
            console.error('Failed to load taxonomies:', e);
        }

        // Check if current URL is a post detail route (e.g. /posts/123 or ?post=123)
        const pathMatches = window.location.pathname.match(/\/posts\/(\d+)/);
        const urlParams = new URLSearchParams(window.location.search);
        const urlPostId = pathMatches ? pathMatches[1] : urlParams.get('post');

        if (window.location.pathname.endsWith('/moderation') && canAccessModerationCenter()) {
            await openModerationCenter(false);
        } else if (urlPostId) {
            await openPostDetail(parseInt(urlPostId, 10), false);
        } else {
            await loadFeed(1);
        }

        updateNavUserArea();
        renderLeftRail();
        if (state.activeView !== 'moderation') {
            renderRightSidebar();
        }

        if (state.currentUser) {
            loadNotifications();
            setInterval(loadNotifications, 30000);
        }
    }

    // --- Shell Rendering ---
    function renderAppShell() {
        const app = document.getElementById('app');
        if (!app) return;

        app.innerHTML = `
            <!-- Top Navigation -->
            <header class="top-nav" role="banner">
                <div class="nav-left">
                    <button class="menu-toggle-btn" id="btn-toggle-menu" aria-label="Mở menu">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                    </button>
                    <a href="#" class="logo-link" id="logo-home-link">
                        <div class="logo-badge">
                            <img src="${config.baseUrl}/assets/img/logo-icon.svg" alt="DevAI Hub" width="30" height="30">
                        </div>
                        <span class="logo-text">DevAI<span>Hub</span></span>
                    </a>
                </div>

                <!-- Global Search -->
                <div class="nav-search" role="search">
                    <div class="search-input-wrapper">
                        <span class="search-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </span>
                        <input type="text" class="search-input" id="global-search-input" placeholder="Tìm kiếm bài viết, chuyên mục, thẻ công nghệ..." autocomplete="off">
                        <button class="search-clear-btn" id="btn-clear-search" aria-label="Xóa tìm kiếm">${Icons.x(14)}</button>
                    </div>
                </div>

                <!-- Right Navigation -->
                <div class="nav-right" id="nav-right-area">
                    <!-- Dynamic: Auth buttons or User Menu -->
                </div>
            </header>

            <!-- Main Layout Grid -->
            <div class="app-container">
                <!-- Left Navigation Rail -->
                <aside class="left-rail" id="left-rail" role="navigation" aria-label="Menu chính">
                    <!-- Dynamic: Rail content -->
                </aside>

                <!-- Center Main Feed Column -->
                <main class="main-feed-column" id="main-content" role="main">
                    <!-- Dynamic: Feed or Post Detail -->
                </main>

                <!-- Right Contextual Sidebar -->
                <aside class="right-sidebar" id="right-sidebar" role="complementary" aria-label="Thông tin cộng đồng">
                    <!-- Dynamic: Sidebar content -->
                </aside>
            </div>
        `;
    }

    // --- Top Nav User Area ---
    function updateNavUserArea() {
        const container = document.getElementById('nav-right-area');
        if (!container) return;

        const wasNotifDropdownOpen = document.getElementById('notifications-dropdown')?.classList.contains('active');
        const wasUserMenuOpen = document.getElementById('user-menu-dropdown')?.classList.contains('active');

        const isDark = state.theme === 'dark';
        const themeToggleBtn = `
            <button class="btn-icon" id="btn-theme-toggle" title="Chuyển chế độ ${isDark ? 'sáng' : 'tối'}" aria-label="Chuyển giao diện">
                ${isDark ? 
                    `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></svg>` : 
                    `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`
                }
            </button>
        `;

        if (state.currentUser) {
            const u = state.currentUser;
            const isAdmin = u.role_name === 'admin' || parseInt(u.role_id, 10) === 3;
            const isMod = u.role_name === 'moderator' || parseInt(u.role_id, 10) === 2;
            const roleBadge = isAdmin ? '<span class="role-badge role-admin">Admin</span>' : (isMod ? '<span class="role-badge role-moderator">Mod</span>' : '');

            container.innerHTML = `
                <button class="btn btn-secondary" id="btn-create-post-top">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Tạo bài</span>
                </button>

                <!-- Notifications Button -->
                <div style="position: relative;">
                    <button class="btn-icon" id="btn-notifications" title="Thông báo" aria-label="Thông báo">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        ${state.unreadNotificationsCount > 0 ? `<span style="position: absolute; top: 3px; right: 3px; width: 8px; height: 8px; border-radius: 50%; background: #EF4444;"></span>` : ''}
                    </button>
                    <div class="dropdown-menu" id="notifications-dropdown" style="width: 320px; right: 0;">
                        <!-- Dynamic notifications -->
                    </div>
                </div>

                ${themeToggleBtn}

                <!-- User Profile Pill -->
                <div style="position: relative;">
                    <div class="user-pill" id="user-profile-pill" role="button" tabindex="0">
                        <div class="user-avatar">
                            ${u.avatar_path ? `<img src="${config.baseUrl}${u.avatar_path}" alt="${escapeHtml(u.display_name)}">` : u.username.substring(0, 2).toUpperCase()}
                        </div>
                        <div class="user-info-text">
                            <span class="user-name">${escapeHtml(u.display_name)} ${roleBadge}</span>
                            <span class="user-karma"><span class="karma-star">${Icons.star(12)}</span> ${(u.post_karma || 0) + (u.comment_karma || 0)} karma</span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>

                    <!-- Dropdown -->
                    <div class="dropdown-menu" id="user-menu-dropdown">
                        <button class="dropdown-item" id="menu-item-profile">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span>Hồ sơ cá nhân</span>
                        </button>
                        <button class="dropdown-item" id="menu-item-bookmarks">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            <span>Bài viết đã lưu</span>
                        </button>
                        <button class="dropdown-item" id="menu-item-myposts">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span>Bài viết của tôi</span>
                        </button>
                        ${(isMod || isAdmin) ? `
                        <button class="dropdown-item moderation-entry" id="menu-item-moderation">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            <span>Trung tâm kiểm duyệt</span>
                        </button>
                        ` : ''}
                        ${isAdmin ? `
                        <a class="dropdown-item" id="menu-item-admin" href="${config.baseUrl}/admin" style="color: #EF4444;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Bảng quản trị Admin</span>
                        </a>
                        ` : ''}
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" id="menu-item-logout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <span>Đăng xuất</span>
                        </button>
                    </div>
                </div>
            `;

            if (wasNotifDropdownOpen) {
                document.getElementById('notifications-dropdown')?.classList.add('active');
                renderNotificationsDropdown();
            }
            if (wasUserMenuOpen) {
                document.getElementById('user-menu-dropdown')?.classList.add('active');
            }
        } else {
            container.innerHTML = `
                <div style="display: flex; gap: 4px; margin-right: 4px;">
                    <button class="demo-pill-btn" data-demo="admin">Demo: Admin</button>
                    <button class="demo-pill-btn" data-demo="mod">Demo: Mod</button>
                    <button class="demo-pill-btn" data-demo="member">Demo: Member</button>
                </div>
                ${themeToggleBtn}
                <button class="btn btn-secondary" id="btn-open-login">Đăng nhập</button>
                <button class="btn btn-primary" id="btn-open-register">Đăng ký</button>
            `;
        }
    }

    // --- Left Rail Rendering ---
    function renderLeftRail() {
        const rail = document.getElementById('left-rail');
        if (!rail) return;

        const f = state.filters;

        let categoriesHtml = state.categories.map(c => `
            <li class="rail-item ${f.category == c.id ? 'active' : ''}" data-cat-id="${c.id}">
                <span class="rail-icon">${Icons.folder(16)}</span>
                <span>${escapeHtml(c.name)}</span>
            </li>
        `).join('');

        let tagsHtml = state.tags.slice(0, 8).map(t => `
            <li class="rail-item ${f.tag == t.id ? 'active' : ''}" data-tag-id="${t.id}">
                <span class="rail-icon">#</span>
                <span>${escapeHtml(t.name)}</span>
            </li>
        `).join('');

        rail.innerHTML = `
            <div class="rail-section">
                <div class="rail-title">Khám Phá Feed</div>
                <ul class="rail-list">
                    <li class="rail-item ${f.sort === 'popular' && !f.category && !f.solved && !f.unanswered ? 'active' : ''}" data-feed-action="popular">
                        <span class="rail-icon">${Icons.flame(16)}</span>
                        <span>Phổ biến (Hot)</span>
                    </li>
                    <li class="rail-item ${f.sort === 'newest' && !f.solved && !f.unanswered ? 'active' : ''}" data-feed-action="newest">
                        <span class="rail-icon">${Icons.sparkles(16)}</span>
                        <span>Mới nhất (New)</span>
                    </li>
                    <li class="rail-item ${f.solved === '1' ? 'active' : ''}" data-feed-action="solved">
                        <span class="rail-icon">${Icons.checkCircle(16)}</span>
                        <span>Đã giải quyết</span>
                    </li>
                    <li class="rail-item ${f.unanswered === '1' ? 'active' : ''}" data-feed-action="unanswered">
                        <span class="rail-icon">${Icons.messageCircle(16)}</span>
                        <span>Chưa có câu trả lời</span>
                    </li>
                </ul>
            </div>

            <div class="rail-section">
                <div class="rail-title">Chuyên Mục</div>
                <ul class="rail-list">
                    ${categoriesHtml}
                </ul>
            </div>

            <div class="rail-section">
                <div class="rail-title">Thẻ Công Nghệ Phổ Biến</div>
                <ul class="rail-list">
                    ${tagsHtml}
                </ul>
            </div>

            ${state.currentUser ? `
            <div class="rail-section">
                <div class="rail-title">Cá Nhân</div>
                <ul class="rail-list">
                    <li class="rail-item" id="rail-my-bookmarks">
                        <span class="rail-icon">${Icons.bookmark(16)}</span>
                        <span>Bài viết đã lưu</span>
                    </li>
                    <li class="rail-item" id="rail-my-posts">
                        <span class="rail-icon">${Icons.fileText(16)}</span>
                        <span>Bài viết của tôi</span>
                    </li>
                </ul>
            </div>
            ` : ''}

            ${canAccessModerationCenter() ? `
            <div class="rail-section moderation-rail-section">
                <div class="rail-title">Kiểm duyệt</div>
                <ul class="rail-list">
                    <li class="rail-item ${state.activeView === 'moderation' ? 'active' : ''}" id="rail-moderation-center">
                        <span class="rail-icon moderation-shield">${Icons.shieldCheck(16)}</span>
                        <span>Trung tâm kiểm duyệt</span>
                    </li>
                </ul>
            </div>
            ` : ''}
        `;
    }

    // --- Right Sidebar Rendering ---
    function renderRightSidebar() {
        const sidebar = document.getElementById('right-sidebar');
        if (!sidebar) return;

        let trendingTags = state.tags.slice(0, 10).map(t => `
            <span class="tag-chip" data-tag-id="${t.id}">#${escapeHtml(t.name)}</span>
        `).join('');

        sidebar.innerHTML = `
            <!-- About Card -->
            <div class="sidebar-widget">
                <div class="widget-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Về DevAI Hub</span>
                </div>
                <div class="widget-body">
                    <p style="margin-bottom: 12px; line-height: 1.5;">
                        Diễn đàn thảo luận lập trình chuyên sâu về Trí tuệ nhân tạo, Machine Learning, Web & DevOps trên nền tảng PHP MVC thuần.
                    </p>
                    <div class="community-stats-grid">
                        <div class="stat-box">
                            <span class="stat-value">9+</span>
                            <span class="stat-label">Thành viên demo</span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-value"><span class="online-dot"></span>Trực tuyến</span>
                            <span class="stat-label">MariaDB & Apache</span>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="btn-sidebar-create-post" style="width: 100%;">
                        Tạo bài viết mới
                    </button>
                </div>
            </div>

            <!-- Community Rules Card -->
            <div class="sidebar-widget">
                <div class="widget-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>Quy Tắc Cộng Đồng</span>
                </div>
                <div class="widget-body">
                    <ol class="rules-list">
                        <li>
                            <strong>Tôn trọng lẫn nhau:</strong>
                            <p>Không công kích cá nhân, ngôn từ thù địch hay phân biệt đối xử.</p>
                        </li>
                        <li>
                            <strong>Nội dung chất lượng & an toàn:</strong>
                            <p>Không đăng tải spam, mã độc hoặc nội dung không liên quan đến công nghệ.</p>
                        </li>
                        <li>
                            <strong>Gắn thẻ & chuyên mục chuẩn:</strong>
                            <p>Chọn đúng chuyên mục và tối đa 5 tags để cộng đồng dễ tra cứu.</p>
                        </li>
                        <li>
                            <strong>Bảo vệ bản quyền:</strong>
                            <p>Ghi rõ nguồn tài liệu, không đạo văn hoặc vi phạm quyền tác giả.</p>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- Trending Tags -->
            <div class="sidebar-widget">
                <div class="widget-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <span>Thẻ Xu Hướng</span>
                </div>
                <div class="widget-body">
                    <div class="trending-tags-cloud">
                        ${trendingTags}
                    </div>
                </div>
            </div>

            <!-- Footer Meta -->
            <div style="padding: 10px; font-size: 11px; color: var(--text-muted); line-height: 1.6;">
                DevAI Hub © 2026. Kiến trúc chuẩn PHP 8.1+ MVC & XAMPP MariaDB.<br>
                Giao diện thiết kế theo Reddit UI Design System.
            </div>
        `;
    }

    // --- Feed View & Rendering ---
    async function loadFeed(page = 1, append = false) {
        state.feedLoading = true;
        const main = document.getElementById('main-content');
        if (!main) return;

        if (!append) {
            state.activeView = 'feed';
            renderLeftRail();
            renderRightSidebar();
            renderFeedStructure();
        }

        const feedListEl = document.getElementById('feed-posts-container');
        if (!feedListEl) return;

        if (!append) {
            feedListEl.innerHTML = `
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
            `;
        }

        try {
            const queryParams = new URLSearchParams({
                page: page,
                limit: 10,
                sort: state.filters.sort || 'popular',
            });

            if (state.filters.type) queryParams.set('type', state.filters.type);
            if (state.filters.category) queryParams.set('category', state.filters.category);
            if (state.filters.tag) queryParams.set('tag', state.filters.tag);
            if (state.filters.q) queryParams.set('q', state.filters.q);
            if (state.filters.solved) queryParams.set('solved', state.filters.solved);
            if (state.filters.unanswered) queryParams.set('unanswered', state.filters.unanswered);

            const res = await apiCall(`/api/feed?${queryParams.toString()}`);
            const posts = res?.data || [];
            state.feedHasMore = !!res?.has_more;
            state.feedPage = page;

            if (append) {
                state.feed = state.feed.concat(posts);
            } else {
                state.feed = posts;
            }

            renderFeedCards(state.feed);
        } catch (error) {
            console.error('Failed to load feed:', error);
            feedListEl.innerHTML = `
                <div class="empty-state-box">
                    <div class="empty-icon">${Icons.alertTriangle(32)}</div>
                    <div class="empty-title">Không thể tải bài viết</div>
                    <div class="empty-desc">${escapeHtml(error.message)}</div>
                    <button class="btn btn-secondary" onclick="window.devai.reloadFeed()">Thử lại</button>
                </div>
            `;
        } finally {
            state.feedLoading = false;
        }
    }

    function renderFeedStructure() {
        const main = document.getElementById('main-content');
        if (!main) return;

        const f = state.filters;
        let activeFilterBannerHtml = '';
        if (f.category || f.tag || f.q || f.type) {
            let filterDesc = [];
            if (f.type) {
                const typeNames = { discussion: 'Thảo luận', question: 'Câu hỏi', resource: 'Tài nguyên', job: 'Việc làm' };
                filterDesc.push(`Định dạng: <strong>${escapeHtml(typeNames[f.type] || f.type)}</strong>`);
            }
            if (f.category) {
                const c = state.categories.find(x => x.id == f.category);
                filterDesc.push(`Chuyên mục: <strong>${escapeHtml(c ? c.name : f.category)}</strong>`);
            }
            if (f.tag) {
                const t = state.tags.find(x => x.id == f.tag);
                filterDesc.push(`Thẻ: <strong>#${escapeHtml(t ? t.name : f.tag)}</strong>`);
            }
            if (f.q) {
                filterDesc.push(`Tìm kiếm: <strong>"${escapeHtml(f.q)}"</strong>`);
            }
            activeFilterBannerHtml = `
                <div class="active-filter-banner">
                    <div>Lọc theo: ${filterDesc.join(' • ')}</div>
                    <button class="clear-filter-btn" id="btn-clear-active-filter">Xóa bộ lọc ${Icons.x(12)}</button>
                </div>
            `;
        }

        main.innerHTML = `
            <!-- Quick Create Post Bar -->
            <div class="quick-create-card">
                <div class="user-avatar" style="width: 36px; height: 36px;">
                    ${state.currentUser ? (state.currentUser.avatar_path ? `<img src="${config.baseUrl}${state.currentUser.avatar_path}">` : state.currentUser.username.substring(0, 2).toUpperCase()) : 'AI'}
                </div>
                <div class="quick-create-input" id="quick-create-trigger">
                    Tạo bài viết thảo luận, đặt câu hỏi hay chia sẻ tài nguyên...
                </div>
                <div class="quick-create-actions">
                    <button class="btn-icon" title="Tải ảnh" id="quick-photo-trigger">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </button>
                    <button class="btn-icon" title="Đặt câu hỏi" id="quick-question-trigger">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </button>
                </div>
            </div>

            <!-- Feed Toolbar & Filters -->
            <div class="feed-toolbar">
                <div class="sort-chips-group">
                    <button class="sort-chip ${f.sort === 'popular' && !f.solved && !f.unanswered ? 'active' : ''}" data-sort="popular">
                        ${Icons.flame(15)} <span>Phổ biến</span>
                    </button>
                    <button class="sort-chip ${f.sort === 'newest' && !f.solved && !f.unanswered ? 'active' : ''}" data-sort="newest">
                        ${Icons.sparkles(15)} <span>Mới nhất</span>
                    </button>
                    <button class="sort-chip ${f.solved === '1' ? 'active' : ''}" data-sort="solved">
                        ${Icons.checkCircle(15)} <span>Đã giải quyết</span>
                    </button>
                    <button class="sort-chip ${f.unanswered === '1' ? 'active' : ''}" data-sort="unanswered">
                        ${Icons.messageCircle(15)} <span>Chưa trả lời</span>
                    </button>
                </div>
            </div>

            ${activeFilterBannerHtml}

            <!-- Feed Posts List -->
            <div id="feed-posts-container" style="display: flex; flex-direction: column; gap: 12px;"></div>

            <!-- Pagination Container -->
            <div id="pagination-container" style="text-align: center; margin: 16px 0;"></div>
        `;
    }

    function renderFeedCards(posts) {
        const container = document.getElementById('feed-posts-container');
        if (!container) return;

        if (!posts || posts.length === 0) {
            container.innerHTML = `
                <div class="empty-state-box">
                    <div class="empty-icon">${Icons.folderOpen(32)}</div>
                    <div class="empty-title">Không tìm thấy bài viết nào</div>
                    <div class="empty-desc">Chưa có bài viết phù hợp với tiêu chí hiện tại. Bạn có thể là người đầu tiên tạo bài viết mới!</div>
                    <button class="btn btn-primary" id="empty-create-btn">Tạo bài viết ngay</button>
                </div>
            `;
            return;
        }

        container.innerHTML = posts.map(post => {
            const hasLiked = !!post.viewer_liked;
            const hasDisliked = !!post.viewer_disliked;
            const hasBookmarked = !!post.viewer_bookmarked;
            const hasPinned = !!post.viewer_pinned;
            const roleBadge = post.role_name === 'admin' ? '<span class="role-badge role-admin">Admin</span>' : (post.role_name === 'moderator' ? '<span class="role-badge role-moderator">Mod</span>' : '');
            const isSolved = post.best_answer_comment_id !== null && post.best_answer_comment_id !== undefined;

            let tagsHtml = '';
            if (post.tags && Array.isArray(post.tags)) {
                tagsHtml = post.tags.map(t => `<span class="tag-chip" data-tag-id="${t.id}">#${escapeHtml(t.name)}</span>`).join('');
            }

            return `
                <article class="post-card" data-post-id="${post.id}">
                    <!-- Left Vote Rail -->
                    <div class="vote-rail">
                        <button class="vote-btn upvote ${hasLiked ? 'active' : ''}" data-action="like" data-post-id="${post.id}" aria-label="Thích bài viết" aria-pressed="${hasLiked}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-8 8h5v8h6v-8h5z"/></svg>
                        </button>
                        <span class="vote-score ${hasLiked ? 'upvoted' : ''} ${hasDisliked ? 'downvoted' : ''}" id="score-${post.id}">${(post.like_count || 0) - (post.dislike_count || 0)}</span>
                        <button class="vote-btn downvote ${hasDisliked ? 'active' : ''}" data-action="downvote" data-post-id="${post.id}" aria-label="Không thích bài viết" aria-pressed="${hasDisliked}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20l8-8h-5V4H9v8H4z"/></svg>
                        </button>
                    </div>

                    <!-- Card Content Area -->
                    <div class="card-content-area">
                        <!-- Meta row -->
                        <div class="card-meta-row">
                            <span class="category-badge" data-cat-id="${post.category_id}">
                                ${Icons.folder(13)} d/${escapeHtml(post.category_name || 'Chung')}
                            </span>
                            <span>•</span>
                            <span>Đăng bởi</span>
                            <span class="author-link" data-username="${escapeHtml(post.username)}">u/${escapeHtml(post.username)}</span>
                            ${roleBadge}
                            <span>•</span>
                            <time datetime="${post.created_at}">${timeAgo(post.created_at)}</time>

                            ${post.is_pinned ? `<span class="badge-pinned">${Icons.pin(12)} Ghim bởi BQT</span>` : ''}
                            ${hasPinned ? `<span class="badge-pinned badge-pinned-self">${Icons.pin(12)} Bạn đã ghim</span>` : ''}
                            ${isSolved ? `<span class="badge-solved">${Icons.checkCircle(12)} Đã giải quyết</span>` : ''}
                        </div>

                        <!-- Post Title -->
                        <h2 class="card-title" data-action="open-detail" data-post-id="${post.id}">
                            ${escapeHtml(post.title)}
                        </h2>

                        <!-- Post Excerpt -->
                        <div class="card-excerpt" data-action="open-detail" data-post-id="${post.id}">
                            ${post.content_html || ''}
                        </div>

                        <!-- Tags Row -->
                        ${tagsHtml ? `<div class="card-tags-row">${tagsHtml}</div>` : ''}

                        <!-- Action Row -->
                        <div class="card-actions-row">
                            <button class="action-btn" data-action="open-detail" data-post-id="${post.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span>${post.comment_count || 0} Bình luận</span>
                            </button>

                            <button class="action-btn ${hasBookmarked ? 'active' : ''}" data-action="bookmark" data-post-id="${post.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="${hasBookmarked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                                <span>${hasBookmarked ? 'Đã lưu' : 'Lưu'}</span>
                            </button>

                            <button class="action-btn ${hasPinned ? 'active' : ''}" data-action="toggle-pin" data-post-id="${post.id}">
                                ${Icons.pin(15)}
                                <span>${hasPinned ? 'Bỏ ghim' : 'Ghim'}</span>
                            </button>

                            <button class="action-btn" data-action="share" data-post-id="${post.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                <span>Chia sẻ</span>
                            </button>

                            <button class="action-btn" data-action="report" data-post-id="${post.id}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                <span>Báo cáo</span>
                            </button>

                            ${canAccessModerationCenter() ? `
                            <button class="action-btn" data-action="mod-menu" data-post-id="${post.id}" title="Công cụ kiểm duyệt">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Kiểm duyệt</span>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                </article>
            `;
        }).join('');

        // Render load more button
        const paginationContainer = document.getElementById('pagination-container');
        if (paginationContainer) {
            if (state.feedHasMore) {
                paginationContainer.innerHTML = `
                    <button class="btn btn-secondary" id="btn-load-more" style="min-width: 200px;">
                        Tải thêm bài viết...
                    </button>
                `;
            } else {
                paginationContainer.innerHTML = `
                    <div style="font-size: 13px; color: var(--text-muted); padding: 12px;">
                        Đã tải hết danh sách bài viết.
                    </div>
                `;
            }
        }
    }

    function isAdminAccount() {
        if (!state.currentUser) return false;
        const u = state.currentUser;
        return u.role_name === 'admin' || parseInt(u.role_id, 10) === 3;
    }

    function isModeratorAccount() {
        if (!state.currentUser) return false;
        const u = state.currentUser;
        return u.role_name === 'moderator' || parseInt(u.role_id, 10) === 2;
    }

    function canAccessModerationCenter() {
        if (!state.currentUser) return false;
        const permissions = state.currentUser.permissions || [];
        return isAdminAccount() || isModeratorAccount()
            || permissions.includes('post.moderate')
            || permissions.includes('report.review');
    }

    // --- Post Detail & Threaded Comments (Reddit Thread View) ---
    async function openPostDetail(postId, updateHistory = true) {
        state.activeView = 'post-detail';
        state.currentPostId = postId;

        if (updateHistory) {
            window.history.pushState({ postId }, '', `${config.baseUrl}/posts/${postId}`);
        }

        const main = document.getElementById('main-content');
        if (!main) return;

        main.innerHTML = `
            <div class="post-detail-view">
                <div class="detail-header-bar">
                    <button class="back-to-feed-btn" id="btn-back-to-feed">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        <span>Quay lại danh sách</span>
                    </button>
                </div>
                <div style="padding: 24px;">
                    <div class="skeleton skeleton-card"></div>
                    <div class="skeleton skeleton-card" style="height: 240px;"></div>
                </div>
            </div>
        `;

        try {
            const res = await apiCall(`/api/posts/${postId}`);
            const post = res?.data;
            if (!post) throw new Error('Không tìm thấy bài viết.');
            state.currentPostDetail = post;

            renderPostDetail(post);
        } catch (error) {
            main.innerHTML = `
                <div class="post-detail-view" style="padding: 32px; text-align: center;">
                    <div style="margin-bottom: 12px; display: flex; justify-content: center; color: var(--color-danger);">${Icons.alertTriangle(36)}</div>
                    <h3>${escapeHtml(error.message)}</h3>
                    <button class="btn btn-secondary" style="margin-top: 16px;" id="btn-back-to-feed">Quay về trang chủ</button>
                </div>
            `;
            document.getElementById('btn-back-to-feed')?.addEventListener('click', () => {
                window.history.pushState({}, '', `${config.baseUrl}/`);
                state.activeView = 'feed';
                loadFeed(1);
            });
        }
    }

    function renderPostDetail(post) {
        const main = document.getElementById('main-content');
        if (!main) return;

        const hasLiked = !!post.viewer_liked;
        const hasDisliked = !!post.viewer_disliked;
        const hasBookmarked = !!post.viewer_bookmarked;
        const hasPinned = !!post.viewer_pinned;
        const roleBadge = post.role_name === 'admin' ? '<span class="role-badge role-admin">Admin</span>' : (post.role_name === 'moderator' ? '<span class="role-badge role-moderator">Mod</span>' : '');
        const isSolved = post.best_answer_comment_id !== null && post.best_answer_comment_id !== undefined;
        const isPostOwner = !!(state.currentUser && state.currentUser.id === post.author_id);

        let tagsHtml = '';
        if (post.tags && Array.isArray(post.tags)) {
            tagsHtml = post.tags.map(t => `<span class="tag-chip" data-tag-id="${t.id}">#${escapeHtml(t.name)}</span>`).join('');
        }

        // Check Best Answer
        let bestAnswerHtml = '';
        let bestAnswerComment = null;
        if (isSolved && post.comments) {
            bestAnswerComment = findCommentById(post.comments, post.best_answer_comment_id);
            if (bestAnswerComment) {
                bestAnswerHtml = `
                    <div class="best-answer-banner">
                        <div class="best-answer-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <span>Câu trả lời hay nhất (Được chọn bởi tác giả)</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 6px;">
                            Bởi u/${escapeHtml(bestAnswerComment.username)} • ${timeAgo(bestAnswerComment.created_at)}
                        </div>
                        <div class="comment-body">${bestAnswerComment.content_html}</div>
                    </div>
                `;
            }
        }

        main.innerHTML = `
            <div class="post-detail-view">
                <!-- Top Navigation in Detail -->
                <div class="detail-header-bar">
                    <button class="back-to-feed-btn" id="btn-back-to-feed">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        <span>Quay lại feed</span>
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button class="action-btn" data-action="share" data-post-id="${post.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            <span>Chia sẻ</span>
                        </button>
                        ${isPostOwner ? `
                        <div style="position: relative;">
                            <button class="btn-icon" id="btn-post-options" data-action="toggle-post-menu" aria-label="Tùy chọn bài viết" title="Tùy chọn bài viết">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                            </button>
                            <div class="dropdown-menu" id="post-owner-menu" style="right: 0;">
                                <button class="dropdown-item" data-action="edit-post" data-post-id="${post.id}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                                    <span>Sửa bài viết</span>
                                </button>
                                <button class="dropdown-item" id="btn-delete-post" data-action="delete-post" data-post-id="${post.id}" style="color: var(--color-danger);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    <span>Xóa bài viết</span>
                                </button>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Post Body Container -->
                <div style="padding: 20px 24px;">
                    <!-- Meta row -->
                    <div class="card-meta-row" style="margin-bottom: 12px;">
                        <span class="category-badge" data-cat-id="${post.category_id}">
                            ${Icons.folder(13)} d/${escapeHtml(post.category_name || 'Chung')}
                        </span>
                        <span>•</span>
                        <span>Đăng bởi</span>
                        <span class="author-link" data-username="${escapeHtml(post.username)}">u/${escapeHtml(post.username)}</span>
                        ${roleBadge}
                        <span>•</span>
                        <time datetime="${post.created_at}">${timeAgo(post.created_at)}</time>

                        ${post.is_pinned ? `<span class="badge-pinned">${Icons.pin(12)} Ghim bởi BQT</span>` : ''}
                        ${hasPinned ? `<span class="badge-pinned badge-pinned-self">${Icons.pin(12)} Bạn đã ghim</span>` : ''}
                        ${isSolved ? `<span class="badge-solved">${Icons.checkCircle(12)} Đã giải quyết</span>` : ''}
                    </div>

                    <!-- Post Title -->
                    <h1 style="font-size: 22px; font-weight: 700; line-height: 1.35; margin-bottom: 14px; color: var(--text-primary);">
                        ${escapeHtml(post.title)}
                    </h1>

                    <!-- Post Full Content -->
                    <div class="post-full-body">
                        ${post.content_html || ''}
                    </div>

                    <!-- Attached Images -->
                    ${(post.images && post.images.length > 0) ? `
                        <div class="post-images-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; margin: 16px 0;">
                            ${post.images.map(img => `
                                <button type="button" class="post-image-thumb" data-lightbox-src="${config.baseUrl}${img.file_path}" data-lightbox-alt="${escapeHtml(img.original_name || '')}" style="padding: 0; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; cursor: zoom-in;">
                                    <img src="${config.baseUrl}${img.file_path}" alt="${escapeHtml(img.original_name || '')}" style="display: block; width: 100%; height: 180px; object-fit: cover;">
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}

                    <!-- Tags -->
                    ${tagsHtml ? `<div class="card-tags-row" style="margin: 16px 0;">${tagsHtml}</div>` : ''}

                    <!-- Best Answer Banner if exists -->
                    ${bestAnswerHtml}

                    <!-- Post Actions Bar -->
                    <div class="card-actions-row" style="padding-top: 12px; margin-top: 16px;">
                        <button class="action-btn ${hasLiked ? 'active' : ''}" data-action="like" data-post-id="${post.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-8 8h5v8h6v-8h5z"/></svg>
                            <span id="score-text-${post.id}">${(post.like_count || 0) - (post.dislike_count || 0)} Thích</span>
                        </button>

                        <button class="action-btn downvote-btn ${hasDisliked ? 'active' : ''}" data-action="downvote" data-post-id="${post.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20l8-8h-5V4H9v8H4z"/></svg>
                            <span>Không thích</span>
                        </button>

                        <button class="action-btn ${hasBookmarked ? 'active' : ''}" data-action="bookmark" data-post-id="${post.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="${hasBookmarked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                            <span>${hasBookmarked ? 'Đã lưu' : 'Lưu bài'}</span>
                        </button>

                        <button class="action-btn ${hasPinned ? 'active' : ''}" data-action="toggle-pin" data-post-id="${post.id}">
                            ${Icons.pin(15)}
                            <span>${hasPinned ? 'Bỏ ghim' : 'Ghim bài'}</span>
                        </button>

                        <button class="action-btn" data-action="report" data-post-id="${post.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                            <span>Báo cáo</span>
                        </button>

                        ${canAccessModerationCenter() ? `
                        <button class="action-btn" data-action="mod-menu" data-post-id="${post.id}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Kiểm duyệt bài viết</span>
                        </button>
                        ` : ''}
                    </div>
                </div>

                <!-- Comments Section -->
                <section class="comments-section" aria-label="Bình luận bài viết">
                    <div class="comments-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>${post.comments ? countAllComments(post.comments) : 0} Bình Luận</span>
                    </div>

                    <!-- Comment Composer -->
                    <div class="comment-composer-box">
                        ${state.currentUser ? `
                            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 6px;">
                                Bình luận với tư cách <strong style="color: var(--accent);">u/${escapeHtml(state.currentUser.username)}</strong>
                            </div>
                            <textarea class="comment-textarea" id="main-comment-input" placeholder="Bạn nghĩ gì về chủ đề này? Hãy chia sẻ ý kiến..." rows="3"></textarea>
                            <div class="composer-toolbar">
                                <button class="btn btn-primary" id="btn-submit-main-comment">Gửi bình luận</button>
                            </div>
                        ` : `
                            <div style="background: var(--surface-subtle); border: 1px dashed var(--border); border-radius: var(--radius-md); padding: 14px; text-align: center; font-size: 13.5px;">
                                Đăng nhập để thảo luận và để lại bình luận cho bài viết này.
                                <button class="btn btn-primary" style="margin-left: 10px;" id="btn-login-to-comment">Đăng nhập</button>
                            </div>
                        `}
                    </div>

                    <!-- Threaded Comments Tree -->
                    <div class="comment-thread" id="comments-tree-container">
                        ${renderCommentsTree(post.comments || [], post)}
                    </div>
                </section>
            </div>
        `;

        // Bind back button
        document.getElementById('btn-back-to-feed')?.addEventListener('click', () => {
            window.history.pushState({}, '', `${config.baseUrl}/`);
            state.activeView = 'feed';
            loadFeed(1);
        });

        // Bind main comment submit
        document.getElementById('btn-submit-main-comment')?.addEventListener('click', async () => {
            const input = document.getElementById('main-comment-input');
            const content = input?.value?.trim();
            if (!content) {
                showToast('Vui lòng nhập nội dung bình luận.', 'error');
                return;
            }
            try {
                await apiCall(`/api/posts/${post.id}/comments`, 'POST', {
                    content_html: `<p>${escapeHtml(content)}</p>`,
                });
                showToast('Đã gửi bình luận!', 'success');
                input.value = '';
                // Reload post detail
                openPostDetail(post.id, false);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        document.getElementById('btn-login-to-comment')?.addEventListener('click', () => {
            openAuthModal('login');
        });
    }

    function countAllComments(comments) {
        let count = 0;
        function traverse(items) {
            for (const item of items) {
                count++;
                if (item.children && Array.isArray(item.children)) {
                    traverse(item.children);
                }
            }
        }
        traverse(comments);
        return count;
    }

    function findCommentById(comments, id) {
        for (const c of comments) {
            if (c.id == id) return c;
            if (c.children && Array.isArray(c.children)) {
                const found = findCommentById(c.children, id);
                if (found) return found;
            }
        }
        return null;
    }

    function renderCommentsTree(comments, post) {
        if (!comments || comments.length === 0) {
            return `
                <div style="text-align: center; color: var(--text-muted); padding: 32px 0; font-size: 13.5px;">
                    Chưa có bình luận nào. Hãy là người đầu tiên bình luận!
                </div>
            `;
        }

        return comments.map(comment => renderCommentItem(comment, post)).join('');
    }

    function renderCommentItem(comment, post) {
        const isAuthor = state.currentUser && state.currentUser.id === comment.author_id;
        const isPostAuthor = state.currentUser && state.currentUser.id === post.author_id;
        const isQuestion = post.post_type === 'question';
        const isBestAnswer = post.best_answer_comment_id == comment.id;
        const roleBadge = comment.role_name === 'admin' ? '<span class="role-badge role-admin">Admin</span>' : (comment.role_name === 'moderator' ? '<span class="role-badge role-moderator">Mod</span>' : '');
        const commentHasLiked = !!comment.viewer_liked;
        const commentHasDisliked = !!comment.viewer_disliked;
        const commentNetScore = (comment.like_count || 0) - (comment.dislike_count || 0);

        let repliesHtml = '';
        if (comment.children && Array.isArray(comment.children) && comment.children.length > 0) {
            repliesHtml = `
                <div class="comment-replies-container">
                    ${comment.children.map(r => renderCommentItem(r, post)).join('')}
                </div>
            `;
        }

        return `
            <div class="comment-item" id="comment-node-${comment.id}" data-comment-id="${comment.id}">
                <!-- Thread vertical rail line -->
                <div class="comment-rail-line" data-action="collapse-comment" data-comment-id="${comment.id}" title="Thu gọn chuỗi"></div>

                <div class="comment-inner-content" id="comment-content-${comment.id}">
                    <div class="comment-header">
                        <span class="comment-collapse-btn" data-action="collapse-comment" data-comment-id="${comment.id}" aria-expanded="true">[-]</span>
                        <span class="comment-author" data-username="${escapeHtml(comment.username)}">u/${escapeHtml(comment.username)}</span>
                        ${roleBadge}
                        <span>•</span>
                        <time>${timeAgo(comment.created_at)}</time>
                        ${isBestAnswer ? `<span class="badge-solved">${Icons.star(12)} Câu trả lời hay nhất</span>` : ''}
                    </div>

                    <div class="comment-collapsible" id="comment-collapsible-${comment.id}">
                        <div class="comment-body">
                            ${comment.content_html || ''}
                        </div>

                        <div class="comment-actions-bar">
                            <span class="comment-action comment-vote-btn ${commentHasLiked ? 'active-up' : ''}" data-action="comment-like" data-comment-id="${comment.id}" aria-pressed="${commentHasLiked}">
                                ${Icons.arrowUp ? Icons.arrowUp(13) : '▲'}
                            </span>
                            <span class="comment-vote-score" id="comment-score-${comment.id}">${commentNetScore}</span>
                            <span class="comment-action comment-vote-btn ${commentHasDisliked ? 'active-down' : ''}" data-action="comment-dislike" data-comment-id="${comment.id}" aria-pressed="${commentHasDisliked}">
                                ${Icons.arrowDown ? Icons.arrowDown(13) : '▼'}
                            </span>

                            ${state.currentUser ? `
                                <span class="comment-action" data-action="reply-comment" data-comment-id="${comment.id}">
                                    ${Icons.messageCircle(13)} Trả lời
                                </span>
                            ` : ''}

                            ${isPostAuthor && isQuestion && !isBestAnswer ? `
                                <span class="comment-action" data-action="set-best-answer" data-comment-id="${comment.id}" style="color: #10B981; font-weight: 700;">
                                    ${Icons.star(13)} Chọn câu trả lời hay nhất
                                </span>
                            ` : ''}

                            ${isAuthor ? `
                                <span class="comment-action" data-action="delete-comment" data-comment-id="${comment.id}" style="color: var(--color-danger);">
                                    Xóa
                                </span>
                            ` : ''}

                            <span class="comment-action" data-action="report-comment" data-comment-id="${comment.id}">
                                Báo cáo
                            </span>
                        </div>

                        <!-- Placeholder for inline reply form -->
                        <div id="reply-form-slot-${comment.id}"></div>

                        <!-- Nested Replies -->
                        ${repliesHtml}
                    </div>
                </div>
            </div>
        `;
    }

    // --- User Profile Page & Hover Card ---
    function formatJoinDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString.replace(' ', 'T'));
        return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    async function openUserProfilePage(username, updateHistory = true) {
        if (!username) return;
        closeUserHoverCard();
        state.activeView = 'profile-page';
        state.profileTab = 'overview';
        state.profileTabItems = [];
        state.profileTabPage = 1;
        state.profileTabHasMore = false;

        if (updateHistory) {
            window.history.pushState({ username }, '', `${config.baseUrl}/u/${encodeURIComponent(username)}`);
        }

        const main = document.getElementById('main-content');
        if (!main) return;
        main.innerHTML = `
            <div class="post-detail-view">
                <div class="detail-header-bar">
                    <button class="back-to-feed-btn" id="btn-back-to-feed">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        <span>Quay lại</span>
                    </button>
                </div>
                <div style="padding: 24px;">
                    <div class="skeleton skeleton-card"></div>
                    <div class="skeleton skeleton-card" style="height: 160px;"></div>
                </div>
            </div>
        `;
        document.getElementById('btn-back-to-feed')?.addEventListener('click', () => {
            window.history.pushState({}, '', `${config.baseUrl}/`);
            state.activeView = 'feed';
            loadFeed(1);
        });

        try {
            const res = await apiCall(`/api/users/${encodeURIComponent(username)}`);
            const profile = res?.data;
            if (!profile) throw new Error('Không tìm thấy người dùng.');
            state.viewedProfile = profile;
            renderUserProfilePage();
            loadProfileTab('overview');
        } catch (err) {
            main.innerHTML = `
                <div class="post-detail-view">
                    <div class="detail-header-bar">
                        <button class="back-to-feed-btn" id="btn-back-to-feed">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            <span>Quay lại</span>
                        </button>
                    </div>
                    <div class="empty-state-box">
                        <div class="empty-icon">${Icons.alertTriangle(32)}</div>
                        <div class="empty-title">Không thể tải hồ sơ</div>
                        <div class="empty-desc">${escapeHtml(err.message)}</div>
                    </div>
                </div>
            `;
            document.getElementById('btn-back-to-feed')?.addEventListener('click', () => {
                window.history.pushState({}, '', `${config.baseUrl}/`);
                state.activeView = 'feed';
                loadFeed(1);
            });
        }
    }

    function renderUserProfilePage() {
        const main = document.getElementById('main-content');
        const p = state.viewedProfile;
        if (!main || !p) return;

        const isOwnProfile = !!(state.currentUser && state.currentUser.id === p.id);
        const roleBadge = p.role_name === 'admin' ? '<span class="role-badge role-admin">Admin</span>' : (p.role_name === 'moderator' ? '<span class="role-badge role-moderator">Mod</span>' : '');
        const initial = escapeHtml((p.display_name || p.username || '?').charAt(0).toUpperCase());
        const avatarHtml = p.avatar_path
            ? `<img src="${config.baseUrl}${escapeHtml(p.avatar_path)}" alt="${escapeHtml(p.username)}" class="profile-page-avatar-img">`
            : `<div class="profile-page-avatar-fallback">${initial}</div>`;

        const tabs = [
            { key: 'overview', label: 'Tổng quan' },
            { key: 'posts', label: 'Bài viết' },
            { key: 'comments', label: 'Bình luận' },
        ];
        if (isOwnProfile) {
            tabs.push({ key: 'saved', label: 'Đã lưu' });
            tabs.push({ key: 'pinned', label: 'Đã ghim' });
            tabs.push({ key: 'liked', label: 'Đã thích' });
            tabs.push({ key: 'disliked', label: 'Không thích' });
        }

        main.innerHTML = `
            <div class="post-detail-view profile-page-view">
                <div class="detail-header-bar">
                    <button class="back-to-feed-btn" id="btn-back-to-feed">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        <span>Quay lại</span>
                    </button>
                </div>

                <div class="profile-page-body">
                    <div class="profile-page-header">
                        ${avatarHtml}
                        <div class="profile-page-heading">
                            <h1>${escapeHtml(p.display_name || p.username)} ${roleBadge}</h1>
                            <div class="profile-page-username">u/${escapeHtml(p.username)}</div>
                        </div>
                        ${isOwnProfile ? `<button class="btn btn-secondary" id="btn-edit-profile-page">Chỉnh sửa hồ sơ</button>` : ''}
                    </div>

                    ${p.bio ? `<div class="profile-page-bio">${escapeHtml(p.bio)}</div>` : ''}

                    <div class="profile-page-stats-row">
                        <div class="profile-page-stat"><strong>${p.post_karma ?? 0}</strong><span>Post karma</span></div>
                        <div class="profile-page-stat"><strong>${p.comment_karma ?? 0}</strong><span>Comment karma</span></div>
                        <div class="profile-page-stat"><strong>${(p.post_karma || 0) + (p.comment_karma || 0)}</strong><span>Uy tín</span></div>
                        <div class="profile-page-stat"><strong>${p.post_count ?? 0}</strong><span>Bài viết</span></div>
                        <div class="profile-page-stat"><strong>${p.comment_count ?? 0}</strong><span>Bình luận</span></div>
                        <div class="profile-page-stat profile-page-joined">${Icons.clock ? Icons.clock(13) : ''} Tham gia ${formatJoinDate(p.created_at)}</div>
                    </div>

                    <div class="profile-page-tabs">
                        ${tabs.map(t => `<button class="profile-tab-btn ${state.profileTab === t.key ? 'active' : ''}" data-profile-tab="${t.key}">${t.label}</button>`).join('')}
                    </div>

                    <div id="profile-tab-content" class="profile-tab-content"></div>
                </div>
            </div>
        `;

        document.getElementById('btn-back-to-feed')?.addEventListener('click', () => {
            window.history.pushState({}, '', `${config.baseUrl}/`);
            state.activeView = 'feed';
            loadFeed(1);
        });
        document.getElementById('btn-edit-profile-page')?.addEventListener('click', () => {
            openProfileModal();
        });
    }

    async function loadProfileTab(tab, page = 1, append = false) {
        state.profileTab = tab;
        state.profileTabPage = page;
        if (!append) state.profileTabItems = [];
        state.profileTabLoading = true;

        document.querySelectorAll('.profile-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-profile-tab') === tab);
        });

        const container = document.getElementById('profile-tab-content');
        if (!container) return;
        if (!append) {
            container.innerHTML = `<div class="skeleton skeleton-card"></div><div class="skeleton skeleton-card"></div>`;
        }

        const username = state.viewedProfile?.username;
        if (!username) return;

        try {
            let items = [];
            let hasMore = false;
            const encoded = encodeURIComponent(username);

            if (tab === 'overview') {
                const [postsRes, commentsRes] = await Promise.all([
                    apiCall(`/api/users/${encoded}/posts?page=1&limit=8`),
                    apiCall(`/api/users/${encoded}/comments?page=1&limit=8`),
                ]);
                const posts = (postsRes?.data || []).map(item => ({ type: 'post', ...item }));
                const comments = (commentsRes?.data || []).map(item => ({ type: 'comment', ...item }));
                items = posts.concat(comments)
                    .sort((a, b) => new Date(b.created_at.replace(' ', 'T')) - new Date(a.created_at.replace(' ', 'T')))
                    .slice(0, 12);
            } else if (tab === 'posts') {
                const res = await apiCall(`/api/users/${encoded}/posts?page=${page}&limit=10`);
                items = (res?.data || []).map(item => ({ type: 'post', ...item }));
                hasMore = items.length === 10;
            } else if (tab === 'comments') {
                const res = await apiCall(`/api/users/${encoded}/comments?page=${page}&limit=10`);
                items = (res?.data || []).map(item => ({ type: 'comment', ...item }));
                hasMore = items.length === 10;
            } else if (tab === 'saved') {
                const res = await apiCall(`/api/profile/bookmarks?page=${page}`);
                items = (res?.data || []).map(item => ({ type: 'post', ...item }));
                hasMore = items.length === 20;
            } else if (tab === 'pinned') {
                const res = await apiCall(`/api/profile/pins?page=${page}`);
                items = (res?.data || []).map(item => ({ type: 'post', ...item }));
                hasMore = items.length === 20;
            } else if (tab === 'liked') {
                const res = await apiCall(`/api/profile/liked?page=${page}`);
                items = (res?.data || []).map(item => ({ type: 'post', ...item }));
                hasMore = items.length === 20;
            } else if (tab === 'disliked') {
                const res = await apiCall(`/api/profile/disliked?page=${page}`);
                items = (res?.data || []).map(item => ({ type: 'post', ...item }));
                hasMore = items.length === 20;
            }

            state.profileTabItems = append ? state.profileTabItems.concat(items) : items;
            state.profileTabHasMore = hasMore;
            renderProfileTabContent();
        } catch (err) {
            container.innerHTML = `<div class="empty-state-box"><div class="empty-title">Không thể tải dữ liệu</div><div class="empty-desc">${escapeHtml(err.message)}</div></div>`;
        } finally {
            state.profileTabLoading = false;
        }
    }

    function renderProfileTabContent() {
        const container = document.getElementById('profile-tab-content');
        if (!container) return;
        const items = state.profileTabItems;

        if (!items || items.length === 0) {
            container.innerHTML = `
                <div class="empty-state-box">
                    <div class="empty-title">Chưa có nội dung</div>
                </div>
            `;
            return;
        }

        let html = items.map(item => {
            if (item.type === 'comment') {
                return `
                    <div class="profile-item-card" data-action="open-detail" data-post-id="${item.post_id}">
                        <div class="profile-item-meta">
                            ${Icons.messageCircle(13)} Bình luận trong <strong>${escapeHtml(item.post_title || '')}</strong> • ${timeAgo(item.created_at)}
                        </div>
                        <div class="profile-item-comment-body">${item.content_html || ''}</div>
                        <div class="profile-item-votes">▲ ${item.like_count || 0} &nbsp; ▼ ${item.dislike_count || 0}</div>
                    </div>
                `;
            }
            return `
                <div class="profile-item-card" data-action="open-detail" data-post-id="${item.id}">
                    <div class="profile-item-meta">
                        ${item.category_name ? `${Icons.folder(13)} d/${escapeHtml(item.category_name)} • ` : ''}${timeAgo(item.created_at)}
                    </div>
                    <div class="profile-item-title">${escapeHtml(item.title || '')}</div>
                </div>
            `;
        }).join('');

        if (state.profileTabHasMore) {
            html += `<div style="text-align:center;padding:16px 0;"><button class="btn btn-secondary" id="btn-profile-tab-load-more">Tải thêm</button></div>`;
        }

        container.innerHTML = html;
    }

    function closeUserHoverCard() {
        document.getElementById('user-hover-card-root')?.remove();
    }

    async function showUserHoverCard(username, anchorEl) {
        closeUserHoverCard();
        if (!username || !anchorEl) return;

        const rect = anchorEl.getBoundingClientRect();
        const root = document.createElement('div');
        root.id = 'user-hover-card-root';
        root.className = 'user-hover-card';
        root.innerHTML = `<div class="user-hover-card-loading">Đang tải...</div>`;
        document.body.appendChild(root);

        const cardWidth = 300;
        let left = rect.left;
        if (left + cardWidth > window.innerWidth - 12) left = window.innerWidth - cardWidth - 12;
        if (left < 12) left = 12;
        root.style.left = `${Math.max(12, left)}px`;
        root.style.top = `${rect.bottom + 8}px`;

        try {
            const res = await apiCall(`/api/users/${encodeURIComponent(username)}`);
            const p = res?.data;
            if (!p) throw new Error('not found');
            const roleBadge = p.role_name === 'admin' ? '<span class="role-badge role-admin">Admin</span>' : (p.role_name === 'moderator' ? '<span class="role-badge role-moderator">Mod</span>' : '');
            const initial = escapeHtml((p.display_name || p.username || '?').charAt(0).toUpperCase());
            const avatarHtml = p.avatar_path
                ? `<img src="${config.baseUrl}${escapeHtml(p.avatar_path)}" class="user-hover-card-avatar-img" alt="${escapeHtml(p.username)}">`
                : `<div class="user-hover-card-avatar-fallback">${initial}</div>`;

            root.innerHTML = `
                <div class="user-hover-card-header">
                    ${avatarHtml}
                    <div>
                        <div class="user-hover-card-name">${escapeHtml(p.display_name || p.username)} ${roleBadge}</div>
                        <div class="user-hover-card-username">u/${escapeHtml(p.username)}</div>
                    </div>
                </div>
                <div class="user-hover-card-joined">${Icons.clock ? Icons.clock(13) : ''} Tham gia ${formatJoinDate(p.created_at)}</div>
                ${p.bio ? `<div class="user-hover-card-bio">${escapeHtml(p.bio)}</div>` : ''}
                <div class="user-hover-card-stats">
                    <div><strong>${p.post_karma ?? 0}</strong><span>Post karma</span></div>
                    <div><strong>${p.comment_karma ?? 0}</strong><span>Comment karma</span></div>
                </div>
                <button class="btn btn-secondary user-hover-card-view-btn" id="hovercard-view-profile" data-username="${escapeHtml(p.username)}">Xem trang cá nhân</button>
            `;
        } catch (err) {
            root.innerHTML = `<div class="user-hover-card-error">Không thể tải thông tin người dùng.</div>`;
        }
    }

    // --- Global Click & Event Binding ---
    function bindGlobalEvents() {
        document.addEventListener('click', async (e) => {
            const target = e.target.closest('[data-action], [data-feed-action], [data-sort], [data-cat-id], [data-tag-id], [data-demo], [data-username], [data-profile-tab], [data-lightbox-src], #hovercard-view-profile, #btn-open-login, #btn-open-register, #btn-create-post-top, #quick-create-trigger, #quick-photo-trigger, #quick-question-trigger, #btn-sidebar-create-post, #empty-create-btn, #btn-theme-toggle, #user-profile-pill, #btn-notifications, .notif-item, #btn-read-all-notifs, #menu-item-profile, #menu-item-bookmarks, #menu-item-myposts, #menu-item-moderation, #menu-item-logout, #logo-home-link, #btn-load-more, #btn-clear-search, #btn-clear-active-filter, #btn-toggle-menu, #rail-my-bookmarks, #rail-my-posts, #rail-moderation-center');

            if (!e.target.closest('.user-hover-card') && !e.target.closest('[data-username]')) {
                closeUserHoverCard();
            }

            if (!target) {
                // Close open dropdowns if clicked outside
                closeDropdowns();
                return;
            }

            // Close dropdowns if not clicking within user pill
            if (!target.closest('#user-profile-pill') && !target.closest('#btn-notifications') && !target.closest('#btn-post-options')) {
                closeDropdowns();
            }

            // 1. Theme Toggle
            if (target.id === 'btn-theme-toggle') {
                state.theme = state.theme === 'dark' ? 'light' : 'dark';
                localStorage.setItem('devai_theme', state.theme);
                document.documentElement.setAttribute('data-theme', state.theme);
                updateNavUserArea();
                return;
            }

            // 2. Home Logo
            if (target.id === 'logo-home-link' || target.getAttribute('data-feed-action') === 'all') {
                e.preventDefault();
                state.filters = { sort: 'popular', type: '', category: '', tag: '', q: '', solved: '', unanswered: '' };
                window.history.pushState({}, '', `${config.baseUrl}/`);
                state.activeView = 'feed';
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 2a. Hover Card "View profile" button
            if (target.id === 'hovercard-view-profile') {
                const username = target.getAttribute('data-username');
                closeUserHoverCard();
                if (username) openUserProfilePage(username);
                return;
            }

            // 2a2. Username click → open hover card
            if (target.hasAttribute('data-username')) {
                const username = target.getAttribute('data-username');
                if (username) showUserHoverCard(username, target);
                return;
            }

            // 2a3. Profile page tab switching
            if (target.hasAttribute('data-profile-tab')) {
                loadProfileTab(target.getAttribute('data-profile-tab'));
                return;
            }

            // 2a4. Profile tab "load more"
            if (target.id === 'btn-profile-tab-load-more') {
                loadProfileTab(state.profileTab, state.profileTabPage + 1, true);
                return;
            }

            // 2b. Left Rail Feed Shortcuts (Phổ biến / Mới nhất / Đã giải quyết / Chưa có câu trả lời)
            if (target.hasAttribute('data-feed-action')) {
                const feedAction = target.getAttribute('data-feed-action');
                const base = { sort: 'popular', type: '', category: '', tag: '', q: '', solved: '', unanswered: '' };
                if (feedAction === 'popular') {
                    state.filters = { ...base, sort: 'popular' };
                } else if (feedAction === 'newest') {
                    state.filters = { ...base, sort: 'newest' };
                } else if (feedAction === 'solved') {
                    state.filters = { ...base, sort: 'newest', solved: '1' };
                } else if (feedAction === 'unanswered') {
                    state.filters = { ...base, sort: 'newest', unanswered: '1' };
                } else {
                    return;
                }
                window.history.pushState({}, '', `${config.baseUrl}/`);
                state.activeView = 'feed';
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 2c. Post image lightbox
            if (target.closest('[data-lightbox-src]')) {
                const thumb = target.closest('[data-lightbox-src]');
                openImageLightbox(thumb.getAttribute('data-lightbox-src'), thumb.getAttribute('data-lightbox-alt') || '');
                return;
            }

            // 3. User Pill Dropdown
            if (target.closest('#user-profile-pill')) {
                const dropdown = document.getElementById('user-menu-dropdown');
                dropdown?.classList.toggle('active');
                return;
            }

            // 4. Notifications Dropdown
            if (target.closest('#btn-notifications')) {
                const dropdown = document.getElementById('notifications-dropdown');
                dropdown?.classList.toggle('active');
                if (dropdown?.classList.contains('active')) {
                    renderNotificationsDropdown();
                }
                return;
            }

            // 5. Mobile Drawer Toggle
            if (target.id === 'btn-toggle-menu' || target.closest('#btn-toggle-menu')) {
                document.getElementById('left-rail')?.classList.toggle('mobile-drawer-open');
                return;
            }

            // 6. Quick Demo Login Buttons
            if (target.hasAttribute('data-demo')) {
                const demoType = target.getAttribute('data-demo');
                handleDemoLogin(demoType);
                return;
            }

            // 7. Open Login / Register
            if (target.id === 'btn-open-login') {
                openAuthModal('login');
                return;
            }
            if (target.id === 'btn-open-register') {
                openAuthModal('register');
                return;
            }

            // 8. Logout
            if (target.id === 'menu-item-logout') {
                try {
                    await apiCall('/api/auth/logout', 'POST');
                    state.currentUser = null;
                    showToast('Đã đăng xuất.', 'success');
                    updateNavUserArea();
                    renderLeftRail();
                    loadFeed(1);
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 9. User Menu Items
            if (target.id === 'menu-item-profile') {
                if (state.currentUser?.username) {
                    openUserProfilePage(state.currentUser.username);
                }
                return;
            }
            if (target.id === 'menu-item-bookmarks' || target.id === 'rail-my-bookmarks') {
                openBookmarksModal();
                return;
            }
            if (target.id === 'menu-item-myposts' || target.id === 'rail-my-posts') {
                openMyPostsModal();
                return;
            }
            if (target.id === 'menu-item-moderation' || target.id === 'rail-moderation-center') {
                closeDropdowns();
                document.getElementById('left-rail')?.classList.remove('mobile-drawer-open');
                openModerationCenter();
                return;
            }

            // 10. Create Post Buttons
            if (['btn-create-post-top', 'quick-create-trigger', 'quick-photo-trigger', 'quick-question-trigger', 'btn-sidebar-create-post', 'empty-create-btn'].includes(target.id)) {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để tạo bài viết.', 'info');
                    openAuthModal('login');
                    return;
                }
                const defaultType = target.id === 'quick-question-trigger' ? 'question' : 'discussion';
                openCreatePostModal(defaultType);
                return;
            }

            // 11. Feed Sort Chips
            if (target.hasAttribute('data-sort')) {
                const sortVal = target.getAttribute('data-sort');
                state.filters.solved = '';
                state.filters.unanswered = '';
                if (sortVal === 'solved') {
                    state.filters.solved = '1';
                    state.filters.sort = 'newest';
                } else if (sortVal === 'unanswered') {
                    state.filters.unanswered = '1';
                    state.filters.sort = 'newest';
                } else {
                    state.filters.sort = sortVal;
                }
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 12. Feed Category Filter (Left rail or card badge)
            if (target.hasAttribute('data-cat-id')) {
                const catId = target.getAttribute('data-cat-id');
                state.filters.category = catId;
                state.activeView = 'feed';
                window.history.pushState({}, '', `${config.baseUrl}/`);
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 13. Feed Tag Filter
            if (target.hasAttribute('data-tag-id')) {
                const tagId = target.getAttribute('data-tag-id');
                state.filters.tag = tagId;
                state.activeView = 'feed';
                window.history.pushState({}, '', `${config.baseUrl}/`);
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 14. Clear Active Filter
            if (target.id === 'btn-clear-active-filter') {
                state.filters.category = '';
                state.filters.tag = '';
                state.filters.type = '';
                state.filters.q = '';
                const searchInput = document.getElementById('global-search-input');
                if (searchInput) searchInput.value = '';
                loadFeed(1);
                renderLeftRail();
                return;
            }

            // 15. Load More Button
            if (target.id === 'btn-load-more') {
                loadFeed(state.feedPage + 1, true);
                return;
            }

            // 16. Open Post Detail
            const action = target.getAttribute('data-action');
            const postId = target.getAttribute('data-post-id');

            if (action === 'open-detail' && postId) {
                openPostDetail(parseInt(postId, 10));
                return;
            }

            // 17. Like / Upvote Action
            if (action === 'like' && postId) {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để thích bài viết.', 'info');
                    openAuthModal('login');
                    return;
                }
                try {
                    const res = await apiCall(`/api/posts/${postId}/like`, 'POST');
                    applyPostVoteResult(postId, res);
                    showToast(res.liked ? 'Đã thích bài viết!' : 'Đã bỏ thích.', 'success');
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 17b. Downvote Action
            if (action === 'downvote' && postId) {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để đánh giá bài viết.', 'info');
                    openAuthModal('login');
                    return;
                }
                try {
                    const res = await apiCall(`/api/posts/${postId}/dislike`, 'POST');
                    applyPostVoteResult(postId, res);
                    showToast(res.disliked ? 'Đã đánh giá không thích.' : 'Đã bỏ đánh giá.', 'success');
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 18. Bookmark / Save Post Action
            if (action === 'bookmark' && postId) {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để lưu bài viết.', 'info');
                    openAuthModal('login');
                    return;
                }
                try {
                    const res = await apiCall(`/api/posts/${postId}/bookmark`, 'POST');
                    target.classList.toggle('active', !!res.bookmarked);
                    const span = target.querySelector('span');
                    if (span) span.textContent = res.bookmarked ? 'Đã lưu' : 'Lưu';
                    showToast(res.bookmarked ? 'Đã lưu bài viết vào mục cá nhân!' : 'Đã bỏ lưu bài viết.', 'success');
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 18b. Personal Pin Action
            if (action === 'toggle-pin' && postId) {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để ghim bài viết.', 'info');
                    openAuthModal('login');
                    return;
                }
                try {
                    const res = await apiCall(`/api/posts/${postId}/pin`, 'POST');
                    target.classList.toggle('active', !!res.pinned);
                    const span = target.querySelector('span');
                    if (span) span.textContent = res.pinned ? 'Bỏ ghim' : 'Ghim';
                    const numericPostId = parseInt(postId, 10);
                    const feedItem = state.feed.find(item => parseInt(item.id, 10) === numericPostId);
                    if (feedItem) feedItem.viewer_pinned = res.pinned;
                    if (state.currentPostDetail && parseInt(state.currentPostDetail.id, 10) === numericPostId) {
                        state.currentPostDetail.viewer_pinned = res.pinned;
                    }
                    showToast(res.pinned ? 'Đã ghim bài viết vào mục cá nhân!' : 'Đã bỏ ghim bài viết.', 'success');
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 19. Share Action
            if (action === 'share' && postId) {
                const shareUrl = `${window.location.origin}${config.baseUrl}/posts/${postId}`;
                navigator.clipboard?.writeText(shareUrl).then(() => {
                    showToast('Đã sao chép liên kết bài viết vào clipboard!', 'success');
                }).catch(() => {
                    showToast(`Liên kết: ${shareUrl}`, 'info');
                });
                return;
            }

            // 19b. Post Owner Menu (three-dot)
            if (action === 'toggle-post-menu') {
                document.getElementById('post-owner-menu')?.classList.toggle('active');
                return;
            }
            if (action === 'edit-post' && postId) {
                try {
                    const res = await apiCall(`/api/posts/${postId}`);
                    if (res?.data) openEditPostModal(res.data);
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }
            if (action === 'delete-post' && postId) {
                if (!confirm('Bạn có chắc chắn muốn xóa bài viết này không? Hành động này không thể hoàn tác.')) return;
                try {
                    await refreshCsrf();
                    await apiCall(`/api/posts/${postId}`, 'DELETE');
                    showToast('Đã xóa bài viết.', 'success');
                    window.history.pushState({}, '', `${config.baseUrl}/`);
                    state.activeView = 'feed';
                    loadFeed(1);
                    renderLeftRail();
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 20. Report Action
            if (action === 'report' && postId) {
                openReportModal(parseInt(postId, 10), null);
                return;
            }
            if (action === 'report-comment') {
                const commentId = target.getAttribute('data-comment-id');
                openReportModal(null, parseInt(commentId, 10));
                return;
            }

            // 20b. Comment Like / Dislike
            if (action === 'comment-like' || action === 'comment-dislike') {
                if (!state.currentUser) {
                    showToast('Vui lòng đăng nhập để đánh giá bình luận.', 'info');
                    openAuthModal('login');
                    return;
                }
                const commentId = target.getAttribute('data-comment-id');
                const endpoint = action === 'comment-like' ? 'like' : 'dislike';
                try {
                    const res = await apiCall(`/api/comments/${commentId}/${endpoint}`, 'POST');
                    const netScore = (res.like_count || 0) - (res.dislike_count || 0);
                    const scoreEl = document.getElementById(`comment-score-${commentId}`);
                    if (scoreEl) scoreEl.textContent = netScore;
                    document.querySelectorAll(`[data-action="comment-like"][data-comment-id="${commentId}"]`).forEach(btn => {
                        btn.classList.toggle('active-up', !!res.liked);
                        btn.setAttribute('aria-pressed', String(!!res.liked));
                    });
                    document.querySelectorAll(`[data-action="comment-dislike"][data-comment-id="${commentId}"]`).forEach(btn => {
                        btn.classList.toggle('active-down', !!res.disliked);
                        btn.setAttribute('aria-pressed', String(!!res.disliked));
                    });
                    const updateCommentTree = (comments) => {
                        for (const c of comments || []) {
                            if (String(c.id) === String(commentId)) {
                                c.like_count = res.like_count;
                                c.dislike_count = res.dislike_count;
                                c.viewer_liked = res.liked;
                                c.viewer_disliked = res.disliked;
                                return true;
                            }
                            if (c.children && updateCommentTree(c.children)) return true;
                        }
                        return false;
                    };
                    if (state.currentPostDetail?.comments) {
                        updateCommentTree(state.currentPostDetail.comments);
                    }
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 21. Collapse/Expand Comment Thread
            if (action === 'collapse-comment') {
                const commentId = target.getAttribute('data-comment-id');
                const collapsibleNode = document.getElementById(`comment-collapsible-${commentId}`);
                const btn = document.querySelector(`.comment-collapse-btn[data-comment-id="${commentId}"]`);
                const itemNode = document.getElementById(`comment-node-${commentId}`);
                if (collapsibleNode) {
                    const isHidden = collapsibleNode.style.display === 'none';
                    collapsibleNode.style.display = isHidden ? '' : 'none';
                    if (btn) btn.textContent = isHidden ? '[-]' : '[+]';
                    if (itemNode) itemNode.classList.toggle('is-collapsed', !isHidden);
                }
                return;
            }

            // 22. Inline Reply to Comment
            if (action === 'reply-comment') {
                const commentId = target.getAttribute('data-comment-id');
                const slot = document.getElementById(`reply-form-slot-${commentId}`);
                if (!slot) return;
                if (slot.innerHTML !== '') {
                    slot.innerHTML = '';
                    return;
                }
                slot.innerHTML = `
                    <div class="nested-reply-box">
                        <textarea class="comment-textarea" id="reply-input-${commentId}" placeholder="Viết câu trả lời..." rows="2"></textarea>
                        <div class="composer-toolbar">
                            <button class="btn btn-ghost" onclick="document.getElementById('reply-form-slot-${commentId}').innerHTML = ''">Hủy</button>
                            <button class="btn btn-primary" id="btn-submit-reply-${commentId}">Trả lời</button>
                        </div>
                    </div>
                `;
                document.getElementById(`btn-submit-reply-${commentId}`)?.addEventListener('click', async () => {
                    const replyInput = document.getElementById(`reply-input-${commentId}`);
                    const content = replyInput?.value?.trim();
                    if (!content) return;
                    try {
                        await apiCall(`/api/posts/${state.currentPostId}/comments`, 'POST', {
                            content_html: `<p>${escapeHtml(content)}</p>`,
                            parent_id: parseInt(commentId, 10),
                        });
                        showToast('Đã gửi phản hồi!', 'success');
                        openPostDetail(state.currentPostId, false);
                    } catch (err) {
                        showToast(err.message, 'error');
                    }
                });
                return;
            }

            // 23. Set Best Answer
            if (action === 'set-best-answer') {
                const commentId = target.getAttribute('data-comment-id');
                try {
                    await apiCall(`/api/posts/${state.currentPostId}/best-answer`, 'POST', {
                        comment_id: parseInt(commentId, 10),
                    });
                    showToast('Đã chọn làm câu trả lời hay nhất!', 'success');
                    openPostDetail(state.currentPostId, false);
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 24. Delete Comment
            if (action === 'delete-comment') {
                const commentId = target.getAttribute('data-comment-id');
                if (!confirm('Bạn có chắc chắn muốn xóa bình luận này không?')) return;
                try {
                    await apiCall(`/api/comments/${commentId}`, 'DELETE');
                    showToast('Đã xóa bình luận.', 'success');
                    openPostDetail(state.currentPostId, false);
                } catch (err) {
                    showToast(err.message, 'error');
                }
                return;
            }

            // 25. Moderator Quick Menu on Post
            if (action === 'mod-menu' && postId) {
                const numericPostId = parseInt(postId, 10);
                const post = state.currentPostDetail?.id == numericPostId
                    ? state.currentPostDetail
                    : state.feed.find(item => parseInt(item.id, 10) === numericPostId);
                openModeratorModal(numericPostId, post);
                return;
            }
        });

        // Search Input listener (with debounce)
        let searchTimeout = null;
        document.addEventListener('input', (e) => {
            if (e.target.id === 'global-search-input') {
                const val = e.target.value.trim();
                const clearBtn = document.getElementById('btn-clear-search');
                if (clearBtn) clearBtn.style.display = val ? 'block' : 'none';

                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    state.filters.q = val;
                    if (state.activeView !== 'feed') {
                        state.activeView = 'feed';
                        window.history.pushState({}, '', `${config.baseUrl}/`);
                    }
                    loadFeed(1);
                }, 400);
            }
        });

        // Browser back/forward navigation
        window.addEventListener('popstate', (e) => {
            const pathMatches = window.location.pathname.match(/\/posts\/(\d+)/);
            if (window.location.pathname.endsWith('/moderation') && canAccessModerationCenter()) {
                openModerationCenter(false);
            } else if (pathMatches) {
                openPostDetail(parseInt(pathMatches[1], 10), false);
            } else {
                state.activeView = 'feed';
                loadFeed(1);
            }
        });
    }

    function closeDropdowns() {
        document.querySelectorAll('.dropdown-menu.active').forEach(el => el.classList.remove('active'));
    }

    // --- Demo Quick Login ---
    async function handleDemoLogin(role) {
        const credentials = {
            admin: { login: 'admin_devai', password: 'Demo@123' },
            mod: { login: 'mod_linh', password: 'Demo@123' },
            member: { login: 'an_nguyen', password: 'Demo@123' },
        };

        const cred = credentials[role];
        if (!cred) return;

        try {
            await refreshCsrf();
            const res = await apiCall('/api/auth/login', 'POST', cred);
            showToast(`Đăng nhập thành công với vai trò: ${role.toUpperCase()} (${cred.login})`, 'success');
            
            // Fetch updated current user info
            const meRes = await apiCall('/api/me');
            state.currentUser = meRes.user;
            state.currentUser.permissions = meRes.permissions || [];

            updateNavUserArea();
            renderLeftRail();
            loadFeed(1);
        } catch (err) {
            showToast(`Đăng nhập thất bại: ${err.message}`, 'error');
        }
    }

    // --- Modals Engine ---
    function openModal(htmlContent, sizeClass = '') {
        const root = document.getElementById('modal-root');
        if (!root) return;

        root.innerHTML = `
            <div class="modal-overlay" id="active-modal-overlay">
                <div class="modal-dialog ${sizeClass}">
                    ${htmlContent}
                </div>
            </div>
        `;

        const overlay = document.getElementById('active-modal-overlay');
        overlay?.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-modal-close]')) {
                closeModal();
            }
        });

        // Esc key closes modal
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                window.removeEventListener('keydown', handleKeyDown);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
    }

    function closeModal() {
        const root = document.getElementById('modal-root');
        if (root) root.innerHTML = '';
    }

    // --- Image Lightbox ---
    function openImageLightbox(src, alt = '') {
        const root = document.getElementById('modal-root');
        if (!root) return;

        root.innerHTML = `
            <div class="lightbox-overlay" id="active-lightbox-overlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 24px; animation: fadeIn 150ms ease;">
                <button type="button" data-lightbox-close aria-label="Đóng" style="position: absolute; top: 16px; right: 20px; width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); color: #fff; display: flex; align-items: center; justify-content: center;">
                    ${Icons.x(20)}
                </button>
                <img src="${src}" alt="${alt}" style="max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: var(--radius-md);">
            </div>
        `;

        const overlay = document.getElementById('active-lightbox-overlay');
        overlay?.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.closest('[data-lightbox-close]')) {
                closeModal();
            }
        });

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                window.removeEventListener('keydown', handleKeyDown);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
    }

    // --- Auth Modal (Login / Register) ---
    function openAuthModal(initialTab = 'login') {
        const html = `
            <div class="modal-header">
                <div class="modal-title">${initialTab === 'login' ? 'Đăng nhập vào DevAI Hub' : 'Đăng ký tài khoản mới'}</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body">
                <div class="auth-tabs">
                    <div class="auth-tab ${initialTab === 'login' ? 'active' : ''}" id="tab-btn-login" style="cursor: pointer;">Đăng nhập</div>
                    <div class="auth-tab ${initialTab === 'register' ? 'active' : ''}" id="tab-btn-register" style="cursor: pointer;">Đăng ký</div>
                </div>

                <!-- Form Login -->
                <div id="auth-form-login" style="display: ${initialTab === 'login' ? 'flex' : 'none'}; flex-direction: column; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Tên người dùng hoặc Email</label>
                        <input type="text" class="form-input" id="auth-login-input" placeholder="ví dụ: admin_devai hoặc an_nguyen" autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" class="form-input" id="auth-password-input" placeholder="Nhập mật khẩu (Demo: Demo@123)">
                    </div>
                    <button class="btn btn-primary" id="btn-submit-login" style="width: 100%; padding: 10px; margin-top: 4px;">Đăng nhập</button>

                    <!-- Quick Demo Accounts -->
                    <div class="demo-account-box">
                        <div class="demo-account-title">Hoặc đăng nhập nhanh bằng tài khoản thử nghiệm:</div>
                        <div class="demo-btns-row">
                            <button class="btn btn-secondary demo-pill-btn" data-demo="admin" style="flex: 1;">Admin</button>
                            <button class="btn btn-secondary demo-pill-btn" data-demo="mod" style="flex: 1;">Moderator</button>
                            <button class="btn btn-secondary demo-pill-btn" data-demo="member" style="flex: 1;">Member</button>
                        </div>
                    </div>
                </div>

                <!-- Form Register -->
                <div id="auth-form-register" style="display: ${initialTab === 'register' ? 'flex' : 'none'}; flex-direction: column; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Tên đăng nhập (Username)</label>
                        <input type="text" class="form-input" id="reg-username-input" placeholder="ví dụ: developer_ai">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tên hiển thị (Display name)</label>
                        <input type="text" class="form-input" id="reg-display-input" placeholder="ví dụ: Nguyễn Văn A">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Địa chỉ Email</label>
                        <input type="email" class="form-input" id="reg-email-input" placeholder="name@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mật khẩu (tối thiểu 8 ký tự)</label>
                        <input type="password" class="form-input" id="reg-password-input" placeholder="Mật khẩu bảo mật">
                    </div>
                    <button class="btn btn-primary" id="btn-submit-register" style="width: 100%; padding: 10px; margin-top: 4px;">Đăng ký tài khoản</button>
                </div>
            </div>
        `;

        openModal(html);

        // Tab Switching
        const tabLogin = document.getElementById('tab-btn-login');
        const tabRegister = document.getElementById('tab-btn-register');
        const formLogin = document.getElementById('auth-form-login');
        const formRegister = document.getElementById('auth-form-register');

        tabLogin?.addEventListener('click', () => {
            tabLogin.classList.add('active');
            tabRegister?.classList.remove('active');
            if (formLogin) formLogin.style.display = 'flex';
            if (formRegister) formRegister.style.display = 'none';
        });

        tabRegister?.addEventListener('click', () => {
            tabRegister.classList.add('active');
            tabLogin?.classList.remove('active');
            if (formRegister) formRegister.style.display = 'flex';
            if (formLogin) formLogin.style.display = 'none';
        });

        // Submit Login
        document.getElementById('btn-submit-login')?.addEventListener('click', async () => {
            const login = document.getElementById('auth-login-input')?.value.trim();
            const password = document.getElementById('auth-password-input')?.value;
            if (!login || !password) {
                showToast('Vui lòng nhập đầy đủ tài khoản và mật khẩu.', 'error');
                return;
            }
            try {
                await refreshCsrf();
                await apiCall('/api/auth/login', 'POST', { login, password });
                showToast('Đăng nhập thành công!', 'success');
                closeModal();
                const meRes = await apiCall('/api/me');
                state.currentUser = meRes.user;
                state.currentUser.permissions = meRes.permissions || [];
                updateNavUserArea();
                renderLeftRail();
                loadFeed(1);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        // Submit Register
        document.getElementById('btn-submit-register')?.addEventListener('click', async () => {
            const username = document.getElementById('reg-username-input')?.value.trim();
            const display_name = document.getElementById('reg-display-input')?.value.trim();
            const email = document.getElementById('reg-email-input')?.value.trim();
            const password = document.getElementById('reg-password-input')?.value;

            if (!username || !email || !password) {
                showToast('Vui lòng điền đầy đủ các thông tin đăng ký.', 'error');
                return;
            }

            try {
                await refreshCsrf();
                await apiCall('/api/auth/register', 'POST', {
                    username,
                    display_name: display_name || username,
                    email,
                    password,
                });
                showToast('Đăng ký tài khoản thành công! Đang đăng nhập...', 'success');
                closeModal();
                const meRes = await apiCall('/api/me');
                state.currentUser = meRes.user;
                state.currentUser.permissions = meRes.permissions || [];
                updateNavUserArea();
                renderLeftRail();
                loadFeed(1);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Create Post Modal (Composer) ---
    function openCreatePostModal(defaultType = 'discussion') {
        const categoriesOptions = state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        const tagsPills = state.tags.map(t => `<div class="tag-select-pill" data-tag-picker-id="${t.id}">#${escapeHtml(t.name)}</div>`).join('');

        const html = `
            <div class="modal-header">
                <div class="modal-title">Tạo Bài Viết Mới</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body">
                <!-- Post Type Tabs -->
                <div class="type-tabs-row">
                    <button class="type-tab-btn ${defaultType === 'discussion' ? 'active' : ''}" data-post-type="discussion">${Icons.messageCircle(14)} Thảo luận</button>
                    <button class="type-tab-btn ${defaultType === 'question' ? 'active' : ''}" data-post-type="question">${Icons.helpCircle(14)} Câu hỏi</button>
                    <button class="type-tab-btn ${defaultType === 'resource' ? 'active' : ''}" data-post-type="resource">${Icons.package(14)} Tài nguyên</button>
                    <button class="type-tab-btn ${defaultType === 'job' ? 'active' : ''}" data-post-type="job">${Icons.briefcase(14)} Việc làm</button>
                </div>

                <div class="form-group">
                    <label class="form-label">Chuyên mục (*)</label>
                    <select class="form-select" id="create-post-category">
                        ${categoriesOptions}
                    </select>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between;">
                        <label class="form-label">Tiêu đề bài viết (*)</label>
                        <span class="char-counter" id="title-char-counter">0/255</span>
                    </div>
                    <input type="text" class="form-input" id="create-post-title" maxlength="255" placeholder="Nhập tiêu đề rõ ràng, mô tả đúng nội dung..." autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Nội dung chi tiết (*)</label>
                    ${richTextEditorHtml('create-post-content', 'Chia sẻ suy nghĩ, đặt câu hỏi kèm ví dụ code hay tài liệu tham khảo...')}
                </div>

                <div class="form-group">
                    <label class="form-label">Chọn thẻ công nghệ (tối đa 5 thẻ)</label>
                    <div class="tags-selector-wrapper" id="tag-picker-container">
                        ${tagsPills}
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Đính kèm ảnh (Tối đa 5 ảnh JPG/PNG/WEBP <= 15MB)</label>
                    <input type="file" id="create-post-images" multiple accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" data-modal-close>Hủy</button>
                <button class="btn btn-primary" id="btn-submit-create-post">Đăng bài viết</button>
            </div>
        `;

        openModal(html, 'modal-lg');
        bindRichTextToolbar('create-post-content');

        let selectedType = defaultType;
        const selectedTagIds = new Set();

        // Type selection
        document.querySelectorAll('.type-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.type-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedType = btn.getAttribute('data-post-type');
            });
        });

        // Title char counter
        const titleInput = document.getElementById('create-post-title');
        const charCounter = document.getElementById('title-char-counter');
        titleInput?.addEventListener('input', () => {
            if (charCounter && titleInput) {
                charCounter.textContent = `${titleInput.value.length}/255`;
            }
        });

        // Tag picking
        document.querySelectorAll('[data-tag-picker-id]').forEach(pill => {
            pill.addEventListener('click', () => {
                const id = parseInt(pill.getAttribute('data-tag-picker-id'), 10);
                if (selectedTagIds.has(id)) {
                    selectedTagIds.delete(id);
                    pill.classList.remove('selected');
                } else {
                    if (selectedTagIds.size >= 5) {
                        showToast('Chỉ được chọn tối đa 5 thẻ.', 'info');
                        return;
                    }
                    selectedTagIds.add(id);
                    pill.classList.add('selected');
                }
            });
        });

        // Submit Post
        document.getElementById('btn-submit-create-post')?.addEventListener('click', async () => {
            const title = titleInput?.value.trim();
            const contentEditor = document.getElementById('create-post-content');
            const plainContent = contentEditor?.textContent.trim() || '';
            const category_id = document.getElementById('create-post-category')?.value;
            const fileInput = document.getElementById('create-post-images');

            if (!title) {
                showToast('Vui lòng nhập tiêu đề bài viết.', 'error');
                return;
            }
            if (title.length < 10) {
                showToast('Tiêu đề phải có ít nhất 10 ký tự.', 'error');
                return;
            }
            if (!plainContent) {
                showToast('Vui lòng nhập nội dung bài viết.', 'error');
                return;
            }
            if (plainContent.length < 10) {
                showToast('Nội dung phải có ít nhất 10 ký tự.', 'error');
                return;
            }
            if (!category_id) {
                showToast('Vui lòng chọn chuyên mục.', 'error');
                return;
            }

            let contentHtml = normalizeRichHtml(contentEditor.innerHTML).trim();
            if (!contentHtml.startsWith('<')) {
                contentHtml = `<p>${contentHtml}</p>`;
            }

            const formData = new FormData();
            formData.append('title', title);
            formData.append('content_html', contentHtml);
            formData.append('category_id', category_id);
            formData.append('post_type', selectedType);
            formData.append('status', 'published');

            selectedTagIds.forEach(tagId => {
                formData.append('tag_ids[]', tagId);
            });

            if (fileInput?.files) {
                for (let i = 0; i < fileInput.files.length; i++) {
                    formData.append('images[]', fileInput.files[i]);
                }
            }

            try {
                await refreshCsrf();
                const res = await apiCall('/api/posts', 'POST', formData, true);
                showToast('Bài viết đã được đăng thành công!', 'success');
                closeModal();
                if (res?.id) {
                    openPostDetail(res.id);
                } else {
                    loadFeed(1);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Edit Post Modal (Owner Only) ---
    function openEditPostModal(post) {
        const postTagIds = new Set((post.tags || []).map(t => t.id));
        const categoriesOptions = state.categories.map(c => `<option value="${c.id}" ${String(c.id) === String(post.category_id) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('');
        const tagsPills = state.tags.map(t => `<div class="tag-select-pill ${postTagIds.has(t.id) ? 'selected' : ''}" data-tag-picker-id="${t.id}">#${escapeHtml(t.name)}</div>`).join('');

        const html = `
            <div class="modal-header">
                <div class="modal-title">Chỉnh Sửa Bài Viết</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body">
                <!-- Post Type Tabs -->
                <div class="type-tabs-row">
                    <button class="type-tab-btn ${post.post_type === 'discussion' ? 'active' : ''}" data-post-type="discussion">${Icons.messageCircle(14)} Thảo luận</button>
                    <button class="type-tab-btn ${post.post_type === 'question' ? 'active' : ''}" data-post-type="question">${Icons.helpCircle(14)} Câu hỏi</button>
                    <button class="type-tab-btn ${post.post_type === 'resource' ? 'active' : ''}" data-post-type="resource">${Icons.package(14)} Tài nguyên</button>
                    <button class="type-tab-btn ${post.post_type === 'job' ? 'active' : ''}" data-post-type="job">${Icons.briefcase(14)} Việc làm</button>
                </div>

                <div class="form-group">
                    <label class="form-label">Chuyên mục (*)</label>
                    <select class="form-select" id="edit-post-category">
                        ${categoriesOptions}
                    </select>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between;">
                        <label class="form-label">Tiêu đề bài viết (*)</label>
                        <span class="char-counter" id="edit-title-char-counter">${(post.title || '').length}/255</span>
                    </div>
                    <input type="text" class="form-input" id="edit-post-title" maxlength="255" value="${escapeHtml(post.title || '')}">
                </div>

                <div class="form-group">
                    <label class="form-label">Nội dung chi tiết (*)</label>
                    ${richTextEditorHtml('edit-post-content', 'Chia sẻ suy nghĩ, đặt câu hỏi kèm ví dụ code hay tài liệu tham khảo...')}
                </div>

                <div class="form-group">
                    <label class="form-label">Chọn thẻ công nghệ (tối đa 5 thẻ)</label>
                    <div class="tags-selector-wrapper" id="edit-tag-picker-container">
                        ${tagsPills}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" data-modal-close>Hủy</button>
                <button class="btn btn-primary" id="btn-submit-edit-post">Lưu thay đổi</button>
            </div>
        `;

        openModal(html, 'modal-lg');
        bindRichTextToolbar('edit-post-content');

        const contentEditor = document.getElementById('edit-post-content');
        if (contentEditor) contentEditor.innerHTML = post.content_html || '';

        let selectedType = post.post_type || 'discussion';
        const selectedTagIds = new Set(postTagIds);

        document.querySelectorAll('.type-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.type-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedType = btn.getAttribute('data-post-type');
            });
        });

        const titleInput = document.getElementById('edit-post-title');
        const charCounter = document.getElementById('edit-title-char-counter');
        titleInput?.addEventListener('input', () => {
            if (charCounter && titleInput) {
                charCounter.textContent = `${titleInput.value.length}/255`;
            }
        });

        document.querySelectorAll('#edit-tag-picker-container [data-tag-picker-id]').forEach(pill => {
            pill.addEventListener('click', () => {
                const id = parseInt(pill.getAttribute('data-tag-picker-id'), 10);
                if (selectedTagIds.has(id)) {
                    selectedTagIds.delete(id);
                    pill.classList.remove('selected');
                } else {
                    if (selectedTagIds.size >= 5) {
                        showToast('Chỉ được chọn tối đa 5 thẻ.', 'info');
                        return;
                    }
                    selectedTagIds.add(id);
                    pill.classList.add('selected');
                }
            });
        });

        document.getElementById('btn-submit-edit-post')?.addEventListener('click', async () => {
            const title = titleInput?.value.trim();
            const plainContent = contentEditor?.textContent.trim() || '';
            const category_id = document.getElementById('edit-post-category')?.value;

            if (!title) {
                showToast('Vui lòng nhập tiêu đề bài viết.', 'error');
                return;
            }
            if (title.length < 10) {
                showToast('Tiêu đề phải có ít nhất 10 ký tự.', 'error');
                return;
            }
            if (!plainContent) {
                showToast('Vui lòng nhập nội dung bài viết.', 'error');
                return;
            }
            if (plainContent.length < 10) {
                showToast('Nội dung phải có ít nhất 10 ký tự.', 'error');
                return;
            }
            if (!category_id) {
                showToast('Vui lòng chọn chuyên mục.', 'error');
                return;
            }

            let contentHtml = normalizeRichHtml(contentEditor.innerHTML).trim();
            if (!contentHtml.startsWith('<')) {
                contentHtml = `<p>${contentHtml}</p>`;
            }

            try {
                await refreshCsrf();
                await apiCall(`/api/posts/${post.id}`, 'PUT', {
                    title, content_html: contentHtml, category_id, post_type: selectedType,
                    status: post.status === 'draft' ? 'draft' : 'published',
                    tag_ids: Array.from(selectedTagIds),
                });
                showToast('Đã cập nhật bài viết!', 'success');
                closeModal();
                openPostDetail(post.id);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Report Modal ---
    function openReportModal(postId = null, commentId = null) {
        if (!state.currentUser) {
            showToast('Vui lòng đăng nhập để gửi báo cáo vi phạm.', 'info');
            openAuthModal('login');
            return;
        }

        const html = `
            <div class="modal-header">
                <div class="modal-title">Báo Cáo Vi Phạm Nội Dung</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Lý do báo cáo (*)</label>
                    <select class="form-select" id="report-reason-select">
                        <option value="spam">Spam / Quảng cáo rác</option>
                        <option value="harassment">Quấy rối / Ngôn từ công kích thù hằn</option>
                        <option value="misinformation">Thông tin sai lệch / Giả mạo</option>
                        <option value="copyright">Vi phạm bản quyền sở hữu trí tuệ</option>
                        <option value="other">Lý do khác</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mô tả chi tiết bổ sung (tùy chọn)</label>
                    <textarea class="form-textarea" id="report-details-input" rows="3" placeholder="Cung cấp thêm chi tiết để kiểm duyệt viên nắm rõ tình huống..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" data-modal-close>Hủy</button>
                <button class="btn btn-primary" id="btn-submit-report">Gửi báo cáo</button>
            </div>
        `;

        openModal(html);

        document.getElementById('btn-submit-report')?.addEventListener('click', async () => {
            const reason = document.getElementById('report-reason-select')?.value;
            const details = document.getElementById('report-details-input')?.value.trim();

            const payload = { reason, details };
            if (postId) payload.post_id = postId;
            if (commentId) payload.comment_id = commentId;

            try {
                await apiCall('/api/reports', 'POST', payload);
                showToast('Cảm ơn bạn! Báo cáo đã được gửi tới đội ngũ quản trị kiểm duyệt.', 'success');
                closeModal();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Dedicated Moderator Review Center ---
    async function openModerationCenter(updateHistory = true) {
        if (!canAccessModerationCenter()) {
            showToast('Bạn không có quyền truy cập khu vực kiểm duyệt.', 'error');
            return;
        }

        state.activeView = 'moderation';
        if (updateHistory) {
            window.history.pushState({ view: 'moderation' }, '', `${config.baseUrl}/moderation`);
        }
        renderLeftRail();

        const main = document.getElementById('main-content');
        const sidebar = document.getElementById('right-sidebar');
        if (!main) return;

        main.innerHTML = `
            <section class="moderation-page" aria-labelledby="moderation-title">
                <div class="moderation-hero">
                    <div class="moderation-hero-icon" aria-hidden="true">${Icons.shieldCheck(28)}</div>
                    <div>
                        <p class="moderation-eyebrow">Không gian làm việc của Moderator</p>
                        <h1 id="moderation-title">Trung tâm kiểm duyệt</h1>
                        <p>Rà soát bài đăng mới và xử lý báo cáo từ cộng đồng. Các chức năng quản trị người dùng, tag và hệ thống không xuất hiện tại đây.</p>
                    </div>
                </div>
                <div class="moderation-loading" aria-label="Đang tải dữ liệu kiểm duyệt">
                    <div class="skeleton skeleton-card"></div>
                    <div class="skeleton skeleton-card"></div>
                </div>
            </section>
        `;

        try {
            const [reportsResponse, postsResponse] = await Promise.all([
                apiCall('/api/admin/reports?status=pending'),
                apiCall('/api/feed?sort=newest&limit=20'),
            ]);
            const reports = reportsResponse?.data || [];
            const posts = postsResponse?.data || [];
            renderModerationWorkspace(reports, posts);

            if (sidebar) {
                sidebar.innerHTML = `
                    <div class="sidebar-widget moderation-scope-card">
                        <div class="widget-header">Phạm vi Moderator</div>
                        <div class="widget-body">
                            <ul class="moderation-scope-list">
                                <li><span>${Icons.check(13)}</span> Xem bài như thành viên bình thường</li>
                                <li><span>${Icons.check(13)}</span> Ghim, khóa, ẩn hoặc khôi phục bài</li>
                                <li><span>${Icons.check(13)}</span> Chuẩn hóa tiêu đề bài viết</li>
                                <li><span>${Icons.check(13)}</span> Xử lý hoặc bác bỏ báo cáo</li>
                                <li class="scope-disabled"><span>–</span> Không quản lý tài khoản và cấu hình hệ thống</li>
                            </ul>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            main.innerHTML = `
                <div class="empty-state-box">
                    <div class="empty-icon">!</div>
                    <div class="empty-title">Không thể tải hàng đợi kiểm duyệt</div>
                    <div class="empty-desc">${escapeHtml(error.message)}</div>
                    <button class="btn btn-secondary" id="btn-retry-moderation">Thử lại</button>
                </div>
            `;
            document.getElementById('btn-retry-moderation')?.addEventListener('click', () => openModerationCenter(false));
        }
    }

    function renderModerationWorkspace(reports, posts) {
        const main = document.getElementById('main-content');
        if (!main) return;

        const reasonLabels = {
            spam: 'Spam / quảng cáo',
            harassment: 'Quấy rối',
            misinformation: 'Thông tin sai lệch',
            copyright: 'Bản quyền',
            other: 'Khác',
        };

        const reportRows = reports.length === 0 ? `
            <div class="moderation-empty-compact">
                <span class="moderation-empty-check">${Icons.checkCircle(20)}</span>
                <div><strong>Hàng đợi đã sạch</strong><p>Không có báo cáo nào đang chờ xử lý.</p></div>
            </div>
        ` : reports.map(report => `
            <article class="review-row report-review-row">
                <div class="review-row-marker report-marker" aria-hidden="true">!</div>
                <div class="review-row-content">
                    <div class="review-row-meta">
                        <span class="review-reason">${escapeHtml(reasonLabels[report.reason] || report.reason)}</span>
                        <span>Report #${report.id}</span>
                        <span>•</span>
                        <span>u/${escapeHtml(report.reporter_username)}</span>
                        <span>•</span>
                        <time>${timeAgo(report.created_at)}</time>
                    </div>
                    <h3>${report.post_id ? escapeHtml(report.post_title || `Bài viết #${report.post_id}`) : `Bình luận #${report.comment_id}`}</h3>
                    <p class="review-row-description">${escapeHtml(report.details || 'Người báo cáo không cung cấp mô tả bổ sung.')}</p>
                    ${report.comment_content ? `<div class="reported-comment-preview">${report.comment_content}</div>` : ''}
                    <div class="review-row-actions">
                        ${report.target_post_id ? `<button class="btn btn-ghost" data-review-open-post="${report.target_post_id}">Xem nội dung</button>` : ''}
                        <button class="btn btn-secondary" data-review-reject="${report.id}">Bác bỏ</button>
                        <button class="btn btn-primary" data-review-resolve="${report.id}">Đã xử lý</button>
                    </div>
                </div>
            </article>
        `).join('');

        const postRows = posts.length === 0 ? `
            <div class="moderation-empty-compact"><div><strong>Chưa có bài mới</strong><p>Danh sách bài đăng gần đây đang trống.</p></div></div>
        ` : posts.map(post => `
            <article class="review-row post-review-row">
                <div class="review-row-marker post-marker" aria-hidden="true">#</div>
                <div class="review-row-content">
                    <div class="review-row-meta">
                        <span class="category-badge">d/${escapeHtml(post.category_name || 'Chung')}</span>
                        <span>u/${escapeHtml(post.username)}</span>
                        <span>•</span>
                        <time>${timeAgo(post.created_at)}</time>
                    </div>
                    <h3>${escapeHtml(post.title)}</h3>
                    <div class="review-post-signals">
                        <span>${post.like_count || 0} lượt thích</span>
                        <span>${post.comment_count || 0} bình luận</span>
                        ${post.is_pinned ? '<span class="moderation-state-badge">Đã ghim</span>' : ''}
                        ${post.is_locked ? '<span class="moderation-state-badge warning">Đã khóa</span>' : ''}
                    </div>
                    <div class="review-row-actions">
                        <button class="btn btn-ghost" data-review-open-post="${post.id}">Xem bài</button>
                        <button class="btn btn-secondary" data-review-post-menu="${post.id}">Thao tác kiểm duyệt</button>
                    </div>
                </div>
            </article>
        `).join('');

        main.innerHTML = `
            <section class="moderation-page" aria-labelledby="moderation-title">
                <div class="moderation-hero">
                    <div class="moderation-hero-icon" aria-hidden="true">${Icons.shieldCheck(28)}</div>
                    <div>
                        <p class="moderation-eyebrow">Không gian làm việc của Moderator</p>
                        <h1 id="moderation-title">Trung tâm kiểm duyệt</h1>
                        <p>Tập trung vào hai nhiệm vụ: duyệt bài và xử lý report.</p>
                    </div>
                </div>
                <div class="moderation-summary-grid">
                    <div class="moderation-summary-card urgent"><strong>${reports.length}</strong><span>Report chờ xử lý</span></div>
                    <div class="moderation-summary-card"><strong>${posts.length}</strong><span>Bài gần đây cần rà soát</span></div>
                </div>
                <section class="moderation-panel" aria-labelledby="report-queue-title">
                    <div class="moderation-panel-header">
                        <div><p class="moderation-eyebrow">Ưu tiên cao</p><h2 id="report-queue-title">Report đang chờ</h2></div>
                        <span class="queue-count">${reports.length}</span>
                    </div>
                    <div class="moderation-list">${reportRows}</div>
                </section>
                <section class="moderation-panel" aria-labelledby="post-review-title">
                    <div class="moderation-panel-header">
                        <div><p class="moderation-eyebrow">Bài đăng mới</p><h2 id="post-review-title">Rà soát nội dung</h2></div>
                        <span class="queue-count neutral">${posts.length}</span>
                    </div>
                    <div class="moderation-list">${postRows}</div>
                </section>
            </section>
        `;

        main.querySelectorAll('[data-review-open-post]').forEach(button => {
            button.addEventListener('click', () => openPostDetail(parseInt(button.dataset.reviewOpenPost, 10)));
        });
        main.querySelectorAll('[data-review-post-menu]').forEach(button => {
            button.addEventListener('click', () => {
                const postId = parseInt(button.dataset.reviewPostMenu, 10);
                openModeratorModal(postId, posts.find(post => parseInt(post.id, 10) === postId));
            });
        });
        main.querySelectorAll('[data-review-resolve], [data-review-reject]').forEach(button => {
            button.addEventListener('click', async () => {
                const isResolve = button.hasAttribute('data-review-resolve');
                const reportId = button.getAttribute(isResolve ? 'data-review-resolve' : 'data-review-reject');
                const note = prompt(
                    isResolve ? 'Ghi chú xử lý report:' : 'Lý do bác bỏ report:',
                    isResolve ? 'Đã kiểm tra và xử lý nội dung vi phạm.' : 'Báo cáo không đủ cơ sở.'
                );
                if (note === null || !note.trim()) return;
                button.disabled = true;
                try {
                    await apiCall(`/api/admin/reports/${reportId}`, 'PATCH', {
                        status: isResolve ? 'resolved' : 'rejected',
                        note: note.trim(),
                    });
                    showToast(isResolve ? 'Đã xử lý report.' : 'Đã bác bỏ report.', 'success');
                    openModerationCenter(false);
                } catch (error) {
                    button.disabled = false;
                    showToast(error.message, 'error');
                }
            });
        });
    }

    // --- Moderator Quick Actions Modal ---
    function openModeratorModal(postId, post = null) {
        const isPinned = Number(post?.is_pinned) === 1;
        const isLocked = Number(post?.is_locked) === 1;
        const isHidden = post?.status === 'hidden';
        const html = `
            <div class="modal-header">
                <div class="modal-title">Công Cụ Kiểm Duyệt Bài Viết (#${postId})</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body" style="gap: 12px;">
                <button class="btn btn-secondary" id="btn-mod-pin" style="justify-content: flex-start; padding: 10px 14px;">${Icons.pin(15)} ${isPinned ? 'Bỏ ghim' : 'Ghim'} bài viết</button>
                <button class="btn btn-secondary" id="btn-mod-lock" style="justify-content: flex-start; padding: 10px 14px;">${Icons.lock(15)} ${isLocked ? 'Mở khóa' : 'Khóa'} bình luận</button>
                <button class="btn btn-secondary" id="btn-mod-hide" style="justify-content: flex-start; padding: 10px 14px;">${Icons.eye(15)} ${isHidden ? 'Khôi phục' : 'Hủy bài (ẩn khỏi diễn đàn)'}</button>
                <div class="dropdown-divider"></div>
                <button class="btn btn-secondary" id="btn-mod-edit-title" style="justify-content: flex-start; padding: 10px 14px;">${Icons.edit(15)} Đổi tiêu đề chuẩn mực</button>
                ${isAdminAccount() ? `
                <button class="btn btn-secondary" id="btn-mod-delete-perm" style="justify-content: flex-start; padding: 10px 14px; color: var(--color-danger); border-color: var(--color-danger);">${Icons.trash(15)} Xóa vĩnh viễn (Kèm tệp đính kèm)</button>
                ` : ''}
            </div>
        `;

        openModal(html);

        const applyAction = async (actionName, data = {}) => {
            try {
                await apiCall(`/api/admin/posts/${postId}/moderate`, 'PATCH', { action: actionName, ...data });
                showToast('Đã thực hiện thao tác kiểm duyệt.', 'success');
                closeModal();
                if (state.activeView === 'moderation') {
                    openModerationCenter(false);
                } else if (state.activeView === 'post-detail') {
                    openPostDetail(postId, false);
                } else {
                    loadFeed(state.feedPage);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        };

        document.getElementById('btn-mod-pin')?.addEventListener('click', () => applyAction(isPinned ? 'unpin_post' : 'pin_post'));
        document.getElementById('btn-mod-lock')?.addEventListener('click', () => applyAction(isLocked ? 'unlock_post' : 'lock_post'));
        document.getElementById('btn-mod-hide')?.addEventListener('click', () => {
            if (isHidden) {
                applyAction('restore_post');
                return;
            }
            const reason = prompt('Nhập lý do hủy bài. Lý do này sẽ được thông báo cho tác giả:');
            if (reason === null) return;
            if (!reason.trim()) {
                showToast('Bạn cần nhập lý do trước khi hủy bài.', 'error');
                return;
            }
            applyAction('hide_post', { reason: reason.trim() });
        });

        document.getElementById('btn-mod-edit-title')?.addEventListener('click', async () => {
            const newTitle = prompt('Nhập tiêu đề mới cho bài viết:');
            if (!newTitle) return;
            try {
                await apiCall(`/api/admin/posts/${postId}/title`, 'PATCH', { title: newTitle });
                showToast('Đã cập nhật tiêu đề bài viết.', 'success');
                closeModal();
                if (state.activeView === 'moderation') {
                    openModerationCenter(false);
                } else {
                    loadFeed(state.feedPage);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        document.getElementById('btn-mod-delete-perm')?.addEventListener('click', async () => {
            if (!confirm('CẢNH BÁO: Thao tác này sẽ xóa vĩnh viễn bài viết và toàn bộ ảnh khỏi hệ thống máy chủ. Tiếp tục?')) return;
            try {
                await apiCall(`/api/admin/posts/${postId}/permanent`, 'DELETE');
                showToast('Đã xóa vĩnh viễn bài viết.', 'success');
                closeModal();
                state.activeView = 'feed';
                window.history.pushState({}, '', `${config.baseUrl}/`);
                loadFeed(1);
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Bookmarks Modal ---
    async function openBookmarksModal() {
        openModal(`
            <div class="modal-header">
                <div class="modal-title">Bài Viết Đã Lưu</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body" id="bookmarks-modal-content">
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
            </div>
        `, 'modal-lg');

        try {
            const res = await apiCall('/api/profile/bookmarks');
            const list = res?.data || [];
            const container = document.getElementById('bookmarks-modal-content');
            if (!container) return;

            if (list.length === 0) {
                container.innerHTML = `
                    <div class="empty-state-box">
                        <div class="empty-icon">${Icons.bookmark(32)}</div>
                        <div class="empty-title">Chưa có bài viết nào được lưu</div>
                        <div class="empty-desc">Nhấn vào nút "Lưu" dưới bất kỳ bài viết nào để lưu lại xem sau.</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = list.map(item => `
                <div style="padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-md); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; background: var(--surface-subtle);">
                    <div>
                        <h4 style="font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text-primary);" data-action="open-detail" data-post-id="${item.id}">
                            ${escapeHtml(item.title)}
                        </h4>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            ${Icons.folder(12)} d/${escapeHtml(item.category_name || 'Chung')} • u/${escapeHtml(item.username)}
                        </div>
                    </div>
                    <button class="btn btn-secondary btn-sm" data-action="open-detail" data-post-id="${item.id}">Xem</button>
                </div>
            `).join('');

            container.querySelectorAll('[data-action="open-detail"]').forEach(el => {
                el.addEventListener('click', () => {
                    const id = el.getAttribute('data-post-id');
                    closeModal();
                    openPostDetail(parseInt(id, 10));
                });
            });
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- My Posts Modal ---
    async function openMyPostsModal() {
        openModal(`
            <div class="modal-header">
                <div class="modal-title">Bài Viết Của Tôi</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body" id="myposts-modal-content">
                <div class="skeleton skeleton-card"></div>
                <div class="skeleton skeleton-card"></div>
            </div>
        `, 'modal-lg');

        try {
            const res = await apiCall('/api/profile/posts');
            const list = res?.data || [];
            const container = document.getElementById('myposts-modal-content');
            if (!container) return;

            if (list.length === 0) {
                container.innerHTML = `
                    <div class="empty-state-box">
                        <div class="empty-icon">${Icons.fileText(32)}</div>
                        <div class="empty-title">Bạn chưa đăng bài viết nào</div>
                        <div class="empty-desc">Hãy bắt đầu chia sẻ câu hỏi hoặc kiến thức với cộng đồng!</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = list.map(item => `
                <div style="padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-md); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; background: var(--surface-subtle);">
                    <div>
                        <h4 style="font-size: 15px; font-weight: 600; cursor: pointer; color: var(--text-primary);" data-action="open-detail" data-post-id="${item.id}">
                            ${escapeHtml(item.title)}
                        </h4>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            ${item.status === 'published' ? `${Icons.dot(8, 'var(--color-success)')} Công khai` : `${Icons.dot(8, 'var(--color-warning)')} Bản nháp`} • ${timeAgo(item.created_at)}
                        </div>
                    </div>
                    <button class="btn btn-secondary btn-sm" data-action="open-detail" data-post-id="${item.id}">Xem</button>
                </div>
            `).join('');

            container.querySelectorAll('[data-action="open-detail"]').forEach(el => {
                el.addEventListener('click', () => {
                    const id = el.getAttribute('data-post-id');
                    closeModal();
                    openPostDetail(parseInt(id, 10));
                });
            });
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // --- Avatar Upload / Random Generator ---
    async function uploadAvatarFile(file) {
        const formData = new FormData();
        formData.append('avatar', file);
        try {
            await refreshCsrf();
            const res = await apiCall('/api/profile/avatar', 'POST', formData, true);
            state.currentUser.avatar_path = res.path;
            updateNavUserArea();
            const preview = document.getElementById('profile-avatar-preview');
            if (preview) preview.innerHTML = `<img src="${config.baseUrl}${res.path}">`;
            showToast('Đã cập nhật ảnh đại diện!', 'success');
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    const AVATAR_COLORS = ['#2E5EEA', '#17C3B2', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#10B981', '#0EA5E9', '#F97316', '#6366F1'];

    function generateRandomAvatarBlob(nameForInitials) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 256;
        const ctx = canvas.getContext('2d');
        const color = AVATAR_COLORS[Math.floor(Math.random() * AVATAR_COLORS.length)];
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, 256, 256);
        const initials = (nameForInitials || '?').trim().substring(0, 2).toUpperCase();
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 110px -apple-system, Segoe UI, Roboto, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(initials, 128, 138);
        return new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    }

    // --- Profile Modal ---
    function openProfileModal() {
        if (!state.currentUser) return;
        const u = state.currentUser;

        const html = `
            <div class="modal-header">
                <div class="modal-title">Hồ Sơ Cá Nhân</div>
                <button class="modal-close-btn" data-modal-close aria-label="Đóng">${Icons.x(16)}</button>
            </div>
            <div class="modal-body" style="gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="position: relative;">
                        <button type="button" id="btn-toggle-avatar-menu" class="user-avatar" style="width: 64px; height: 64px; font-size: 24px; cursor: pointer; padding: 0; border: none;" title="Đổi ảnh đại diện">
                            <span id="profile-avatar-preview">${u.avatar_path ? `<img src="${config.baseUrl}${u.avatar_path}">` : u.username.substring(0, 2).toUpperCase()}</span>
                        </button>
                        <div style="position: absolute; bottom: -2px; right: -2px; width: 20px; height: 20px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; pointer-events: none; border: 2px solid var(--surface);">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                        </div>
                        <input type="file" id="profile-avatar-input" accept="image/jpeg,image/png,image/webp" style="display: none;">
                        <div class="dropdown-menu" id="avatar-options-menu" style="top: calc(100% + 6px); left: 0; right: auto; width: 160px;">
                            <button type="button" class="dropdown-item" id="btn-upload-avatar">Tải ảnh lên</button>
                            <button type="button" class="dropdown-item" id="btn-random-avatar">Ngẫu nhiên</button>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary);">${escapeHtml(u.display_name)}</h3>
                        <div style="font-size: 13px; color: var(--text-muted);">u/${escapeHtml(u.username)} • ${escapeHtml(u.email || '')}</div>
                        <div style="font-size: 13px; color: #F59E0B; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 4px;">${Icons.star(13)} ${(u.post_karma || 0) + (u.comment_karma || 0)} Karma</div>
                    </div>
                </div>

                <div class="dropdown-divider"></div>

                <div class="form-group">
                    <label class="form-label">Tên hiển thị</label>
                    <input type="text" class="form-input" id="profile-display-name" value="${escapeHtml(u.display_name)}">
                </div>

                <div class="form-group">
                    <label class="form-label">Tiểu sử (Bio)</label>
                    <textarea class="form-textarea" id="profile-bio" rows="3" placeholder="Giới thiệu đôi nét về bản thân, kỹ năng công nghệ...">${escapeHtml(u.bio || '')}</textarea>
                </div>

                <button class="btn btn-primary" id="btn-save-profile">Lưu thông tin</button>

                <div class="dropdown-divider"></div>

                <h4 style="font-size: 14px; font-weight: 700;">Đổi mật khẩu</h4>
                <div class="form-group">
                    <input type="password" class="form-input" id="pwd-current" placeholder="Mật khẩu hiện tại">
                </div>
                <div class="form-group">
                    <input type="password" class="form-input" id="pwd-new" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)">
                </div>
                <button class="btn btn-secondary" id="btn-change-password">Đổi mật khẩu</button>
            </div>
        `;

        openModal(html);

        // Avatar Menu Toggle (only shows the upload/random options when the avatar itself is clicked)
        const avatarMenu = document.getElementById('avatar-options-menu');
        document.getElementById('btn-toggle-avatar-menu')?.addEventListener('click', (e) => {
            e.stopPropagation();
            avatarMenu?.classList.toggle('active');
        });
        document.getElementById('active-modal-overlay')?.addEventListener('click', (e) => {
            if (avatarMenu?.classList.contains('active') && !e.target.closest('#btn-toggle-avatar-menu') && !e.target.closest('#avatar-options-menu')) {
                avatarMenu.classList.remove('active');
            }
        });

        // Avatar Upload
        const avatarInput = document.getElementById('profile-avatar-input');
        document.getElementById('btn-upload-avatar')?.addEventListener('click', () => {
            avatarMenu?.classList.remove('active');
            avatarInput?.click();
        });
        avatarInput?.addEventListener('change', async () => {
            const file = avatarInput.files?.[0];
            if (file) await uploadAvatarFile(file);
        });

        // Random Avatar (client-side generated, no upload needed for the picker itself)
        document.getElementById('btn-random-avatar')?.addEventListener('click', async () => {
            avatarMenu?.classList.remove('active');
            const blob = await generateRandomAvatarBlob(u.display_name || u.username);
            const file = new File([blob], 'avatar.png', { type: 'image/png' });
            await uploadAvatarFile(file);
        });

        // Save Profile
        document.getElementById('btn-save-profile')?.addEventListener('click', async () => {
            const display_name = document.getElementById('profile-display-name')?.value.trim();
            const bio = document.getElementById('profile-bio')?.value.trim();
            try {
                await apiCall('/api/profile', 'PUT', { display_name, bio });
                showToast('Đã cập nhật hồ sơ cá nhân!', 'success');
                const meRes = await apiCall('/api/me');
                state.currentUser = meRes.user;
                updateNavUserArea();
                closeModal();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        // Change Password
        document.getElementById('btn-change-password')?.addEventListener('click', async () => {
            const current_password = document.getElementById('pwd-current')?.value;
            const new_password = document.getElementById('pwd-new')?.value;
            if (!current_password || !new_password) {
                showToast('Vui lòng nhập đầy đủ mật khẩu hiện tại và mật khẩu mới.', 'error');
                return;
            }
            try {
                await apiCall('/api/profile/password', 'PUT', { current_password, new_password });
                showToast('Đã đổi mật khẩu thành công!', 'success');
                closeModal();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });
    }

    // --- Notifications ---
    async function loadNotifications() {
        if (!state.currentUser) return;
        try {
            const res = await apiCall('/api/notifications');
            state.notifications = res?.data || [];
            state.unreadNotificationsCount = state.notifications.filter(n => !n.is_read).length;
            updateNavUserArea();
        } catch (e) {
            // silent fail for polling
        }
    }

    function renderNotificationsDropdown() {
        const dropdown = document.getElementById('notifications-dropdown');
        if (!dropdown) return;

        if (state.notifications.length === 0) {
            dropdown.innerHTML = `
                <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                    Chưa có thông báo nào.
                </div>
            `;
            return;
        }

        dropdown.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid var(--border-subtle);">
                <span style="font-weight: 700; font-size: 13px;">Thông báo (${state.unreadNotificationsCount} mới)</span>
                ${state.unreadNotificationsCount > 0 ? `<button class="btn btn-ghost" id="btn-read-all-notifs" style="font-size: 11px;">Đọc tất cả</button>` : ''}
            </div>
            <div style="max-height: 280px; overflow-y: auto;">
                ${state.notifications.map(n => `
                    <div class="notif-item" style="padding: 8px 12px; border-bottom: 1px solid var(--border-subtle); font-size: 12.5px; cursor: pointer; ${n.is_read ? 'opacity: 0.7;' : 'background: var(--surface-selected);'}" data-notif-id="${n.id}" data-post-id="${n.post_id || ''}">
                        <div>${escapeHtml(n.message)}</div>
                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">${timeAgo(n.created_at)}</div>
                    </div>
                `).join('')}
            </div>
        `;

        document.getElementById('btn-read-all-notifs')?.addEventListener('click', async () => {
            try {
                await apiCall('/api/notifications/read-all', 'PATCH');
                state.unreadNotificationsCount = 0;
                state.notifications.forEach(n => n.is_read = 1);
                renderNotificationsDropdown();
                updateNavUserArea();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        dropdown.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', async () => {
                const notifId = parseInt(item.getAttribute('data-notif-id'), 10);
                const postId = item.getAttribute('data-post-id');
                const notif = state.notifications.find(n => n.id === notifId);
                if (notif && !notif.is_read) {
                    try {
                        await apiCall(`/api/notifications/${notifId}/read`, 'PATCH');
                        notif.is_read = 1;
                        state.unreadNotificationsCount = Math.max(0, state.unreadNotificationsCount - 1);
                        updateNavUserArea();
                    } catch (err) {
                        // silent fail, still navigate
                    }
                }
                document.getElementById('notifications-dropdown')?.classList.remove('active');
                if (postId) {
                    openPostDetail(postId);
                }
            });
        });
    }

    // Expose reload helper
    window.devai = {
        reloadFeed: () => loadFeed(1),
        openPostDetail,
    };

    // Run on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
