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
            const gap = 14;
            left = Math.round(sidebarRect.right + gap);

            if (left + panelRect.width > viewportWidth - 12) {
                left = Math.max(12, Math.round(viewportWidth - panelRect.width - 12));
            }

            top = Math.round(triggerRect.top + (triggerRect.height / 2) - (panelRect.height / 2));
            if (top + panelRect.height > viewportHeight - 12) {
                top = Math.max(12, Math.round(viewportHeight - panelRect.height - 12));
            }

            panelEl.style.width = '';
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

    const cadastroTrigger = document.querySelector('[data-cadastro-trigger]');
    const cadastroPanel = document.querySelector('[data-cadastro-panel]');
    const cadastroChevron = document.querySelector('[data-cadastro-chevron]');

    if (cadastroTrigger && cadastroPanel) {
        let closeTimer = null;

        const clearCloseTimer = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        };

        const setExpanded = (expanded) => {
            cadastroTrigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (cadastroChevron) {
                if (expanded) {
                    cadastroChevron.classList.add('rotate-90');
                } else {
                    cadastroChevron.classList.remove('rotate-90');
                }
            }
        };

        const isOpen = () => !cadastroPanel.classList.contains('hidden');

        const positionPanel = () => {
            positionFloatingPanel(cadastroTrigger, cadastroPanel);
        };

        const openPanel = () => {
            clearCloseTimer();
            cadastroPanel.classList.remove('hidden');
            positionPanel();
            setExpanded(true);
        };

        const closePanel = () => {
            clearCloseTimer();
            cadastroPanel.classList.add('hidden');
            setExpanded(false);
        };

        const scheduleClose = () => {
            clearCloseTimer();
            closeTimer = window.setTimeout(closePanel, 220);
        };

        cadastroTrigger.addEventListener('mouseenter', openPanel);
        cadastroTrigger.addEventListener('mouseleave', scheduleClose);
        cadastroTrigger.addEventListener('focus', openPanel);
        cadastroTrigger.addEventListener('blur', scheduleClose);

        cadastroPanel.addEventListener('mouseenter', clearCloseTimer);
        cadastroPanel.addEventListener('mouseleave', scheduleClose);

        cadastroTrigger.addEventListener('click', (event) => {
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

            if (cadastroTrigger.contains(event.target) || cadastroPanel.contains(event.target)) {
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
    }

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

