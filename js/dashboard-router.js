/**
 * AgriMart Dashboard Single-Page Application (SPA) Router
 * Provides persistent navbar, fixed independent sidebar scrolling,
 * seamless client-side content swapping, form processing, and toast notifications.
 */

(function () {
    'use strict';

    const OUTLET_ID = 'dashContentOutlet';
    const PROGRESS_BAR_ID = 'dashProgressBar';
    const SIDEBAR_ID = 'dashSidebar';

    let outlet = null;
    let progressBar = null;
    let isNavigating = false;

    // Initialize SPA Router on page load
    document.addEventListener('DOMContentLoaded', function () {
        outlet = document.getElementById(OUTLET_ID);
        progressBar = document.getElementById(PROGRESS_BAR_ID);

        if (!outlet) return;

        // 1. Intercept Link Clicks globally across the application
        document.addEventListener('click', handleGlobalClick);

        // 2. Intercept Form Submissions inside the outlet
        document.addEventListener('submit', handleGlobalSubmit);

        // 3. Handle Browser Back & Forward History
        window.addEventListener('popstate', function (event) {
            const url = window.location.href;
            loadPage(url, false);
        });

        // 4. Highlight initial active sidebar link
        updateActiveSidebar(window.location.href);

        // 5. Initialize inner interactive elements on first load
        initPageScripts(outlet);
    });

    /**
     * Intercepts link clicks
     */
    function handleGlobalClick(e) {
        const link = e.target.closest('a');
        if (!link) return;

        const rawHref = link.getAttribute('href');
        if (!rawHref || rawHref === '#' || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
            return;
        }

        // Allow explicit opt-out via data-no-spa or target=_blank
        if (link.dataset.noSpa !== undefined || link.target === '_blank') {
            return;
        }

        // Direct logout to full redirect
        if (rawHref.includes('logout.php')) {
            return;
        }

        // Resolve absolute target URL
        let targetUrl;
        try {
            targetUrl = new URL(link.href, window.location.href);
        } catch (err) {
            return;
        }

        // Ensure target is on same origin
        if (targetUrl.origin !== window.location.origin) {
            return;
        }

        // Ignore anchor hash jumps on the current active page
        if (targetUrl.pathname === window.location.pathname && targetUrl.hash && !targetUrl.search) {
            const targetEl = document.querySelector(targetUrl.hash);
            if (targetEl) {
                e.preventDefault();
                targetEl.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        }

        // SPA Navigation
        e.preventDefault();
        loadPage(targetUrl.href, true);
    }

    /**
     * Intercepts form submissions inside main content
     */
    function handleGlobalSubmit(e) {
        const form = e.target;
        if (!form || !outlet || !outlet.contains(form)) return;

        // Allow explicit opt-out
        if (form.dataset.noSpa !== undefined || form.target === '_blank') {
            return;
        }

        // Resolve action URL correctly relative to current page
        const rawAction = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        
        let actionUrl;
        try {
            actionUrl = form.action ? new URL(form.action) : new URL(rawAction, window.location.href);
        } catch (err) {
            return;
        }

        // Only handle internal submissions
        if (actionUrl.origin !== window.location.origin) {
            return;
        }

        e.preventDefault();

        if (method === 'GET') {
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (value !== '') {
                    params.append(key, value);
                }
            }
            const target = new URL(actionUrl.href);
            target.search = params.toString();
            loadPage(target.href, true);
        } else if (method === 'POST') {
            submitFormAjax(form, actionUrl.href);
        }
    }

    /**
     * Handles POST form submission via AJAX and handles JSON or HTML responses
     */
    async function submitFormAjax(form, actionUrl) {
        if (isNavigating) return;
        isNavigating = true;

        showProgress(35);

        const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitBtns.forEach(btn => {
            btn.disabled = true;
            btn.dataset.origText = btn.innerText || btn.value;
            if (btn.innerText) btn.innerText = 'Processing...';
        });

        try {
            const formData = new FormData(form);
            const submitter = form.querySelector('[type="submit"]:focus') || form.querySelector('[type="submit"]');
            if (submitter && submitter.name && !formData.has(submitter.name)) {
                formData.append(submitter.name, submitter.value);
            }

            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-AgriMart-SPA': '1'
                }
            });

            // If response redirected to login
            if (response.redirected && response.url.includes('login.php')) {
                window.location.href = response.url;
                return;
            }

            const contentType = response.headers.get('content-type') || '';
            const responseText = await response.text();

            // Detect JSON responses (e.g. add_to_cart.php, update_cart.php)
            let isJson = false;
            let jsonData = null;
            if (contentType.includes('application/json')) {
                isJson = true;
                jsonData = JSON.parse(responseText);
            } else {
                try {
                    const trimmed = responseText.trim();
                    if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
                        jsonData = JSON.parse(trimmed);
                        isJson = typeof jsonData === 'object' && jsonData !== null;
                    }
                } catch (e) {
                    isJson = false;
                }
            }

            if (isJson) {
                // 1. If JSON contains explicit redirect
                if (jsonData.redirect) {
                    loadPage(jsonData.redirect, true);
                    return;
                }

                // 2. If JSON updated cart counter
                if (jsonData.cart_count !== undefined) {
                    updateHeaderCartCount(jsonData.cart_count);
                }

                // 3. Show Toast notification
                if (jsonData.success) {
                    showDashboardToast(`✓ ${jsonData.message || 'Item added to your shopping cart!'}`, false, 'cart.php', 'View Cart →');
                } else {
                    showDashboardToast(`✕ ${jsonData.message || 'Unable to complete request.'}`, true);
                }

                // 4. If currently on cart.php and an update/remove occurred, reload the cart content
                if (window.location.href.includes('cart.php') || actionUrl.includes('update_cart.php')) {
                    loadPage('cart.php', false);
                }

                return;
            }

            // Otherwise, it is an HTML page (e.g. booking confirmation, order details, profile update)
            showProgress(75);
            renderContent(responseText, response.url || actionUrl, true);

        } catch (err) {
            console.error('AgriMart SPA Form Submission Error:', err);
            // Fallback to normal browser form submit on network failure
            form.submit();
        } finally {
            submitBtns.forEach(btn => {
                btn.disabled = false;
                if (btn.dataset.origText) {
                    if (btn.innerText) btn.innerText = btn.dataset.origText;
                    else btn.value = btn.dataset.origText;
                }
            });
            hideProgress();
            isNavigating = false;
        }
    }

    /**
     * Loads target URL via AJAX and swaps content into #dashContentOutlet
     */
    async function loadPage(url, pushState = true) {
        if (isNavigating) return;
        isNavigating = true;

        showProgress(25);
        if (outlet) outlet.classList.add('loading');

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-AgriMart-SPA': '1'
                }
            });

            if (response.redirected && response.url.includes('login.php')) {
                window.location.href = response.url;
                return;
            }

            if (!response.ok && response.status === 404) {
                console.warn('AgriMart SPA: Page not found, performing full navigation:', url);
                window.location.href = url;
                return;
            }

            showProgress(80);
            const html = await response.text();
            renderContent(html, response.url || url, pushState);

        } catch (err) {
            console.error('AgriMart SPA Navigation Error:', err);
            window.location.href = url; // fallback to full reload
        } finally {
            if (outlet) outlet.classList.remove('loading');
            hideProgress();
            isNavigating = false;
        }
    }

    /**
     * Parses fetched HTML and replaces only the main outlet content
     */
    function renderContent(html, targetUrl, pushState) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // 1. Update Document Title
        if (doc.title) {
            document.title = doc.title;
        }

        // 2. Extract Main Content
        let newContent = null;

        const dashWrap = doc.querySelector('.dash-content-wrap');
        const dashMain = doc.querySelector('.dash-main-content');
        const pageWrap = doc.querySelector('.page-wrap');
        const prodDetail = doc.querySelector('.product-detail-section');
        const equipDetail = doc.querySelector('.equipment-detail-page');
        const myProductsPage = doc.querySelector('.my-products-page');
        const cartPage = doc.querySelector('.cart-page');
        const catalogSec = doc.querySelector('#catalog');
        const mainTag = doc.querySelector('main');

        if (dashWrap) {
            newContent = dashWrap.outerHTML;
        } else if (dashMain) {
            newContent = dashMain.innerHTML;
        } else if (prodDetail) {
            newContent = `<div class="dash-content-wrap">${prodDetail.outerHTML}</div>`;
        } else if (equipDetail) {
            newContent = `<div class="dash-content-wrap">${equipDetail.outerHTML}</div>`;
        } else if (pageWrap) {
            newContent = `<div class="dash-content-wrap">${pageWrap.outerHTML}</div>`;
        } else if (myProductsPage) {
            newContent = `<div class="dash-content-wrap">${myProductsPage.outerHTML}</div>`;
        } else if (cartPage) {
            newContent = `<div class="dash-content-wrap">${cartPage.outerHTML}</div>`;
        } else if (catalogSec) {
            const hero = doc.querySelector('.page-hero');
            const heroHtml = hero ? hero.outerHTML : '';
            newContent = `<div class="dash-content-wrap">${heroHtml}${catalogSec.outerHTML}</div>`;
        } else if (mainTag) {
            newContent = `<div class="dash-content-wrap">${mainTag.outerHTML}</div>`;
        } else {
            // Fallback: extract body innerHTML without headers/footers
            const tempDiv = doc.createElement('div');
            tempDiv.innerHTML = doc.body.innerHTML;
            tempDiv.querySelectorAll('.site-header, .dash-topbar, .dash-sidebar, .site-footer').forEach(el => el.remove());
            newContent = `<div class="dash-content-wrap">${tempDiv.innerHTML}</div>`;
        }

        // 3. Inject new content into outlet
        outlet.innerHTML = newContent;

        // 4. Update Header Badges & User Info
        updateHeaderBadges(doc);

        // 5. Update Browser History
        if (pushState) {
            history.pushState({ url: targetUrl }, '', targetUrl);
        }

        // 6. Update Active Sidebar Link
        updateActiveSidebar(targetUrl);

        // 7. Auto close mobile drawer if open
        document.body.classList.remove('sidebar-open');

        // 8. Close profile dropdown if open
        const userDropdown = document.getElementById('userDropdownMenu');
        if (userDropdown) userDropdown.classList.remove('show');

        // 9. Scroll Main View to top
        window.scrollTo({ top: 0, behavior: 'instant' });

        // 10. Execute any embedded scripts in the newly inserted HTML
        initPageScripts(outlet);
    }

    /**
     * Executes script tags inside the newly rendered HTML in global window context
     */
    function initPageScripts(container) {
        if (!container) return;

        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            if (oldScript.src) {
                if (oldScript.src.includes('dashboard-router.js')) return;
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.src = oldScript.src;
                document.body.appendChild(newScript);
            } else if (oldScript.textContent) {
                try {
                    window.eval(oldScript.textContent);
                } catch (e) {
                    console.error('AgriMart SPA Script Execution Error:', e);
                }
            }
        });
    }

    /**
     * Updates active class on sidebar navigation links
     */
    function updateActiveSidebar(url) {
        let pathname = '';
        try {
            const urlObj = new URL(url, window.location.origin);
            pathname = urlObj.pathname.split('/').pop();
        } catch (e) {
            pathname = 'dashboard.php';
        }
        if (!pathname || pathname === '') pathname = 'dashboard.php';

        const sidebarLinks = document.querySelectorAll('.dash-sidebar .dash-nav-link');
        sidebarLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            if (!linkHref) return;

            const linkFile = linkHref.split('?')[0].split('#')[0];
            
            if (
                linkFile === pathname || 
                (pathname === 'dashboard.php' && linkFile === 'index.php') || 
                (pathname === 'order_details.php' && linkFile === 'orders.php') || 
                (pathname === 'booking_details.php' && linkFile === 'bookings.php') ||
                (pathname === 'product_details.php' && linkFile === 'products.php') ||
                (pathname === 'equipment_details.php' && linkFile === 'equipment.php')
            ) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    /**
     * Synchronizes badge counters from fetched document
     */
    function updateHeaderBadges(doc) {
        // Sync Cart Count
        const newCartBadge = doc.getElementById('cartCount');
        if (newCartBadge) {
            const count = parseInt(newCartBadge.textContent.trim(), 10) || 0;
            updateHeaderCartCount(count);
        }

        // Sync Alert Badge
        const newAlertBadge = doc.querySelector('.dash-topbar [title="Notifications"] .dash-badge-count');
        const currentAlertBadge = document.querySelector('.dash-topbar [title="Notifications"] .dash-badge-count');
        if (newAlertBadge && currentAlertBadge) {
            currentAlertBadge.textContent = newAlertBadge.textContent;
        }
    }

    /**
     * Updates cart badge counter with animation across the dashboard
     */
    function updateHeaderCartCount(count) {
        const badges = document.querySelectorAll('#cartCount, .cart-count, .dash-nav-badge[data-badge="cart"], .yt-menu-badge[data-badge="cart"]');
        badges.forEach(b => {
            b.textContent = count;
            b.style.display = count > 0 ? 'inline-block' : 'none';
            b.classList.remove('badge-pop');
            void b.offsetWidth; // trigger reflow
            b.classList.add('badge-pop');
        });
    }

    /**
     * Displays a floating toast notification
     */
    function showDashboardToast(message, isError = false, actionUrl = 'cart.php', actionText = 'View Cart →') {
        let toast = document.getElementById('agriDashToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'agriDashToast';
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: #0d2818;
                color: #f5f0df;
                padding: 16px 22px;
                border-left: 4px solid #d6b95f;
                border-radius: 4px;
                box-shadow: 0 12px 36px rgba(0,0,0,0.4);
                z-index: 99999;
                font-size: 14px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                display: flex;
                align-items: center;
                gap: 16px;
                transform: translateY(100px);
                opacity: 0;
                transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
                max-width: 440px;
                box-sizing: border-box;
            `;
            document.body.appendChild(toast);
        }

        toast.style.borderLeftColor = isError ? '#e57373' : '#d6b95f';
        toast.innerHTML = `
            <div style="flex:1; line-height: 1.4;">${message}</div>
            ${actionUrl ? `<a href="${actionUrl}" class="dash-toast-link" style="color:#d6b95f; font-weight:700; text-decoration:none; font-size:12px; font-family:monospace; text-transform:uppercase; white-space:nowrap;">${actionText}</a>` : ''}
        `;

        // Handle click on toast link via SPA
        const link = toast.querySelector('.dash-toast-link');
        if (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                loadPage(actionUrl, true);
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
            });
        }

        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        });

        if (window.dashToastTimeout) clearTimeout(window.dashToastTimeout);
        window.dashToastTimeout = setTimeout(() => {
            toast.style.transform = 'translateY(100px)';
            toast.style.opacity = '0';
        }, 4500);
    }

    /**
     * Shows top loading progress bar
     */
    function showProgress(percentage) {
        if (!progressBar) return;
        progressBar.classList.add('loading');
        progressBar.style.width = percentage + '%';
    }

    /**
     * Hides top loading progress bar
     */
    function hideProgress() {
        if (!progressBar) return;
        progressBar.style.width = '100%';
        setTimeout(() => {
            progressBar.classList.remove('loading');
            progressBar.style.width = '0%';
        }, 200);
    }

    // Expose SPA methods globally
    window.AgriMartSPA = {
        navigateTo: loadPage,
        showToast: showDashboardToast,
        updateCartCount: updateHeaderCartCount
    };

})();
