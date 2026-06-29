<style>
    :root { --sidebar-w: 220px; --header-h: 56px; }
    body { background: #f4f6fb; }
    .admin-header {
        position: fixed; top: 0; left: 0; right: 0; height: var(--header-h);
        background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
        color: white; z-index: 1000;
        display: flex; align-items: center; padding: 0 1rem; gap: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .admin-sidebar {
        position: fixed; top: var(--header-h); left: 0; bottom: 0;
        width: var(--sidebar-w); background: #1e2d3d;
        padding-top: .5rem; overflow-y: auto; z-index: 900;
    }
    .admin-sidebar .nav-link {
        color: #a8c0d6; padding: .6rem 1.25rem;
        border-radius: 6px; margin: 1px 8px;
        display: flex; align-items: center; gap: .6rem; font-size: .875rem;
        transition: background .15s, color .15s;
    }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active {
        background: rgba(255,255,255,.1); color: #fff;
    }
    .admin-sidebar .nav-link i { font-size: 1.1rem; }
    .admin-sidebar .sidebar-section {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .8px;
        color: #4a6a8a; padding: .75rem 1.25rem .25rem; font-weight: 700;
    }
    .admin-content {
        margin-left: var(--sidebar-w);
        margin-top: var(--header-h);
        min-height: calc(100vh - var(--header-h));
    }
    .stat-card { border-radius: 12px; }
    .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .stat-num { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .stat-label { font-size: .8rem; color: #888; margin-top: 2px; }
    .quick-action { border-radius: 12px; transition: box-shadow .15s; color: inherit; }
    .quick-action:hover { box-shadow: 0 6px 20px rgba(0,0,0,.12) !important; }
    .table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; color: #888; font-weight: 600; }
    .btn-icon { width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; }
</style>
