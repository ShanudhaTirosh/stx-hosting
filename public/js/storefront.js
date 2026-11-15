/**
 * STX Hosting — Senior Frontend Motion Engine & Interactivity
 * High-performance, 60fps animations utilizing IntersectionObserver and requestAnimationFrame.
 * Fully accessible, respecting prefers-reduced-motion.
 */

(function () {
    'use strict';

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('DOMContentLoaded', () => {
        initStickyNav();
        initScrollReveals();
        initPricingToggles();
        initAccordions();
        initCounters();
        initRouteProgress();
    });

    /**
     * Sticky Navigation Bar with subtle shadow elevation on scroll
     */
    function initStickyNav() {
        const bar = document.querySelector('.bar');
        if (!bar) return;

        const onScroll = () => {
            if (window.scrollY > 15) {
                bar.classList.add('scrolled');
            } else {
                bar.classList.remove('scrolled');
            }
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /**
     * Staggered Scroll-Triggered Reveals via IntersectionObserver
     */
    function initScrollReveals() {
        if (prefersReducedMotion) {
            document.querySelectorAll('[data-reveal]').forEach(el => el.classList.add('revealed'));
            return;
        }

        const reveals = document.querySelectorAll('[data-reveal]');
        if (!reveals.length) return;

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const delay = parseInt(el.getAttribute('data-delay') || '0', 10);
                    
                    if (delay > 0) {
                        setTimeout(() => el.classList.add('revealed'), delay);
                    } else {
                        el.classList.add('revealed');
                    }
                    obs.unobserve(el);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        reveals.forEach(el => observer.observe(el));
    }

    /**
     * Interactive Monthly / Annual Billing Toggle with Smooth Price Interpolation
     */
    function initPricingToggles() {
        const toggleContainers = document.querySelectorAll('.pricing-toggle');
        if (!toggleContainers.length) return;

        toggleContainers.forEach(container => {
            const buttons = container.querySelectorAll('.toggle-btn');
            
            buttons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const term = e.currentTarget.getAttribute('data-term');
                    if (!term) return;

                    buttons.forEach(b => b.classList.remove('active'));
                    e.currentTarget.classList.add('active');

                    // Update all pricing elements bound to this toggle
                    document.querySelectorAll('[data-price-monthly]').forEach(priceEl => {
                        const monthlyPrice = parseInt(priceEl.getAttribute('data-price-monthly') || '0', 10);
                        const annualPrice = parseInt(priceEl.getAttribute('data-price-annual') || monthlyPrice, 10);
                        const currency = priceEl.getAttribute('data-currency') || 'LKR';
                        
                        const target = term === 'annual' ? annualPrice : monthlyPrice;
                        animatePriceNumber(priceEl, target, currency);
                    });

                    // Update term suffix labels
                    document.querySelectorAll('[data-term-label]').forEach(lbl => {
                        lbl.textContent = term === 'annual' ? '/mo, billed annually' : '/month';
                    });

                    // Toggle annual savings badges
                    document.querySelectorAll('.annual-badge').forEach(badge => {
                        badge.style.display = term === 'annual' ? 'inline-block' : 'none';
                    });
                });
            });
        });
    }

    /**
     * Smooth requestAnimationFrame Number Interpolation for Prices
     */
    function animatePriceNumber(element, targetVal, currency) {
        if (prefersReducedMotion) {
            element.textContent = formatCurrency(targetVal, currency);
            return;
        }

        const startVal = parseInt(element.getAttribute('data-current-val') || targetVal, 10);
        const startTime = performance.now();
        const duration = 280;

        element.setAttribute('data-current-val', targetVal);

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-Out Spring curve
            const ease = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(startVal + (targetVal - startVal) * ease);

            element.textContent = formatCurrency(current, currency);

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    function formatCurrency(amount, currency) {
        const formatted = new Intl.NumberFormat('en-US').format(amount);
        return currency === 'USD' ? `$${formatted}` : `Rs. ${formatted}`;
    }

    /**
     * Accessible, Animated FAQ Accordions
     */
    function initAccordions() {
        const triggers = document.querySelectorAll('.accordion-trigger');
        if (!triggers.length) return;

        triggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const item = trigger.closest('.accordion-item');
                if (!item) return;

                const isOpen = item.classList.contains('open');

                // Close other items in the same accordion group
                const parent = item.closest('.accordion');
                if (parent) {
                    parent.querySelectorAll('.accordion-item').forEach(other => {
                        if (other !== item) {
                            other.classList.remove('open');
                            const otherTrigger = other.querySelector('.accordion-trigger');
                            if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
                        }
                    });
                }

                // Toggle current
                if (isOpen) {
                    item.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                } else {
                    item.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    /**
     * Animated Number Counters for Statistics & Telemetry
     */
    function initCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseFloat(el.getAttribute('data-counter') || '0');
                    const decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
                    const suffix = el.getAttribute('data-suffix') || '';

                    if (prefersReducedMotion) {
                        el.textContent = target.toFixed(decimals) + suffix;
                    } else {
                        animateCounter(el, target, decimals, suffix);
                    }

                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.2 });

        counters.forEach(el => observer.observe(el));
    }

    function animateCounter(el, target, decimals, suffix) {
        const startTime = performance.now();
        const duration = 1200;

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            const current = (target * ease);

            el.textContent = current.toFixed(decimals) + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                el.textContent = target.toFixed(decimals) + suffix;
            }
        }

        requestAnimationFrame(update);
    }

    /**
     * Route Transition Top Progress Bar
     */
    function initRouteProgress() {
        const links = document.querySelectorAll('a[href^="/"]:not([target]):not([href^="#"])');
        if (!links.length) return;

        let progressBar = document.getElementById('route-progress');
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = 'route-progress';
            progressBar.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 0%;
                height: 2.5px;
                background: linear-gradient(90deg, #087a7d, #4a56e2);
                z-index: 9999;
                pointer-events: none;
                transition: width 350ms cubic-bezier(0.16, 1, 0.3, 1), opacity 200ms ease;
            `;
            document.body.appendChild(progressBar);
        }

        links.forEach(link => {
            link.addEventListener('click', (e) => {
                // Ignore cmd/ctrl clicks or modified clicks
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                progressBar.style.opacity = '1';
                progressBar.style.width = '70%';
            });
        });
    }
})();
