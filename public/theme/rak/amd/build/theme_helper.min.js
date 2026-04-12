// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

/**
 * Theme RAK — AMD Helper Module
 *
 * Features:
 *  1. Smooth "Scroll to Top" floating action button.
 *  2. "Focus Mode" toggle that hides all blocks with one click.
 *  3. Sidebar hover-expand enhancement (CSS does most of the work;
 *     JS handles accessibility and state persistence).
 *  4. Lazy-load course card images via IntersectionObserver.
 *  5. Navbar scroll shadow enhancement.
 *
 * @module     theme_rak/theme_helper
 * @copyright  2024 RAK Theme
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/log'], function(Log) {
    'use strict';

    // ---------------------------------------------------------------- //
    // Constants                                                         //
    // ---------------------------------------------------------------- //
    const SCROLL_THRESHOLD = 300;          // px before scroll-top button appears
    const FOCUS_MODE_KEY   = 'rak_focus_mode';
    const SIDEBAR_KEY      = 'rak_sidebar_open';

    // ---------------------------------------------------------------- //
    // Utility helpers                                                   //
    // ---------------------------------------------------------------- //

    /**
     * Throttle a callback to at most once per animation frame.
     *
     * @param {Function} fn  Callback to throttle.
     * @returns {Function}   Throttled version.
     */
    function rafThrottle(fn) {
        let pending = false;
        return function(...args) {
            if (!pending) {
                pending = true;
                requestAnimationFrame(() => {
                    fn.apply(this, args);
                    pending = false;
                });
            }
        };
    }

    /**
     * Create a floating action button and append to body.
     *
     * @param {string} id         Element id.
     * @param {string} iconClass  FontAwesome class string.
     * @param {string} label      aria-label text.
     * @returns {HTMLElement}     The created button.
     */
    function createFab(id, iconClass, label) {
        const btn = document.createElement('button');
        btn.id = id;
        btn.setAttribute('aria-label', label);
        btn.setAttribute('type', 'button');
        btn.innerHTML = `<i class="${iconClass}" aria-hidden="true"></i>`;
        document.body.appendChild(btn);
        return btn;
    }

    // ---------------------------------------------------------------- //
    // Feature 1 — Scroll to Top                                        //
    // ---------------------------------------------------------------- //

    /**
     * Initialise the "Scroll to Top" FAB.
     */
    function initScrollToTop() {
        const btn = createFab('rak-scroll-top', 'fa fa-arrow-up', 'Scroll to top');

        // Show / hide based on scroll position.
        const onScroll = rafThrottle(() => {
            if (window.scrollY > SCROLL_THRESHOLD) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        });

        window.addEventListener('scroll', onScroll, {passive: true});

        // Smooth scroll to top on click.
        btn.addEventListener('click', () => {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });

        Log.debug('RAK: Scroll-to-Top initialised.');
    }

    // ---------------------------------------------------------------- //
    // Feature 2 — Focus Mode                                           //
    // ---------------------------------------------------------------- //

    /**
     * Apply or remove focus mode from the body.
     *
     * @param {boolean}     active   Whether focus mode should be on.
     * @param {HTMLElement} btn      The toggle button element.
     */
    function setFocusMode(active, btn) {
        document.body.classList.toggle('rak-focus-mode', active);
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', String(active));

        // Show a brief toast-style notification.
        const msg = active ? 'Focus mode ON — blocks hidden' : 'Focus mode OFF — blocks visible';
        showToast(msg);

        try {
            sessionStorage.setItem(FOCUS_MODE_KEY, active ? '1' : '0');
        } catch (_) { /* storage blocked */ }
    }

    /**
     * Initialise the Focus Mode FAB.
     */
    function initFocusMode() {
        const btn = createFab('rak-focus-toggle', 'fa fa-eye', 'Toggle focus mode');
        btn.setAttribute('aria-pressed', 'false');

        // Restore state from session.
        try {
            const stored = sessionStorage.getItem(FOCUS_MODE_KEY);
            if (stored === '1') {
                setFocusMode(true, btn);
            }
        } catch (_) { /* storage blocked */ }

        btn.addEventListener('click', () => {
            const isActive = document.body.classList.contains('rak-focus-mode');
            setFocusMode(!isActive, btn);
        });

        Log.debug('RAK: Focus Mode initialised.');
    }

    // ---------------------------------------------------------------- //
    // Feature 3 — Sidebar Hover Expand (accessibility layer)           //
    // ---------------------------------------------------------------- //

    /**
     * Add keyboard / ARIA support for the sidebar mini expansion.
     * CSS handles the visual expand; JS handles aria-expanded.
     */
    function initSidebarMini() {
        const drawer = document.getElementById('nav-drawer');
        if (!drawer) {
            return;
        }

        // Restore open state.
        const wasOpen = localStorage.getItem(SIDEBAR_KEY) === '1';
        if (wasOpen) {
            drawer.classList.add('rak-sidebar-pinned');
        }

        drawer.addEventListener('mouseenter', () => {
            drawer.setAttribute('aria-expanded', 'true');
        });
        drawer.addEventListener('mouseleave', () => {
            if (!drawer.classList.contains('rak-sidebar-pinned')) {
                drawer.setAttribute('aria-expanded', 'false');
            }
        });

        // Double-click to pin sidebar open.
        drawer.addEventListener('dblclick', () => {
            const pinned = drawer.classList.toggle('rak-sidebar-pinned');
            localStorage.setItem(SIDEBAR_KEY, pinned ? '1' : '0');
            showToast(pinned ? 'Sidebar pinned open' : 'Sidebar unpinned');
        });

        Log.debug('RAK: Sidebar mini initialised.');
    }

    // ---------------------------------------------------------------- //
    // Feature 4 — Lazy-load course card images                         //
    // ---------------------------------------------------------------- //

    /**
     * Observe all .card-img-top images and load them only when near
     * the viewport (performance optimisation for dashboard with many
     * course cards).
     */
    function initLazyImages() {
        if (!('IntersectionObserver' in window)) {
            return; // Fallback: browser loads everything normally.
        }

        const images = document.querySelectorAll('.card-img-top[data-src], .courseimage[data-src]');
        if (!images.length) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        }, {rootMargin: '200px 0px'});

        images.forEach(img => observer.observe(img));

        Log.debug('RAK: Lazy image observer initialised for', images.length, 'images.');
    }

    // ---------------------------------------------------------------- //
    // Feature 5 — Navbar scroll shadow                                 //
    // ---------------------------------------------------------------- //

    /**
     * Add a stronger shadow to the navbar when the user scrolls down.
     */
    function initNavbarShadow() {
        const navbar = document.querySelector('.navbar.fixed-top, nav.navbar');
        if (!navbar) {
            return;
        }

        const onScroll = rafThrottle(() => {
            navbar.classList.toggle('rak-scrolled', window.scrollY > 8);
        });

        window.addEventListener('scroll', onScroll, {passive: true});
    }

    // ---------------------------------------------------------------- //
    // Feature 6 — Course card enter animation (stagger)               //
    // ---------------------------------------------------------------- //

    /**
     * Stagger-animate course cards on the dashboard/frontpage.
     */
    function initCardAnimations() {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const cards = document.querySelectorAll(
            '.card, .dashboard-card, .frontpage-course-list .coursebox'
        );

        cards.forEach((card, i) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.4s ease ${i * 0.05}s, transform 0.4s ease ${i * 0.05}s`;
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, {threshold: 0.1});

        cards.forEach(card => observer.observe(card));
    }

    // ---------------------------------------------------------------- //
    // Toast notification helper                                        //
    // ---------------------------------------------------------------- //

    let toastTimer = null;

    /**
     * Show a small toast notification at the bottom of the screen.
     *
     * @param {string} message  The message to display.
     * @param {number} duration Milliseconds before auto-dismiss.
     */
    function showToast(message, duration = 2400) {
        let toast = document.getElementById('rak-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'rak-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.style.cssText = [
                'position:fixed',
                'bottom:24px',
                'left:50%',
                'transform:translateX(-50%) translateY(16px)',
                'background:rgba(26,26,46,0.88)',
                'color:#fff',
                'padding:8px 20px',
                'border-radius:50px',
                'font-size:0.82rem',
                'font-weight:500',
                'z-index:99999',
                'opacity:0',
                'transition:opacity 0.22s,transform 0.22s',
                'pointer-events:none',
                'white-space:nowrap',
            ].join(';');
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        // Trigger reflow so transition runs.
        void toast.offsetHeight;
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';

        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(16px)';
        }, duration);
    }

    // ---------------------------------------------------------------- //
    // Public API                                                        //
    // ---------------------------------------------------------------- //

    return {
        /**
         * Initialise all RAK theme enhancements.
         * Called via {{#js}} in the layout PHP or requirejs in lib.php.
         *
         * @param {object} config Optional config overrides.
         */
        init: function(config) {
            config = Object.assign({
                scrollToTop:     true,
                focusMode:       true,
                sidebarMini:     true,
                lazyImages:      true,
                navbarShadow:    true,
                cardAnimations:  true,
            }, config || {});

            document.addEventListener('DOMContentLoaded', () => {
                if (config.scrollToTop)    { initScrollToTop(); }
                if (config.focusMode)      { initFocusMode(); }
                if (config.sidebarMini)    { initSidebarMini(); }
                if (config.lazyImages)     { initLazyImages(); }
                if (config.navbarShadow)   { initNavbarShadow(); }
                if (config.cardAnimations) { initCardAnimations(); }

                Log.debug('RAK Theme Helper fully initialised.');
            });
        },

        // Expose toast for use by other modules if needed.
        showToast: showToast,
    };
});
