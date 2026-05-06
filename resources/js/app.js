import './bootstrap';
import './auth';

document.addEventListener('DOMContentLoaded', () => {
    const slides = [
        {
            src: '/Images/slider1.png',
            title: 'Bid opening and committee activity',
            meta: 'Bid openings, committee sessions, and procurement activities presented with clear public-facing captions.',
        },
        {
            src: '/Images/slider2.png',
            title: 'Procurement workflow in motion',
            meta: 'Gallery slides help explain how BAC-Office operations move from coordination to public notice and award posting.',
        },
        {
            src: '/Images/slider3.png',
            title: 'Project handling and documentation',
            meta: 'Use photos and documentation highlights to give visitors a quick view of procurement-related activity.',
        },
        {
            src: '/Images/slider4.png',
            title: 'Committee sessions and review moments',
            meta: 'Support transparency with a visual record of review sessions, oversight, and official coordination.',
        },
        {
            src: '/Images/slider5.png',
            title: 'BAC-Office public operations',
            meta: 'Show office milestones and procurement events in a format that feels intentional and easy to scan.',
        },
        {
            src: '/Images/slider6.png',
            title: 'Procurement coordination highlights',
            meta: 'Highlight procurement coordination, records management, and BAC-Office activity through documented visuals.',
        },
    ];

    let currentSlideIndex = 0;
    const carouselImage = document.getElementById('carouselImage');
    const nextButton = document.querySelector('.carousel-btn.right');
    const previousButton = document.querySelector('.carousel-btn.left');
    const carouselCaptionTitle = document.getElementById('carouselCaptionTitle');
    const carouselCaptionMeta = document.getElementById('carouselCaptionMeta');
    const carouselStepIndicator = document.getElementById('carouselStepIndicator');
    const carouselDots = document.getElementById('carouselDots');
    let autoSlideTimer = null;

    function renderSlideMeta(index) {
        const slide = slides[index];
        if (!slide) return;

        if (carouselCaptionTitle) {
            carouselCaptionTitle.textContent = slide.title;
        }

        if (carouselCaptionMeta) {
            carouselCaptionMeta.textContent = slide.meta;
        }

        if (carouselStepIndicator) {
            carouselStepIndicator.textContent = `${String(index + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
        }

        if (carouselDots) {
            carouselDots.querySelectorAll('.carousel-dot').forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === index);
            });
        }
    }

    function renderSlide(index) {
        if (!carouselImage) return;

        carouselImage.style.opacity = '0';

        window.setTimeout(() => {
            carouselImage.src = slides[index].src;
            carouselImage.style.opacity = '1';
            renderSlideMeta(index);
        }, 180);
    }

    function showNextSlide() {
        currentSlideIndex = (currentSlideIndex + 1) % slides.length;
        renderSlide(currentSlideIndex);
    }

    function showPreviousSlide() {
        currentSlideIndex = (currentSlideIndex - 1 + slides.length) % slides.length;
        renderSlide(currentSlideIndex);
    }

    function resetAutoSlide() {
        if (!carouselImage) return;

        window.clearInterval(autoSlideTimer);
        autoSlideTimer = window.setInterval(showNextSlide, 5000);
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            showNextSlide();
            resetAutoSlide();
        });
    }

    if (previousButton) {
        previousButton.addEventListener('click', () => {
            showPreviousSlide();
            resetAutoSlide();
        });
    }

    if (carouselImage) {
        if (carouselDots) {
            slides.forEach((slide, index) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'carousel-dot';
                dot.setAttribute('aria-label', `Go to slide ${index + 1}: ${slide.title}`);

                dot.addEventListener('click', () => {
                    currentSlideIndex = index;
                    renderSlide(currentSlideIndex);
                    resetAutoSlide();
                });

                carouselDots.appendChild(dot);
            });
        }

        renderSlideMeta(currentSlideIndex);
        resetAutoSlide();
    }

    // Keep global handlers available for any cached inline button markup.
    window.nextSlide = showNextSlide;
    window.prevSlide = showPreviousSlide;

    const siteNavbar = document.getElementById('siteNavbar');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.getElementById('navLinks');
    const navRight = document.getElementById('navRight');

    function syncMenuToggleState(isOpen) {
        if (!menuToggle) return;

        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menuToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        menuToggle.innerHTML = isOpen ? '&times;' : '&#9776;';
    }

    function closeMobileMenu() {
        if (!siteNavbar || !menuToggle || !navLinks || !navRight) return;

        siteNavbar.classList.remove('mobile-open');
        navLinks.classList.remove('show');
        navRight.classList.remove('show');
        syncMenuToggleState(false);
    }

    if (siteNavbar && menuToggle && navLinks && navRight) {
        syncMenuToggleState(false);

        menuToggle.addEventListener('click', () => {
            const isOpen = siteNavbar.classList.toggle('mobile-open');
            navLinks.classList.toggle('show', isOpen);
            navRight.classList.toggle('show', isOpen);
            syncMenuToggleState(isOpen);
        });

        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMobileMenu);
        });

        document.addEventListener('click', (event) => {
            if (window.innerWidth > 900) return;
            if (siteNavbar.contains(event.target)) return;
            closeMobileMenu();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                closeMobileMenu();
            }
        });
    }

    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const cards = document.querySelectorAll('.doc-card');

    function filterCards() {
        if (!searchInput || !categoryFilter) return;

        const search = searchInput.value.toLowerCase();
        const category = categoryFilter.value;

        cards.forEach((card) => {
            const title = card.dataset.title || '';
            const cardCategory = card.dataset.category || '';
            const matchSearch = title.includes(search);
            const matchCategory = category === 'all' || cardCategory.includes(category);

            card.style.display = matchSearch && matchCategory ? 'block' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('keyup', filterCards);
    if (categoryFilter) categoryFilter.addEventListener('change', filterCards);

    const publicDetailsTriggers = document.querySelectorAll('[data-public-details-trigger]');
    const publicDetailsModals = document.querySelectorAll('.public-details-modal');
    let activePublicDetailsModal = null;

    function closePublicDetailsModal(modal = activePublicDetailsModal) {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;

        if (activePublicDetailsModal === modal) {
            activePublicDetailsModal = null;
        }

        if (!document.querySelector('.public-details-modal.is-open')) {
            document.body.classList.remove('public-modal-open');
        }
    }

    function openPublicDetailsModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        if (activePublicDetailsModal && activePublicDetailsModal !== modal) {
            closePublicDetailsModal(activePublicDetailsModal);
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.classList.add('public-modal-open');
        activePublicDetailsModal = modal;

        const closeButton = modal.querySelector('[data-public-details-close]');
        if (closeButton) closeButton.focus({ preventScroll: true });
    }

    publicDetailsTriggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            const modalId = trigger.dataset.publicDetailsTrigger;
            const modal = modalId ? document.getElementById(modalId) : null;

            if (!modal) return;

            event.preventDefault();
            openPublicDetailsModal(modalId);
        });
    });

    publicDetailsModals.forEach((modal) => {
        modal.querySelectorAll('[data-public-details-close]').forEach((button) => {
            button.addEventListener('click', () => closePublicDetailsModal(modal));
        });

        const loginButton = modal.querySelector('[data-public-details-login]');
        if (loginButton) {
            loginButton.addEventListener('click', () => {
                closePublicDetailsModal(modal);

                if (typeof window.openLogin === 'function') {
                    window.setTimeout(() => window.openLogin(), 80);
                }
            });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePublicDetailsModal();
        }
    });

    const homeRevealItems = document.querySelectorAll('[data-home-reveal]');

    if (homeRevealItems.length > 0) {
        if ('IntersectionObserver' in window) {
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;

                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, {
                threshold: 0.18,
                rootMargin: '0px 0px -40px 0px',
            });

            homeRevealItems.forEach((item) => revealObserver.observe(item));
        } else {
            homeRevealItems.forEach((item) => item.classList.add('is-visible'));
        }
    }

    window.openPortal = function () {
        const modal = document.getElementById('portalModal');
        if (modal) modal.style.display = 'flex';
    };

    window.closePortal = function () {
        const modal = document.getElementById('portalModal');
        if (modal) modal.style.display = 'none';
    };

    window.showStaff = function () {
        const portal = document.getElementById('portalModal');
        const staff = document.getElementById('staffForm');

        if (portal) portal.style.display = 'none';
        if (staff) staff.style.display = 'flex';
    };

    window.showAdmin = function () {
        const portal = document.getElementById('portalModal');
        const admin = document.getElementById('adminForm');

        if (portal) portal.style.display = 'none';
        if (admin) admin.style.display = 'flex';
    };

    window.showRegister = function () {
        const login = document.getElementById('loginModal');
        const register = document.getElementById('registerForm');

        if (login) login.style.display = 'none';
        if (register) register.style.display = 'flex';
    };
});
