/**
 * AgriMart Real-Time Cart & UI Interactions (cart.js)
 */

document.addEventListener('DOMContentLoaded', function() {
    initAddToCartAjax();
    initCartQuantityAjax();
});

// 1. Toast Notification Helper
function showAgriToast(message, isError = false, actionUrl = 'cart.php', actionText = 'View Cart →') {
    let toast = document.getElementById('agriToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'agriToast';
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0d2818;
            color: #f5f0df;
            padding: 16px 22px;
            border-left: 4px solid #d6b95f;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            z-index: 99999;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 16px;
            transform: translateY(100px);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
            max-width: 420px;
        `;
        document.body.appendChild(toast);
    }

    if (isError) {
        toast.style.borderLeftColor = '#e57373';
    } else {
        toast.style.borderLeftColor = '#d6b95f';
    }

    toast.innerHTML = `
        <div style="flex:1;">${message}</div>
        ${actionUrl ? `<a href="${actionUrl}" style="color:#d6b95f; font-weight:700; text-decoration:none; font-size:13px; font-family:monospace; text-transform:uppercase;">${actionText}</a>` : ''}
    `;

    // Trigger animation
    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    if (window.toastTimeout) clearTimeout(window.toastTimeout);
    window.toastTimeout = setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }, 4500);
}

// 2. Update Header Cart Badges
function updateCartCountBadges(count) {
    const badges = document.querySelectorAll('.cart-count, #cartCount, .dash-badge-count, .dash-nav-badge, .yt-menu-badge');
    badges.forEach(b => {
        b.textContent = count;
        if (count > 0) {
            b.style.display = 'flex';
        } else {
            b.style.display = 'none';
        }
        b.classList.add('badge-pop');
        setTimeout(() => b.classList.remove('badge-pop'), 400);
    });
}

// 3. Intercept Add-to-Cart Submissions
function initAddToCartAjax() {
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.getAttribute('action') && form.getAttribute('action').includes('add_to_cart.php')) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            const origText = submitBtn ? submitBtn.innerText : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = 'Adding...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = origText;
                }

                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.success) {
                    updateCartCountBadges(data.cart_count);
                    showAgriToast(`✓ ${data.message || 'Item added to your shopping cart!'}`);
                } else {
                    showAgriToast(`✕ ${data.message || 'Unable to add item to cart.'}`, true);
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = origText;
                }
                form.submit();
            });
        }
    });
}

// 4. Real-Time Cart Quantity AJAX (+ / - / Update / Remove)
function initCartQuantityAjax() {
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.getAttribute('action') && form.getAttribute('action').includes('update_cart.php')) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');

            // Explicitly capture submit button name & value
            const submitter = e.submitter;
            if (submitter && submitter.name && submitter.value) {
                formData.set(submitter.name, submitter.value);
            }

            const cartRow = form.closest('.cart-item, [data-cart-row], article');
            const clickedBtn = submitter;
            if (clickedBtn) {
                clickedBtn.disabled = true;
                clickedBtn.style.opacity = '0.6';
            }

            fetch('update_cart.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (clickedBtn) {
                    clickedBtn.disabled = false;
                    clickedBtn.style.opacity = '1';
                }

                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                if (data.success) {
                    updateCartCountBadges(data.cart_count);

                    if (data.item_removed && cartRow) {
                        cartRow.style.transition = 'opacity 0.3s ease, transform 0.3s ease, max-height 0.3s ease';
                        cartRow.style.opacity = '0';
                        cartRow.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            cartRow.remove();
                            // If cart is now empty, reload to show empty state
                            if (data.cart_count === 0 || data.product_count === 0) {
                                window.location.reload();
                            }
                        }, 300);
                        showAgriToast(data.message || 'Item removed from cart.', false, '', '');
                    } else if (cartRow) {
                        // Update Quantity Input & Displays
                        const qtyInput = cartRow.querySelector('input[name="quantity"]');
                        if (qtyInput) qtyInput.value = data.quantity;

                        const qtyDisplay = cartRow.querySelector('.cart-quantity, .qty-val, [data-qty]');
                        if (qtyDisplay) qtyDisplay.textContent = data.quantity;

                        // Update Subtotal Display
                        const subtotalEl = cartRow.querySelector('.cart-subtotal strong, .cart-item-subtotal, [data-subtotal]');
                        if (subtotalEl) subtotalEl.textContent = data.item_subtotal;

                        showAgriToast(data.message || 'Cart updated.', false, '', '');
                    }

                    // Update Grand Totals and Item Counts in Order Summary
                    const summaryTotalEls = document.querySelectorAll('.summary-total strong, .cart-total-amount, #cartTotal');
                    summaryTotalEls.forEach(el => el.textContent = data.cart_total);

                    const summaryItemsEl = document.getElementById('summaryItemsCount');
                    if (summaryItemsEl) summaryItemsEl.textContent = data.cart_count;

                    const summaryProductsEl = document.getElementById('summaryProductsCount');
                    if (summaryProductsEl) summaryProductsEl.textContent = data.product_count;
                } else {
                    showAgriToast(`✕ ${data.message || 'Error updating cart.'}`, true);
                }
            })
            .catch(err => {
                if (clickedBtn) {
                    clickedBtn.disabled = false;
                    clickedBtn.style.opacity = '1';
                }
                form.submit();
            });
        }
    });
}
