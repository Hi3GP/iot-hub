{{-- 管理面板主题：浅蓝门户风（浅蓝渐变底 / 天蓝主色 / 白卡柔影 / 轻盈激活态） --}}

<style>
    :root {
        --brand: #3d8bff;
        --brand-dark: #2b76f0;
        --brand-light: #6fb0ff;
        --brand-soft: #eaf3ff;
        --brand-softer: #f4f8ff;
        --coral: #ff6b6b;
        --coral-soft: #fff0f0;
        --emerald: #22c583;
        --emerald-soft: #e7f8f0;
        --amber: #ffa940;
        --amber-soft: #fff5e8;
        --violet: #8b7cf6;
        --violet-soft: #f0edff;
        --cyan: #38bdf8;
        --cyan-soft: #e8f7fe;

        --page-bg: #f4f8ff;
        --card-bg: #ffffff;
        --line: #ebf0f7;
        --line-soft: #f2f5fa;

        --ink: #1f2a44;
        --ink-2: #55607a;
        --ink-3: #97a1b5;

        --radius-card: 14px;
        --shadow-card: 0 6px 20px -10px rgba(56, 110, 200, .14);
        --shadow-card-hover: 0 12px 28px -12px rgba(56, 110, 200, .22);
        --shadow-blue: 0 6px 16px -6px rgba(61, 139, 255, .45);
    }

    /* ============ 全局底色：顶部浅蓝渐变 ============ */
    .fi-body {
        background:
            linear-gradient(180deg, #d7e7ff 0%, #e8f1ff 200px, var(--page-bg) 420px);
        background-attachment: fixed;
        color: var(--ink-2);
    }

    .fi-body h1,
    .fi-body .fi-header-heading,
    .fi-body .fi-section-heading {
        color: var(--ink);
        letter-spacing: -0.01em;
        font-weight: 700;
    }

    /* ============ 顶栏：白色半透 ============ */
    .fi-body .fi-topbar {
        background: rgba(255, 255, 255, .85);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid var(--line);
    }

    .fi-body .fi-topbar .fi-input-wrp {
        background: #f3f7fd;
        border-color: transparent !important;
    }

    /* ============ 侧边栏 ============ */
    .fi-body .fi-sidebar {
        background: rgba(255, 255, 255, .92);
        border-right: 1px solid var(--line);
    }

    .fi-body .fi-sidebar .fi-sidebar-group-btn {
        padding-block: 0.3rem;
    }

    /* 菜单紧凑化：缩小 nav 上下留白、组间距与项间距（覆盖默认 py-8 / gap-y-7） */
    .fi-body .fi-sidebar .fi-sidebar-nav {
        padding-block: 1rem;
        row-gap: 0.75rem;
    }

    .fi-body .fi-sidebar .fi-sidebar-nav-groups {
        row-gap: 0.25rem;
    }

    .fi-body .fi-sidebar .fi-sidebar-group,
    .fi-body .fi-sidebar .fi-sidebar-group-items {
        row-gap: 2px;
    }

    .fi-body .fi-sidebar .fi-sidebar-group-btn .fi-sidebar-group-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        color: #b3bcd0;
        text-transform: uppercase;
    }

    .fi-body .fi-sidebar .fi-sidebar-group + .fi-sidebar-group {
        border-top: 1px solid var(--line-soft);
        margin-top: 0.5rem;
        padding-top: 0.5rem;
    }

    .fi-body .fi-sidebar .fi-sidebar-item-btn {
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.86rem;
        padding-block: 0.42rem;
        margin-inline: 0.35rem;
        color: var(--ink-2);
        transition: all .15s ease;
    }

    .fi-body .fi-sidebar .fi-sidebar-item-btn svg {
        color: var(--ink-3);
        transition: color .15s ease;
    }

    .fi-body .fi-sidebar .fi-sidebar-item-btn:hover {
        background: var(--brand-softer);
        color: var(--brand);
    }

    .fi-body .fi-sidebar .fi-sidebar-item-btn:hover svg {
        color: var(--brand);
    }

    /* 激活项：浅蓝底 + 蓝字（轻盈） */
    .fi-body .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: var(--brand-soft);
        color: var(--brand-dark) !important;
        font-weight: 600;
        box-shadow: none;
    }

    .fi-body .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn svg,
    .fi-body .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label {
        color: var(--brand) !important;
    }

    .fi-body .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-badge {
        background: var(--brand) !important;
        color: #fff !important;
    }

    /* ============ 侧栏滚动条：隐藏滑块（仍可滚动） ============ */
    .fi-body .fi-sidebar,
    .fi-body .fi-sidebar * {
        scrollbar-width: none;
    }

    .fi-body .fi-sidebar::-webkit-scrollbar,
    .fi-body .fi-sidebar *::-webkit-scrollbar {
        display: none;
    }

    /* ============ 卡片 / 组件容器 ============ */
    .fi-body .fi-wi-widget,
    .fi-body .fi-ta,
    .fi-body .fi-section {
        background: var(--card-bg);
        border: 1px solid var(--line);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
    }

    .fi-body .fi-wi-widget .fi-widget-header-heading,
    .fi-body .fi-wi-widget h3,
    .fi-body .fi-ta-header-heading {
        color: var(--ink) !important;
        font-weight: 700 !important;
        font-size: 0.95rem !important;
    }

    /* ============ 表格 ============ */
    .fi-body .fi-ta {
        overflow: hidden;
    }

    .fi-body .fi-ta-table thead .fi-ta-header-cell {
        background: #f7faff;
        color: var(--ink-3);
        font-size: 0.76rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        border-bottom: 1px solid var(--line-soft);
        padding-block: 0.7rem;
    }

    .fi-body .fi-ta-table tbody td.fi-ta-cell {
        border-bottom: 1px solid var(--line-soft);
        font-size: 0.86rem;
        padding-block: 0.75rem;
    }

    .fi-body .fi-ta-table tbody tr:last-child td.fi-ta-cell {
        border-bottom: none;
    }

    .fi-body .fi-ta-table tbody tr:hover > td.fi-ta-cell {
        background: #f8fbff;
    }

    /* ============ 主按钮：天蓝 ============ */
    .fi-body .fi-btn.fi-color-primary[class*="fi-bg-color"],
    .fi-simple .fi-btn.fi-color-primary[class*="fi-bg-color"] {
        background: linear-gradient(135deg, var(--brand-light), var(--brand)) !important;
        border-color: transparent !important;
        color: #fff !important;
        box-shadow: var(--shadow-blue) !important;
        font-weight: 600;
    }

    .fi-body .fi-btn.fi-color-primary[class*="fi-bg-color"]:hover,
    .fi-body .fi-btn.fi-color-primary[class*="fi-bg-color"]:focus,
    .fi-simple .fi-btn.fi-color-primary[class*="fi-bg-color"]:hover,
    .fi-simple .fi-btn.fi-color-primary[class*="fi-bg-color"]:focus {
        background: linear-gradient(135deg, var(--brand), var(--brand-dark)) !important;
        color: #fff !important;
        box-shadow: 0 8px 20px -6px rgba(61, 139, 255, .55) !important;
    }

    .fi-body .fi-btn.fi-color-gray[class*="fi-bg-color"] {
        background: #fff !important;
        border-color: var(--line) !important;
        color: var(--ink-2) !important;
    }

    .fi-body .fi-btn.fi-color-gray[class*="fi-bg-color"]:hover {
        background: var(--brand-softer) !important;
        color: var(--brand) !important;
        border-color: #cfdff7 !important;
    }

    /* ============ 开关：开启态天蓝 ============ */
    .fi-body .fi-toggle-on[class*="fi-bg-color"],
    .fi-simple .fi-toggle-on[class*="fi-bg-color"] {
        background-color: var(--brand) !important;
        border-color: var(--brand) !important;
    }

    /* ============ 标签页 / 分段控件 ============ */
    .fi-body .fi-tabs-item {
        border-radius: 10px;
    }

    .fi-body .fi-tabs-item.fi-active {
        background-color: var(--brand) !important;
        color: #fff !important;
        box-shadow: var(--shadow-blue);
    }

    .fi-body .fi-tabs-item.fi-active svg {
        color: #fff !important;
    }

    /* ============ 徽章：低饱和柔和底 ============ */
    .fi-body .fi-badge.fi-color-primary {
        background: var(--brand-soft) !important;
        color: var(--brand-dark) !important;
        font-weight: 600;
    }

    .fi-body .fi-badge.fi-color-success {
        background: var(--emerald-soft) !important;
        color: #12a15f !important;
        font-weight: 600;
    }

    .fi-body .fi-badge.fi-color-danger {
        background: var(--coral-soft) !important;
        color: #e84a4a !important;
        font-weight: 600;
    }

    .fi-body .fi-badge.fi-color-warning {
        background: var(--amber-soft) !important;
        color: #d9820b !important;
        font-weight: 600;
    }

    /* ============ 输入框 ============ */
    .fi-body .fi-input-wrp,
    .fi-simple .fi-input-wrp {
        border: 1px solid #c5d0e0 !important;
        border-radius: 10px;
        background: #f9fbff;
        box-shadow: none !important;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .fi-body .fi-input-wrp:focus-within,
    .fi-simple .fi-input-wrp:focus-within {
        border-color: var(--brand) !important;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(61, 139, 255, .14) !important;
    }

    /* ============ 品牌 Logo ============ */
    .fi-body .fi-logo {
        height: 2.2rem;
        width: auto;
    }

    .fi-body .fi-logo img,
    .fi-body .fi-logo svg {
        height: 2.2rem;
        width: auto;
    }

    /* ============ 登录页 ============ */
    .fi-simple-main {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: 0 24px 60px -24px rgba(56, 110, 200, .25) !important;
        /* 整体宽度缩小 20%（原 fi-width-lg 32rem → 25.6rem） */
        width: 25.6rem !important;
        max-width: 100%;
    }

    /* 登录页底部版权：强制居中（Filament grid 上下文会覆盖 .text-center） */
    .fi-auth-footer {
        text-align: center !important;
    }

    /* ============ 下拉 / 弹窗 ============ */
    .fi-body .fi-dropdown-panel,
    .fi-body .fi-modal-panel,
    .fi-body [role="dialog"] {
        border: 1px solid var(--line);
        box-shadow: 0 20px 48px -20px rgba(56, 110, 200, .25) !important;
        border-radius: 14px;
    }

    /* ==================================================================
       仪表板组件
       ================================================================== */

    .fi-wi-widget.dash-plain {
        background: transparent;
        border: none;
        box-shadow: none;
    }

    /* ============ 欢迎横幅 ============ */
    .wb-banner {
        position: relative;
        overflow: hidden;
        border-radius: var(--radius-card);
        padding: 26px 30px;
        color: #fff;
        background: linear-gradient(120deg, #5ea8ff 0%, var(--brand) 45%, var(--brand-dark) 100%);
        box-shadow: 0 14px 34px -14px rgba(61, 139, 255, .65);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .wb-banner::before,
    .wb-banner::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .1);
        pointer-events: none;
    }

    .wb-banner::before {
        width: 220px;
        height: 220px;
        right: -70px;
        top: -110px;
    }

    .wb-banner::after {
        width: 140px;
        height: 140px;
        right: 180px;
        bottom: -90px;
        background: rgba(255, 255, 255, .08);
    }

    .wb-text {
        position: relative;
        z-index: 1;
    }

    .wb-hello {
        font-size: 1.3rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        margin: 0 0 6px;
    }

    .wb-sub {
        font-size: 0.84rem;
        color: rgba(255, 255, 255, .88);
        margin: 0;
    }

    .wb-actions {
        position: relative;
        z-index: 1;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .wb-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 0.84rem;
        font-weight: 600;
        text-decoration: none;
        transition: all .15s ease;
    }

    .wb-btn svg {
        width: 16px;
        height: 16px;
    }

    .wb-btn-solid {
        background: #fff;
        color: var(--brand-dark);
    }

    .wb-btn-solid:hover {
        background: #f2f7ff;
        transform: translateY(-1px);
    }

    .wb-btn-ghost {
        background: rgba(255, 255, 255, .16);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .35);
    }

    .wb-btn-ghost:hover {
        background: rgba(255, 255, 255, .26);
    }

    /* ============ 快速统计行 ============ */
    .qs-card {
        background: var(--card-bg);
        border: 1px solid var(--line);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-card);
        padding: 8px 10px;
        display: flex;
    }

    .qs-item {
        flex: 1 1 0;
        min-width: 130px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        position: relative;
        text-decoration: none;
        border-radius: 10px;
        transition: background .15s ease;
    }

    a.qs-item:hover {
        background: var(--brand-softer);
    }

    .qs-item + .qs-item::before {
        content: "";
        position: absolute;
        left: 0;
        top: 22%;
        height: 56%;
        width: 1px;
        background: var(--line);
    }

    .qs-icon {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .qs-icon svg {
        width: 22px;
        height: 22px;
    }

    .qs-icon-blue { background: var(--brand-soft); color: var(--brand); }
    .qs-icon-emerald { background: var(--emerald-soft); color: var(--emerald); }
    .qs-icon-gray { background: #f1f3f7; color: #8a94a8; }
    .qs-icon-coral { background: var(--coral-soft); color: var(--coral); }
    .qs-icon-amber { background: var(--amber-soft); color: var(--amber); }
    .qs-icon-violet { background: var(--violet-soft); color: var(--violet); }

    .qs-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
    }

    .qs-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: var(--ink);
        letter-spacing: -0.02em;
    }

    .qs-label {
        font-size: 0.78rem;
        color: var(--ink-3);
        margin-top: 3px;
    }

    /* ============ 快捷入口网格 ============ */
    .ql-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .ql-item {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--card-bg);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px 14px;
        text-decoration: none;
        color: var(--ink);
        font-size: 0.86rem;
        font-weight: 600;
        transition: all .15s ease;
    }

    .ql-item:hover {
        border-color: #c4d9fb;
        box-shadow: var(--shadow-card-hover);
        transform: translateY(-2px);
        color: var(--brand-dark);
    }

    .ql-item svg {
        width: 20px;
        height: 20px;
        color: var(--brand);
        flex-shrink: 0;
    }

    /* 面板标题（公告/快捷入口卡片头） */
    .dash-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .dash-panel-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--ink);
    }

    .dash-panel-more {
        font-size: 0.78rem;
        color: var(--brand);
        text-decoration: none;
        font-weight: 500;
    }

    .dash-panel-more:hover {
        color: var(--brand-dark);
    }

    /* ---- FilePond 上传预览浅色化：白底卡片 + 居中大图预览 ---- */
    .fi-body .filepond--root,
    .fi-body .filepond--root .filepond--panel-root {
        background: transparent;
    }

    .fi-body .filepond--item-panel {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .fi-body .filepond--file {
        color: var(--ink-2);
    }

    .fi-body .filepond--file-wrapper > .filepond--file-info,
    .fi-body .filepond--file-status {
        color: var(--ink-2);
    }

    .fi-body .filepond--image-preview-wrapper {
        background:
            conic-gradient(#f8fafc 0 25%, #fff 0 50%, #f8fafc 0 75%, #fff 0) 0 0 / 16px 16px;
    }

    .fi-body .filepond--image-preview {
        background: transparent;
    }

    /* 预览图片完整显示不裁切 */
    .fi-body .filepond--image-preview picture img {
        object-fit: contain;
    }

    /* 操作按钮（删除/打开等）浅色化 */
    .fi-body .filepond--action {
        background: rgba(255, 255, 255, .9);
        color: #64748b;
        border-radius: 8px;
    }

    .fi-body .filepond--action:hover {
        background: #fff;
        color: var(--ink);
    }
</style>
