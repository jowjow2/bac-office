@once
    <script>
        window.BacNotificationConfig = {
            feedUrl: @json(route('notifications.feed')),
            readAllUrl: @json(route('notifications.read-all')),
            readUrlTemplate: @json(route('notifications.read', ['notification' => '__ID__'])),
            openUrlTemplate: @json(route('notifications.open', ['notification' => '__ID__'])),
            biddingTrackUrl: @json(auth()->user()?->role === 'bidder' ? route('bidder.bidding-track.data', [], false) : null),
            csrfToken: @json(csrf_token()),
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const config = window.BacNotificationConfig || {};
            if (!config.feedUrl) return;

            const badgeSelectors = ['[data-notification-badge]'];
            const list = document.querySelector('[data-notifications-list]');
            const dropdownList = document.querySelector('[data-notification-dropdown-list]');
            const unreadLabels = document.querySelectorAll('[data-notification-unread-label]');
            const readAllForms = document.querySelectorAll('[data-notifications-read-all]');
            const emptyText = 'No important notifications right now.';
            let latestIds = new Set();
            let lastToastNotificationId = null;

            // Track bidding updates for toast (skip first load)
            let lastBiddingUpdates = false;
            let biddingInitialized = false;

            syncNotifications(false);

            // Bidding track live badge - only if element exists (bidder pages)
            const biddingBadgeSelector = '[data-bidding-track-badge]';
            const biddingTrackUrl = config.biddingTrackUrl;
            const biddingHasElement = document.querySelector(biddingBadgeSelector) !== null;
            if (biddingTrackUrl && biddingHasElement) {
                syncBiddingTrack(); // initial fetch
                window.biddingTrackInterval = setInterval(function () {
                    syncBiddingTrack();
                }, 5000);
            }

            window.setInterval(function () {
                syncNotifications(true);
            }, 3000);

            readAllForms.forEach(function (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    try {
                        await fetch(config.readAllUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });

                        document.querySelectorAll('[data-notification-row]').forEach(markRowRead);
                        updateBadges(0);
                    } catch (error) {
                        form.submit();
                    }
                });
            });

            document.addEventListener('click', function (event) {
                const link = event.target.closest('[data-notification-open]');
                if (!link) return;

                const id = link.dataset.notificationId;
                if (!id) return;

                markRowRead(link.closest('[data-notification-row]'));
                updateBadges(Math.max(currentBadgeCount() - 1, 0));

                fetch(urlFor(config.readUrlTemplate, id), {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                }).catch(function () {});
            });

            async function syncNotifications(highlightNew) {
                try {
                    const response = await fetch(config.feedUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    if (!data.ok) return;

                    const notifications = data.notifications || [];
                    const incomingIds = new Set(notifications.map(function (item) { return String(item.id); }));
                    const hasNew = highlightNew && notifications.some(function (item) {
                        return !latestIds.has(String(item.id));
                    });

                    updateBadges(Number(data.unread_count || 0));
                    renderList(list, notifications, false);
                    renderList(dropdownList, notifications.slice(0, 5), true);

                    if (hasNew) {
                        const newestNotification = notifications.find(function (item) {
                            return !latestIds.has(String(item.id)) && !item.is_read;
                        });

                        if (newestNotification) {
                            showNotificationToast(newestNotification);
                        }

                        document.dispatchEvent(new CustomEvent('bac:notifications-updated', {
                            detail: data,
                        }));
                    }

                    latestIds = incomingIds;
                } catch (error) {
                    // Live notifications are best-effort; the page still works without polling.
                }
            }

            async function syncBiddingTrack() {
                try {
                    const response = await fetch(biddingTrackUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    if (!data.ok) return;

                    const hasUpdates = !!data.has_recent_updates;
                    updateBiddingBadges(data);

                    // Toast: only after first poll, show when updates appear
                    if (biddingHasElement) {
                        if (!biddingInitialized) {
                            // First load: set baseline state, no toast
                            lastBiddingUpdates = hasUpdates;
                            biddingInitialized = true;
                        } else if (hasUpdates && !lastBiddingUpdates) {
                            // New updates since last poll
                            showBidUpdateToast();
                        }
                        lastBiddingUpdates = hasUpdates;
                    }
                } catch (error) {
                    // Best-effort
                }
            }

            function updateBiddingBadges(data) {
                const badgeEl = document.querySelector(biddingBadgeSelector);
                if (!badgeEl) return;

                const hasUpdates = !!data.has_recent_updates;

                // Red NEW badge for recent updates
                if (hasUpdates) {
                    badgeEl.textContent = 'NEW';
                    badgeEl.hidden = false;
                    badgeEl.style.display = 'inline-flex';
                    badgeEl.classList.add('active');
                } else {
                    badgeEl.textContent = '';
                    badgeEl.hidden = true;
                    badgeEl.style.display = 'none';
                    badgeEl.classList.remove('active');
                }

                // LIVE indicator for active bids
                let liveIndicator = document.querySelector('.live-indicator');
                if (!liveIndicator) {
                    liveIndicator = document.createElement('span');
                    liveIndicator.className = 'live-indicator';
                    const link = badgeEl.closest('a');
                    if (link) link.insertBefore(liveIndicator, badgeEl);
                }

                if (data.has_active_bids) {
                    liveIndicator.innerHTML = '<i class="fas fa-circle" style="font-size:8px;animation:blink 1.5s infinite;"></i> LIVE';
                    liveIndicator.title = 'Live bidding updates active';
                    liveIndicator.style.display = 'inline-flex';
                } else {
                    liveIndicator.style.display = 'none';
                }

                // Dispatch event so track page can update timeline
                window.dispatchEvent(new CustomEvent('bac:bidding-track-updated', {
                    detail: data
                }));
            }

            function showBidUpdateToast() {
                // Remove any existing toast
                const existing = document.querySelector('.toast-bidding-update');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.className = 'toast toast-bidding-update';
                toast.innerHTML = '<i class="fas fa-sync-alt" style="margin-right:8px;animation:spin 1s linear infinite;"></i> Bid status updated';
                toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:12px 24px;border-radius:8px;font-size:14px;z-index:9999;box-shadow:0 4px 12px rgba(22,163,74,0.3);';
                document.body.appendChild(toast);

                setTimeout(function () {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.3s';
                    setTimeout(function () { toast.remove(); }, 300);
                }, 4000);
            }

            function showNotificationToast(notification) {
                if (!notification || String(notification.id) === String(lastToastNotificationId)) return;
                lastToastNotificationId = notification.id;

                const existing = document.querySelector('.toast-live-notification');
                if (existing) existing.remove();

                const toast = document.createElement('a');
                toast.className = 'toast-live-notification';
                toast.href = openUrl(notification.id);
                toast.dataset.notificationOpen = '';
                toast.dataset.notificationId = notification.id;
                toast.dataset.notificationRow = '';
                toast.innerHTML = `
                    <span class="toast-live-notification-icon"><i class="fas fa-bell"></i></span>
                    <span class="toast-live-notification-copy">
                        <strong>${escapeHtml(notification.title || 'New notification')}</strong>
                        <small>${escapeHtml(notification.message || '')}</small>
                    </span>
                `;
                toast.style.cssText = [
                    'position:fixed',
                    'top:20px',
                    'right:20px',
                    'width:min(360px,calc(100vw - 32px))',
                    'display:flex',
                    'align-items:center',
                    'gap:12px',
                    'padding:14px 16px',
                    'border-radius:14px',
                    'background:#0f172a',
                    'color:#fff',
                    'text-decoration:none',
                    'box-shadow:0 18px 40px rgba(15,23,42,0.28)',
                    'z-index:10050',
                    'transform:translateY(-8px)',
                    'opacity:0',
                    'transition:opacity .2s ease, transform .2s ease'
                ].join(';');

                const style = document.createElement('style');
                style.textContent = `
                    .toast-live-notification.show { opacity: 1 !important; transform: translateY(0) !important; }
                    .toast-live-notification-icon {
                        width: 38px;
                        height: 38px;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        flex: 0 0 auto;
                        border-radius: 999px;
                        background: rgba(249, 115, 22, 0.16);
                        color: #fb923c;
                    }
                    .toast-live-notification-copy {
                        min-width: 0;
                        display: grid;
                        gap: 3px;
                    }
                    .toast-live-notification-copy strong {
                        color: #ffffff;
                        font-size: 14px;
                        line-height: 1.25;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                    .toast-live-notification-copy small {
                        color: #cbd5e1;
                        font-size: 12px;
                        line-height: 1.35;
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }
                `;

                if (!document.querySelector('style[data-live-notification-toast-style]')) {
                    style.dataset.liveNotificationToastStyle = '';
                    document.head.appendChild(style);
                }

                document.body.appendChild(toast);
                requestAnimationFrame(function () {
                    toast.classList.add('show');
                });

                setTimeout(function () {
                    toast.classList.remove('show');
                    setTimeout(function () { toast.remove(); }, 220);
                }, 5000);
            }

            function renderList(target, notifications, compact) {
                if (!target) return;

                if (notifications.length === 0) {
                    target.innerHTML = compact
                        ? `<div class="notification-empty">${emptyText}</div>`
                        : `<div class="empty-state">${emptyText}</div>`;
                    return;
                }

                target.innerHTML = notifications.map(function (notification) {
                    return compact ? renderDropdownItem(notification) : renderPageRow(notification);
                }).join('');
            }

            function getNotificationIcon(notification) {
                let iconClass = 'fa-bell';
                let iconColor = 'notification-icon-default';
                const title = (notification.title || '').toLowerCase();
                const message = (notification.message || '').toLowerCase();

                if (title.includes('bid') || message.includes('bid')) {
                    if (message.includes('rejected')) {
                        iconClass = 'fa-circle-xmark';
                        iconColor = 'notification-icon-danger';
                    } else if (message.includes('validated')) {
                        iconClass = 'fa-check-circle';
                        iconColor = 'notification-icon-success';
                    } else {
                        iconClass = 'fa-file-contract';
                        iconColor = 'notification-icon-info';
                    }
                } else if (title.includes('project') || message.includes('project')) {
                    iconClass = 'fa-briefcase';
                    iconColor = 'notification-icon-warning';
                } else if (title.includes('message') || message.includes('message')) {
                    iconClass = 'fa-envelope';
                    iconColor = 'notification-icon-info';
                } else if (title.includes('award') || message.includes('award')) {
                    iconClass = 'fa-trophy';
                    iconColor = 'notification-icon-warning';
                } else if (title.includes('assign') || message.includes('assign')) {
                    iconClass = 'fa-tasks';
                    iconColor = 'notification-icon-primary';
                }
                return { iconClass, iconColor };
            }

            function renderPageRow(notification) {
                const stateClass = notification.is_read ? 'notification-read' : 'notification-unread';
                const { iconClass, iconColor } = getNotificationIcon(notification);
                const unreadIndicator = notification.is_read ? '' : '<span class="notification-unread-indicator"></span>';

                return `
                    <a href="${escapeAttribute(openUrl(notification.id))}"
                       class="notification-item ${stateClass}"
                       data-notification-row
                       data-notification-open
                       data-notification-id="${escapeAttribute(notification.id)}">
                        <div class="notification-icon ${iconColor}">
                            <i class="fas ${iconClass}"></i>
                            ${unreadIndicator}
                        </div>
                        <div class="notification-content">
                            <h3 class="notification-title">${escapeHtml(notification.title || notification.message || 'Notification')}</h3>
                            <p class="notification-message">${escapeHtml(notification.message || '')}</p>
                        </div>
                        <div class="notification-time">
                            <span>${escapeHtml(notification.time || 'Recently')}</span>
                            <div class="notification-action">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </a>
                `;
            }

            function renderDropdownItem(notification) {
                const stateClass = notification.is_read ? 'is-read' : 'is-unread';

                return `
                    <a href="${escapeAttribute(openUrl(notification.id))}"
                       class="notification-item ${stateClass}"
                       data-notification-row
                       data-notification-open
                       data-notification-id="${escapeAttribute(notification.id)}">
                        <div class="notification-item-title">${escapeHtml(notification.title || notification.message || 'Notification')}</div>
                        <div class="notification-item-meta">${escapeHtml(notification.message || '')}</div>
                        <div class="notification-item-meta">${escapeHtml(notification.time || 'Recently')}</div>
                    </a>
                `;
            }

            function updateBadges(count) {
                document.querySelectorAll('.notification-button').forEach(function (button) {
                    const existingBadge = button.querySelector('.notification-badge');
                    if (existingBadge && !existingBadge.dataset.notificationBadge) {
                        existingBadge.dataset.notificationBadge = '';
                    }

                    if (!button.querySelector('[data-notification-badge]')) {
                        const badge = document.createElement('span');
                        badge.className = 'notification-badge';
                        badge.dataset.notificationBadge = '';
                        badge.hidden = true;
                        badge.style.display = 'none';
                        button.appendChild(badge);
                    }
                });

                badgeSelectors.forEach(function (selector) {
                    document.querySelectorAll(selector).forEach(function (badge) {
                        if (!badge) return;

                        if (count > 0) {
                            badge.textContent = String(count);
                            badge.hidden = false;
                            badge.style.display = 'inline-flex';
                        } else {
                            badge.textContent = '';
                            badge.hidden = true;
                            badge.style.display = 'none';
                        }
                    });
                });

                unreadLabels.forEach(function (label) {
                    label.textContent = count > 0
                        ? `${count} unread notification${count === 1 ? '' : 's'}`
                        : 'All important notifications are read';
                });
            }

            function currentBadgeCount() {
                const badge = document.querySelector('[data-notification-badge], .notification-button .notification-badge');
                return Number(badge?.textContent || 0);
            }

            function markRowRead(row) {
                if (!row) return;
                // Handle full-page notification items
                if (row.classList.contains('notification-unread')) {
                    row.classList.remove('notification-unread');
                    row.classList.add('notification-read');
                }
                // Handle dropdown notification items
                if (row.classList.contains('is-unread')) {
                    row.classList.remove('is-unread');
                    row.classList.add('is-read');
                }
            }

            function openUrl(id) {
                return urlFor(config.openUrlTemplate, id);
            }

            function urlFor(template, id) {
                return String(template || '').replace('__ID__', encodeURIComponent(id));
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text == null ? '' : String(text);
                return div.innerHTML;
            }

            function escapeAttribute(text) {
                return escapeHtml(text).replace(/"/g, '&quot;');
            }
        });
    </script>
@endonce
