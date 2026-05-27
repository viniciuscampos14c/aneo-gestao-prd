(function () {
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.querySelector('[data-sidebar-open]');
    const closeBtn = document.querySelector('[data-sidebar-close]');
    const adminThemeToggle = document.querySelector('[data-admin-theme-toggle]');
    const adminThemeIconDark = document.querySelector('[data-theme-icon-dark]');
    const adminThemeIconLight = document.querySelector('[data-theme-icon-light]');
    const adminThemeKey = 'aneo_admin_theme';
    const sidebarCollapseBtn = document.querySelector('[data-sidebar-collapse]');
    const sidebarStateKey = 'aneo_admin_sidebar';

    if (openBtn && sidebar) {
        openBtn.addEventListener('click', () => sidebar.classList.remove('-translate-x-full'));
    }
    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', () => sidebar.classList.add('-translate-x-full'));
    }

    const applySidebarState = (state) => {
        const isCollapsed = state === 'collapsed';
        document.documentElement.classList.toggle('admin-sidebar-collapsed', isCollapsed);

        if (sidebarCollapseBtn) {
            const label = isCollapsed ? 'Expandir menu' : 'Recolher menu';
            sidebarCollapseBtn.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');
            sidebarCollapseBtn.setAttribute('title', label);
        }
    };

    let currentSidebarState = 'expanded';
    try {
        if (localStorage.getItem(sidebarStateKey) === 'collapsed') {
            currentSidebarState = 'collapsed';
        }
    } catch (error) {
        currentSidebarState = document.documentElement.classList.contains('admin-sidebar-collapsed') ? 'collapsed' : 'expanded';
    }

    applySidebarState(currentSidebarState);

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', () => {
            currentSidebarState = currentSidebarState === 'collapsed' ? 'expanded' : 'collapsed';
            applySidebarState(currentSidebarState);
            try {
                localStorage.setItem(sidebarStateKey, currentSidebarState);
            } catch (error) {
                // Ignora indisponibilidade de storage.
            }
        });
    }

    const applyAdminTheme = (theme) => {
        const isLight = theme === 'light';
        document.documentElement.classList.toggle('admin-theme-light', isLight);

        if (adminThemeIconDark) {
            adminThemeIconDark.classList.toggle('hidden', isLight);
        }

        if (adminThemeIconLight) {
            adminThemeIconLight.classList.toggle('hidden', !isLight);
        }

        if (adminThemeToggle) {
            const nextLabel = isLight ? 'Alternar para tema escuro' : 'Alternar para tema claro';
            adminThemeToggle.setAttribute('aria-label', nextLabel);
            adminThemeToggle.setAttribute('title', nextLabel);
        }
    };

    let currentAdminTheme = 'dark';
    try {
        if (localStorage.getItem(adminThemeKey) === 'light') {
            currentAdminTheme = 'light';
        }
    } catch (error) {
        currentAdminTheme = document.documentElement.classList.contains('admin-theme-light') ? 'light' : 'dark';
    }

    applyAdminTheme(currentAdminTheme);

    if (adminThemeToggle) {
        adminThemeToggle.addEventListener('click', () => {
            currentAdminTheme = currentAdminTheme === 'light' ? 'dark' : 'light';
            applyAdminTheme(currentAdminTheme);

            try {
                localStorage.setItem(adminThemeKey, currentAdminTheme);
            } catch (error) {
                // Ignora localStorage indisponivel.
            }
        });
    }

    const positionFloatingPanel = (triggerEl, panelEl) => {
        if (!triggerEl || !panelEl) return;

        const triggerRect = triggerEl.getBoundingClientRect();
        const panelRect = panelEl.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
        const desktopSidebarMode = Boolean(sidebarRect) && viewportWidth >= 1024;

        let left = 12;
        let top = 12;

        if (desktopSidebarMode && sidebarRect) {
            const sidebarWidth = Math.max(210, Math.round(sidebarRect.width - 24));
            left = Math.round(sidebarRect.left + 12);
            top = Math.round(triggerRect.bottom + 6);

            if (top + panelRect.height > viewportHeight - 12) {
                top = Math.max(12, Math.round(triggerRect.top - panelRect.height - 6));
            }

            panelEl.style.width = `${sidebarWidth}px`;
        } else {
            panelEl.style.width = '';
            left = sidebarRect ? Math.round(sidebarRect.right + 10) : Math.round(triggerRect.right + 12);
            if (left + panelRect.width > viewportWidth - 12) {
                left = Math.max(12, Math.round(triggerRect.left - panelRect.width - 12));
            }

            top = Math.round(triggerRect.top);
            if (top + panelRect.height > viewportHeight - 12) {
                top = Math.max(12, viewportHeight - panelRect.height - 12);
            }
        }

        if (top < 12) {
            top = 12;
        }

        panelEl.style.position = 'fixed';
        panelEl.style.zIndex = '240';
        panelEl.style.left = `${left}px`;
        panelEl.style.top = `${top}px`;
    };

    const fabToggle = document.getElementById('fab-toggle');
    const fabMenu = document.getElementById('fab-menu');

    if (fabToggle && fabMenu) {
        fabToggle.addEventListener('click', () => {
            fabMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!fabMenu.contains(event.target) && !fabToggle.contains(event.target)) {
                fabMenu.classList.add('hidden');
            }
        });
    }

    const wireSidebarGroup = ({ triggerSelector, panelSelector, chevronSelector }) => {
        const trigger = document.querySelector(triggerSelector);
        const panel = document.querySelector(panelSelector);
        const chevron = document.querySelector(chevronSelector);

        if (!trigger || !panel) {
            return;
        }

        let closeTimer = null;

        const clearCloseTimer = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        };

        const setExpanded = (expanded) => {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (chevron) {
                if (expanded) {
                    chevron.classList.add('rotate-90');
                } else {
                    chevron.classList.remove('rotate-90');
                }
            }
        };

        const isOpen = () => !panel.classList.contains('hidden');

        const positionPanel = () => {
            positionFloatingPanel(trigger, panel);
        };

        const openPanel = () => {
            clearCloseTimer();
            panel.classList.remove('hidden');
            positionPanel();
            setExpanded(true);
        };

        const closePanel = () => {
            clearCloseTimer();
            panel.classList.add('hidden');
            setExpanded(false);
        };

        const scheduleClose = () => {
            clearCloseTimer();
            closeTimer = window.setTimeout(closePanel, 220);
        };

        trigger.addEventListener('mouseenter', openPanel);
        trigger.addEventListener('mouseleave', scheduleClose);
        trigger.addEventListener('focus', openPanel);
        trigger.addEventListener('blur', scheduleClose);

        panel.addEventListener('mouseenter', clearCloseTimer);
        panel.addEventListener('mouseleave', scheduleClose);

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            if (isOpen()) {
                closePanel();
            } else {
                openPanel();
            }
        });

        document.addEventListener('click', (event) => {
            if (!isOpen()) {
                return;
            }

            if (trigger.contains(event.target) || panel.contains(event.target)) {
                return;
            }

            closePanel();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) {
                closePanel();
            }
        });

        window.addEventListener('resize', () => {
            if (isOpen()) {
                positionPanel();
            }
        });

        window.addEventListener('scroll', () => {
            if (isOpen()) {
                positionPanel();
            }
        }, true);
    };

    wireSidebarGroup({
        triggerSelector: '[data-finance-trigger]',
        panelSelector: '[data-finance-panel]',
        chevronSelector: '[data-finance-chevron]',
    });

    wireSidebarGroup({
        triggerSelector: '[data-cadastro-trigger]',
        panelSelector: '[data-cadastro-panel]',
        chevronSelector: '[data-cadastro-chevron]',
    });

    const wireHorizontalScrollProxy = () => {
        const shells = document.querySelectorAll('.finance-scroll-shell');
        shells.forEach((shell) => {
            const scrollArea = shell.querySelector('[data-horizontal-scroll-area]');
            const proxy = shell.querySelector('[data-horizontal-scroll-proxy]');
            const proxyContent = shell.querySelector('[data-horizontal-scroll-proxy-content]');
            if (!scrollArea || !proxy || !proxyContent) {
                return;
            }

            const table = scrollArea.querySelector('table');
            if (!table) {
                proxy.style.display = 'none';
                return;
            }

            let syncing = false;

            const refresh = () => {
                const contentWidth = Math.ceil(table.scrollWidth);
                const viewportWidth = Math.ceil(scrollArea.clientWidth);
                proxyContent.style.width = `${contentWidth}px`;
                proxy.style.display = contentWidth > viewportWidth ? 'block' : 'none';
            };

            proxy.addEventListener('scroll', () => {
                if (syncing) return;
                syncing = true;
                scrollArea.scrollLeft = proxy.scrollLeft;
                syncing = false;
            });

            scrollArea.addEventListener('scroll', () => {
                if (syncing) return;
                syncing = true;
                proxy.scrollLeft = scrollArea.scrollLeft;
                syncing = false;
            });

            refresh();
            window.addEventListener('resize', refresh);
        });
    };

    wireHorizontalScrollProxy();

    const wireAdminAlertBadgeRefresh = () => {
        const trigger = document.querySelector('[data-mobile-neg-trigger]');
        if (!trigger) {
            return;
        }

        const endpoint = String(trigger.getAttribute('data-mobile-neg-endpoint') || '').trim();
        if (!endpoint) {
            return;
        }

        const staticAlertCount = Number(trigger.getAttribute('data-static-alert-count') || '0') || 0;
        let staticAlertKeys = [];
        try {
            staticAlertKeys = JSON.parse(String(trigger.getAttribute('data-static-alert-keys') || '[]'));
        } catch (error) {
            staticAlertKeys = [];
        }
        staticAlertKeys = Array.isArray(staticAlertKeys)
            ? staticAlertKeys.map((key) => String(key || '').trim()).filter((key) => key !== '')
            : [];
        let refreshInFlight = false;

        const seenKey = (key) => 'aneo_admin_alert_seen_' + key;
        const isSeen = (key) => {
            try {
                if (localStorage.getItem(seenKey(key))) {
                    return true;
                }
            } catch (error) {
            }
            try {
                if (sessionStorage.getItem(seenKey(key))) {
                    return true;
                }
            } catch (error) {
            }
            return false;
        };

        const currentStaticCount = () => {
            if (staticAlertKeys.length === 0) {
                return staticAlertCount;
            }

            return staticAlertKeys.filter((key) => !isSeen(key)).length;
        };

        const renderBadge = (total) => {
            let badge = trigger.querySelector('[data-admin-alert-badge]');
            if (total <= 0) {
                if (badge) {
                    badge.remove();
                }
                return;
            }

            if (!badge) {
                badge = document.createElement('span');
                badge.setAttribute('data-admin-alert-badge', '');
                badge.className = 'absolute -right-1 -top-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white';
                trigger.appendChild(badge);
            }

            badge.textContent = String(Math.min(99, total));
        };

        const refresh = async () => {
            if (refreshInFlight) {
                return;
            }

            refreshInFlight = true;
            try {
                const response = await fetch(endpoint, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const mobileCount = Number(payload?.data?.mobile_negotiation_alert_count || 0) || 0;
                const mobileAlerts = Array.isArray(payload?.data?.mobile_negotiation_alerts)
                    ? payload.data.mobile_negotiation_alerts
                    : [];
                const visibleMobileKeys = mobileAlerts
                    .map((alert) => 'mobile-negotiation-' + String(alert?.id || '').trim())
                    .filter((key) => key !== 'mobile-negotiation-');
                const unseenVisibleMobileCount = visibleMobileKeys.filter((key) => !isSeen(key)).length;
                const unseenHiddenMobileCount = Math.max(0, mobileCount - visibleMobileKeys.length);

                renderBadge(currentStaticCount() + unseenVisibleMobileCount + unseenHiddenMobileCount);
            } catch (error) {
                // Ignora falhas silenciosas no refresh do badge.
            } finally {
                refreshInFlight = false;
            }
        };

        renderBadge(currentStaticCount());
        refresh();
        window.setTimeout(refresh, 1500);
        window.setInterval(refresh, 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        });
        window.addEventListener('focus', refresh);
        document.addEventListener('aneo-admin-alerts-updated', refresh);
    };

    wireAdminAlertBadgeRefresh();

    const cards = document.querySelectorAll('[data-student-card]');
    const zones = document.querySelectorAll('[data-dropzone]');

    cards.forEach((card) => {
        card.addEventListener('dragstart', () => {
            card.classList.add('dragging');
            card.dataset.dragging = '1';
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            delete card.dataset.dragging;
        });
    });

    zones.forEach((zone) => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));

        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');

            const dragging = document.querySelector('[data-dragging="1"]');
            if (!dragging) return;

            const studentId = dragging.dataset.studentId;
            const statusId = zone.dataset.dropzone;
            const csrf = zone.dataset.csrf;

            if (!studentId || !statusId) return;

            zone.appendChild(dragging);

            try {
                const response = await fetch('index.php?route=kanban/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: `_csrf=${encodeURIComponent(csrf)}&student_id=${encodeURIComponent(studentId)}&status_id=${encodeURIComponent(statusId)}`,
                });

                const raw = await response.text();
                let payload = null;

                if (raw) {
                    const clean = raw.replace(/^\uFEFF+/, '');
                    try {
                        payload = JSON.parse(clean);
                    } catch (parseError) {
                        payload = null;
                    }
                }

                if (!response.ok || !payload || !payload.ok) {
                    const message = payload && payload.message
                        ? payload.message
                        : `Falha ao mover card (HTTP ${response.status}).`;
                    alert(message);
                    window.location.reload();
                    return;
                }

                document.querySelectorAll('[data-dropzone]').forEach((dropzone) => {
                    const columnId = dropzone.dataset.dropzone;
                    const countEl = document.querySelector(`[data-status-count="${columnId}"]`);
                    if (countEl) {
                        countEl.textContent = dropzone.querySelectorAll('[data-student-card]').length;
                    }
                });
            } catch (error) {
                alert('Erro de rede ao mover card.');
                window.location.reload();
            }
        });
    });
})();

