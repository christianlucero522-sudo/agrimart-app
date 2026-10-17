/**
 * AgriMart Universal Mobile Navigation & Responsive Helper (responsive.js)
 * Ensures smooth, native-like mobile responsiveness across all devices and phones.
 */

(function() {
    function initAgriResponsive() {
        initMobileHeaderNav();
        initDashboardMobile();
        wrapResponsiveTables();
    }

    // 1. Mobile Header & Drawer Navigation
    function initMobileHeaderNav() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        const wrap = header.querySelector('.wrap');
        if (!wrap) return;

        const mainNav = header.querySelector('.main-nav');
        if (!mainNav) return;

        // Create hamburger button if not present
        let toggleBtn = header.querySelector('.agri-nav-toggle');
        if (!toggleBtn) {
            toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'agri-nav-toggle';
            toggleBtn.setAttribute('aria-label', 'Toggle navigation menu');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.innerHTML = '<span></span><span></span><span></span>';

            const headerActions = header.querySelector('.header-actions');
            if (headerActions) {
                headerActions.appendChild(toggleBtn);
            } else {
                wrap.appendChild(toggleBtn);
            }
        }

        // Clone User Info & Action Buttons into Mobile Drawer
        const headerActions = header.querySelector('.header-actions');
        if (headerActions && !mainNav.querySelector('.mobile-user-row') && !mainNav.querySelector('.mobile-actions-row')) {
            const userChip = headerActions.querySelector('.user-chip');
            const actionBtns = headerActions.querySelectorAll('a.btn');

            if (userChip) {
                const userRow = document.createElement('div');
                userRow.className = 'mobile-user-row';
                userRow.innerHTML = userChip.innerHTML;
                mainNav.prepend(userRow);
            }

            if (actionBtns && actionBtns.length > 0) {
                const actRow = document.createElement('div');
                actRow.className = 'mobile-actions-row';
                actionBtns.forEach(btn => {
                    const clone = btn.cloneNode(true);
                    clone.style.display = 'inline-flex';
                    actRow.appendChild(clone);
                });
                mainNav.appendChild(actRow);
            }
        }

        // Toggle Drawer
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = mainNav.classList.toggle('is-open');
            toggleBtn.classList.toggle('is-active', isOpen);
            toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                document.body.classList.add('mobile-nav-locked');
            } else {
                document.body.classList.remove('mobile-nav-locked');
            }
        });

        // Close on link click
        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mainNav.classList.remove('is-open');
                toggleBtn.classList.remove('is-active');
                toggleBtn.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('mobile-nav-locked');
            });
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (mainNav.classList.contains('is-open') && !header.contains(e.target)) {
                mainNav.classList.remove('is-open');
                toggleBtn.classList.remove('is-active');
                toggleBtn.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('mobile-nav-locked');
            }
        });
    }

    // 2. Dashboard Sidebar Touch Backdrop
    function initDashboardMobile() {
        const dashToggle = document.querySelector('.dash-menu-toggle');
        const sidebar = document.querySelector('.dash-sidebar');
        if (!dashToggle || !sidebar) return;

        let backdrop = document.querySelector('.dash-sidebar-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'dash-sidebar-backdrop';
            backdrop.style.cssText = 'display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1999; -webkit-backdrop-filter:blur(2px); backdrop-filter:blur(2px);';
            document.body.appendChild(backdrop);
        }

        dashToggle.addEventListener('click', function() {
            const isOpen = document.body.classList.toggle('sidebar-open');
            backdrop.style.display = isOpen ? 'block' : 'none';
        });

        backdrop.addEventListener('click', function() {
            document.body.classList.remove('sidebar-open');
            backdrop.style.display = 'none';
        });

        // Close sidebar on navigation on mobile
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 900) {
                    document.body.classList.remove('sidebar-open');
                    backdrop.style.display = 'none';
                }
            });
        });
    }

    // 3. Fluid Responsive Table Scroll Wrappers
    function wrapResponsiveTables() {
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            if (!table.closest('.table-responsive, .table-wrap, .dash-table-wrap, .table-container')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAgriResponsive);
    } else {
        initAgriResponsive();
    }
})();
