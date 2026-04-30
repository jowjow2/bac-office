document.addEventListener('DOMContentLoaded', () => {
    initDashboardSidebar();
    initLogoutConfirm();
});

function initDashboardSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const navLeft = document.querySelector('.navbar .nav-left');

    if (!sidebar || !navLeft || typeof window.matchMedia !== 'function') {
        return;
    }

    const mobileBreakpoint = window.matchMedia('(max-width: 768px)');
    const sidebarId = sidebar.id || 'dashboardSidebar';
    sidebar.id = sidebarId;

    let toggleButton = document.querySelector('[data-dashboard-sidebar-toggle]');

    if (!toggleButton) {
        toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'dashboard-menu-toggle';
        toggleButton.dataset.dashboardSidebarToggle = 'true';
        toggleButton.setAttribute('aria-controls', sidebarId);
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Open navigation menu');
        toggleButton.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
        navLeft.insertBefore(toggleButton, navLeft.firstChild);
    }

    let backdrop = document.querySelector('.dashboard-sidebar-backdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'dashboard-sidebar-backdrop';
        backdrop.hidden = true;
        document.body.appendChild(backdrop);
    }

    const closeSidebar = () => {
        document.body.classList.remove('dashboard-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.setAttribute('aria-label', 'Open navigation menu');
        backdrop.hidden = true;
    };

    const openSidebar = () => {
        if (!mobileBreakpoint.matches) {
            return;
        }

        document.body.classList.add('dashboard-sidebar-open');
        toggleButton.setAttribute('aria-expanded', 'true');
        toggleButton.setAttribute('aria-label', 'Close navigation menu');
        backdrop.hidden = false;
    };

    const toggleSidebar = () => {
        if (document.body.classList.contains('dashboard-sidebar-open')) {
            closeSidebar();
            return;
        }

        openSidebar();
    };

    toggleButton.addEventListener('click', toggleSidebar);
    backdrop.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileBreakpoint.matches) {
                closeSidebar();
            }
        });
    });

    const handleViewportChange = (event) => {
        if (!event.matches) {
            closeSidebar();
        }
    };

    if (typeof mobileBreakpoint.addEventListener === 'function') {
        mobileBreakpoint.addEventListener('change', handleViewportChange);
    } else if (typeof mobileBreakpoint.addListener === 'function') {
        mobileBreakpoint.addListener(handleViewportChange);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('dashboard-sidebar-open')) {
            closeSidebar();
        }
    });

    closeSidebar();
}

function initLogoutConfirm() {
    const logoutForms = Array.from(document.querySelectorAll('.sidebar-form')).filter((form) => (
        form.querySelector('.sidebar-logout')
    ));

    if (logoutForms.length === 0) {
        return;
    }

    let pendingLogoutForm = null;

    const modal = document.createElement('div');
    modal.className = 'logout-confirm';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="logout-confirm-card" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle" aria-describedby="logoutConfirmText">
            <div class="logout-confirm-icon" aria-hidden="true">!</div>
            <h3 id="logoutConfirmTitle" class="logout-confirm-title">Logout</h3>
            <p id="logoutConfirmText" class="logout-confirm-text">Are you sure you want to logout?</p>
            <div class="logout-confirm-actions">
                <button type="button" class="logout-confirm-btn logout-confirm-cancel" data-logout-cancel>Cancel</button>
                <button type="button" class="logout-confirm-btn logout-confirm-yes" data-logout-confirm>Yes</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const cancelButton = modal.querySelector('[data-logout-cancel]');
    const confirmButton = modal.querySelector('[data-logout-confirm]');

    function closeLogoutConfirm() {
        pendingLogoutForm = null;
        modal.classList.remove('is-open');
        document.body.classList.remove('logout-confirm-open');

        window.setTimeout(() => {
            if (!modal.classList.contains('is-open')) {
                modal.hidden = true;
            }
        }, 180);
    }

    function openLogoutConfirm(form) {
        pendingLogoutForm = form;
        modal.hidden = false;
        document.body.classList.add('logout-confirm-open');

        window.requestAnimationFrame(() => {
            modal.classList.add('is-open');
            confirmButton?.focus();
        });
    }

    logoutForms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.logoutConfirmed === 'true') {
                return;
            }

            event.preventDefault();
            openLogoutConfirm(form);
        });
    });

    cancelButton?.addEventListener('click', closeLogoutConfirm);

    confirmButton?.addEventListener('click', () => {
        if (!pendingLogoutForm) {
            closeLogoutConfirm();
            return;
        }

        const formToSubmit = pendingLogoutForm;
        formToSubmit.dataset.logoutConfirmed = 'true';
        closeLogoutConfirm();
        formToSubmit.submit();
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeLogoutConfirm();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeLogoutConfirm();
        }
    });
}
