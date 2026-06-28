(function () {
    var KEY = 'hp-admin-theme';

    function getTheme() {
        var stored = localStorage.getItem(KEY);
        return stored === 'light' ? 'light' : 'dark';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(KEY, theme);
    }

    function toggleTheme() {
        setTheme(getTheme() === 'dark' ? 'light' : 'dark');
    }

    function initTheme() {
        setTheme(getTheme());
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', toggleTheme);
        });
    }

    function closeSidebar() {
        var sidebar = document.querySelector('.admin-sidebar');
        var overlay = document.querySelector('.admin-overlay');
        if (sidebar) sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-visible');
        document.body.classList.remove('admin-nav-open');
    }

    function openSidebar() {
        var sidebar = document.querySelector('.admin-sidebar');
        var overlay = document.querySelector('.admin-overlay');
        if (sidebar) sidebar.classList.add('is-open');
        if (overlay) overlay.classList.add('is-visible');
        document.body.classList.add('admin-nav-open');
    }

    function initSidebar() {
        var menuBtn = document.querySelector('[data-menu-toggle]');
        var overlay = document.querySelector('.admin-overlay');
        var sidebar = document.querySelector('.admin-sidebar');

        if (menuBtn) {
            menuBtn.addEventListener('click', function () {
                if (sidebar && sidebar.classList.contains('is-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        if (sidebar) {
            sidebar.querySelectorAll('.admin-nav-link, .admin-btn-primary').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 900) {
                        closeSidebar();
                    }
                });
            });
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                closeSidebar();
            }
        });
    }

    if (document.documentElement.getAttribute('data-theme') === null) {
        setTheme('dark');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initTheme();
            initSidebar();
        });
    } else {
        initTheme();
        initSidebar();
    }
})();
