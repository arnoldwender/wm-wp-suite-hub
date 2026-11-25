/**
 * WM Suite Hub — Vanilla ES6+ Cockpit Controller
 *
 * Lightweight SPA controller communicating with the wm-hub/v1 REST API.
 * Supports dynamic theme switching (CyberEvents, WP Native, Obsidian Dark, High-Contrast).
 * Every control is a native button or link; the dialogs trap focus and return it on close.
 * Every text it shows comes translated from Hub_Orchestrator::cockpit_script_strings() (window.wmHubSettings.i18n),
 * and every value that goes into markup passes escapeHtml() first.
 *
 * @package ArnoldWender\SuiteHub
 * @since   1.0.0
 */

(function () {
    'use strict';

    // Global configuration passed via wp_localize_script
    const config = window.wmHubSettings || {
        restUrl: '/wp-json/wm-hub/v1/',
        restNonce: '',
        activeTheme: 'cyber-events',
        i18n: {}
    };
    const i18n = config.i18n || {};

    /**
     * A translated string with its placeholders filled the way PHP's sprintf() fills them
     * (%s, %d and the positional %1$s). A key PHP did not pass shows as itself, which the suite rules out.
     */
    function t(key, ...args) {
        const text = typeof i18n[key] === 'string' ? i18n[key] : key;
        let next = 0;
        return text.replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => {
            const value = position ? args[Number(position) - 1] : args[next++];
            return value === undefined || value === null ? '' : String(value);
        });
    }

    let lastFocusedElement = null;

    /**
     * Helper to perform authenticated REST API requests
     */
    async function apiRequest(endpoint, method = 'GET', body = null) {
        const headers = {
            'X-WP-Nonce': config.restNonce,
            'Content-Type': 'application/json'
        };

        const options = {
            method: method,
            headers: headers
        };

        if (body && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(body);
        }

        const url = config.restUrl.replace(/\/$/, '') + '/' + endpoint.replace(/^\//, '');

        try {
            const response = await fetch(url, options);
            const rawText  = await response.text();

            let data;
            try {
                // If there's leading notice or text before '{' or '[', slice from first valid JSON character
                const firstJsonChar = rawText.search(/[{\[]/);
                const cleanJson = firstJsonChar !== -1 ? rawText.slice(firstJsonChar) : rawText;
                data = JSON.parse(cleanJson);
            } catch (parseErr) {
                console.error('[WM Suite Hub] JSON Parse Error on payload:', rawText);
                throw new Error(response.ok ? t('errorOccurred') : t('httpError', response.status));
            }

            if (!response.ok) {
                throw new Error(data.message || t('httpError', response.status));
            }
            return data;
        } catch (error) {
            console.error('[WM Suite Hub] API Request Failed:', error);
            throw error;
        }
    }

    /**
     * Dynamic Theme Switcher with instant UI updates and REST API persistence
     */
    async function switchTheme(newTheme, scope = 'global') {
        const wrap = document.querySelector('.wm-cockpit-wrap');
        if (wrap) {
            wrap.setAttribute('data-wm-theme', newTheme);
        }

        // Synchronize dropdown selector
        const selector = document.getElementById('wm-theme-selector');
        if (selector && selector.value !== newTheme) {
            selector.value = newTheme;
        }

        try {
            await apiRequest('settings/theme', 'POST', { theme: newTheme, scope: scope });
            showToast(t('themeUpdated'), 'success');
        } catch (err) {
            console.error('[WM Suite Hub] Theme update failed:', err);
            showToast(t('themeSaveFailed', err.message), 'error');
        }
    }

    /**
     * Accessible Toast Notification
     */
    function showToast(message, type = 'success') {
        const existing = document.querySelectorAll('.wm-toast');
        existing.forEach(el => el.remove());

        const toast = document.createElement('div');
        toast.className = `wm-toast wm-toast-${type}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `<span class="dashicons dashicons-${type === 'success' ? 'yes' : 'warning'}"></span> <span>${escapeHtml(message)}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    /**
     * Loads and renders recent audit logs into the table
     */
    async function loadAuditLogs() {
        const tbody = document.getElementById('wm-audit-table-body');
        if (!tbody) return;

        try {
            const res = await apiRequest('audit/logs?limit=15');
            const logs = res.data || [];

            if (logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="wm-table-loading">${escapeHtml(t('noAuditEvents'))}</td></tr>`;
                return;
            }

            tbody.innerHTML = logs.map(log => `
                <tr>
                    <td><code>${escapeHtml(log.timestamp)}</code></td>
                    <td><strong>${escapeHtml(log.module_slug)}</strong></td>
                    <td><span class="wm-dep-tag">${escapeHtml(log.event_type)}</span></td>
                    <td>${escapeHtml(t('userNumber', log.user_id))}</td>
                    <td><code class="wm-hash-badge">${escapeHtml(log.current_hash ? log.current_hash.substring(0, 16) + '…' : '—')}</code></td>
                </tr>
            `).join('');
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="wm-table-loading wm-text-danger">${escapeHtml(t('logsLoadFailed', err.message))}</td></tr>`;
        }
    }

    /**
     * Walks the audit chain on the server and shows the result.
     */
    async function verifyCryptoChain() {
        const statusText = document.getElementById('wm-audit-status-text');
        const statusBanner = document.getElementById('wm-audit-verification-banner');

        if (statusText) statusText.textContent = t('chainChecking');

        try {
            const res = await apiRequest('audit/verify');
            const data = res.data || {};

            let modalHtml = '';
            if (data.intact) {
                if (statusBanner) {
                    statusBanner.className = 'wm-audit-verification-status wm-audit-intact';
                }
                if (statusText) {
                    statusText.textContent = t('chainIntactStatus', data.total_checked);
                }

                modalHtml = `
                    <div class="wm-verify-result">
                        <span class="dashicons dashicons-yes-alt wm-verify-icon wm-verify-icon--ok"></span>
                        <h4 class="wm-verify-title">${escapeHtml(t('chainIntactTitle'))}</h4>
                        <p class="wm-verify-text">${escapeHtml(t('chainIntactBody', data.total_checked))}</p>
                        <div class="wm-verify-hash">${escapeHtml(t('latestHash', data.latest_hash || '—'))}</div>
                    </div>
                `;
            } else {
                if (statusBanner) {
                    statusBanner.className = 'wm-audit-verification-status wm-audit-broken';
                }
                if (statusText) {
                    statusText.textContent = t('chainBrokenStatus', data.broken_at_id);
                }

                modalHtml = `
                    <div class="wm-verify-result">
                        <span class="dashicons dashicons-warning wm-verify-icon wm-verify-icon--broken"></span>
                        <h4 class="wm-verify-title wm-text-danger">${escapeHtml(t('chainBrokenTitle'))}</h4>
                        <p class="wm-verify-text">${escapeHtml(data.details || '')}</p>
                    </div>
                `;
            }

            openModal(t('chainCheckTitle'), modalHtml);
            loadAuditLogs();
        } catch (err) {
            if (statusText) statusText.textContent = t('chainCheckFailed');
            openModal(t('chainCheckFailed'), `<p class="wm-text-danger">${escapeHtml(err.message)}</p>`);
        }
    }

    /**
     * Refreshes fleet telemetry
     */
    async function refreshFleetStatus() {
        const btn = document.getElementById('wm-btn-refresh-telemetry');
        if (btn) {
            btn.disabled = true;
            btn.querySelector('.dashicons')?.classList.add('wm-spin');
        }

        try {
            await apiRequest('cache/flush', 'POST');
            await apiRequest('fleet/status?force_refresh=1');
            showToast(t('refreshSuccess'), 'success');
            setTimeout(() => window.location.reload(), 600);
        } catch (err) {
            showToast(t('refreshFailed', err.message), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.querySelector('.dashicons')?.classList.remove('wm-spin');
            }
        }
    }

    /**
     * Modal Controller with Focus Trap
     */
    function openModal(title, bodyHtml) {
        lastFocusedElement = document.activeElement;

        const modal = document.getElementById('wm-cockpit-modal');
        const modalTitle = document.getElementById('wm-modal-title');
        const modalBody = document.getElementById('wm-modal-body-content');

        if (!modal || !modalTitle || !modalBody) return;

        modalTitle.textContent = title;
        modalBody.innerHTML = bodyHtml;

        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');

        const closeBtn = document.getElementById('wm-modal-close-btn');
        if (closeBtn) closeBtn.focus();

        document.addEventListener('keydown', handleModalKeyDown);
    }

    function closeModal() {
        const modal = document.getElementById('wm-cockpit-modal');
        // Closed already: do nothing (until 1.2.3 an Escape anywhere on the page moved focus away).
        if (!modal || !modal.classList.contains('is-active')) return;

        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.wm-modal-overlay.is-active')) {
            document.removeEventListener('keydown', handleModalKeyDown);
        }

        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    /**
     * Focus trap for whichever dialog is open (the cockpit's own or the pre-flight one).
     */
    function handleModalKeyDown(e) {
        if (e.key === 'Escape') {
            closeModal();
            closePreflightModal();
            return;
        }

        if (e.key === 'Tab') {
            const modal = document.querySelector('.wm-modal-overlay.is-active');
            if (!modal) return;

            const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    let currentActiveBundleId = null;

    /**
     * Opens Accessible Preflight Inspection Modal
     */
    async function openPreflightModal(bundleId) {
        currentActiveBundleId = bundleId;
        const modal = document.getElementById('wm-preflight-modal');
        const loader = document.querySelector('.wm-preflight-loader');
        const results = document.getElementById('wm-preflight-results');
        const suiteName = document.getElementById('wm-modal-suite-name');

        if (!modal) return;

        lastFocusedElement = document.activeElement;
        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
        document.addEventListener('keydown', handleModalKeyDown);

        const closeBtn = document.getElementById('wm-btn-modal-close');
        if (closeBtn) closeBtn.focus();

        if (loader) loader.hidden = false;
        if (results) results.hidden = true;

        try {
            const data = await apiRequest('marketplace/preflight', 'POST', { bundle_id: bundleId });
            if (suiteName) suiteName.textContent = t('preflightTitle', data.suite_name || bundleId);

            if (loader) loader.hidden = true;
            if (results) {
                results.hidden = false;
                renderPreflightResults(data);
            }
        } catch (err) {
            if (loader) loader.hidden = true;
            if (results) {
                results.hidden = false;
                results.innerHTML = `<p class="wm-text-danger"><strong>${escapeHtml(t('preflightFailed'))}</strong> ${escapeHtml(err.message)}</p>`;
            }
        }
    }

    function renderPreflightResults(data) {
        const results = document.getElementById('wm-preflight-results');
        if (!results) return;

        const audit = data.preflight || {};
        const checks = audit.checks || {};
        const order = data.resolved_order || [];

        let html = '<div class="wm-preflight-table-wrap"><table class="wm-table wm-preflight-table"><thead><tr>'
            + `<th scope="col">${escapeHtml(t('colCheck'))}</th><th scope="col">${escapeHtml(t('colRequired'))}</th>`
            + `<th scope="col">${escapeHtml(t('colActual'))}</th><th scope="col">${escapeHtml(t('colStatus'))}</th></tr></thead><tbody>`;

        for (const c of Object.values(checks)) {
            const statusIcon = c.passed
                ? `<span class="dashicons dashicons-yes-alt wm-check-ok"></span> ${escapeHtml(t('checkMet'))}`
                : `<span class="dashicons dashicons-warning wm-check-warn"></span> ${escapeHtml(t('checkNotMet'))}`;
            html += `<tr><td><strong>${escapeHtml(c.name)}</strong></td><td><code>${escapeHtml(c.required)}</code></td><td><code>${escapeHtml(c.actual)}</code></td><td>${statusIcon}</td></tr>`;
        }

        html += '</tbody></table></div>';
        // With no declared dependency the "order" is just the listed one; say that instead of a computed path.
        const orderLabel = data.has_dependencies ? t('orderComputed') : t('orderFree');
        html += `<div class="wm-dag-box"><strong>${escapeHtml(orderLabel)}</strong><br><code>${escapeHtml(order.join(' → '))}</code></div>`;

        results.innerHTML = html;
    }

    function closePreflightModal() {
        const modal = document.getElementById('wm-preflight-modal');
        if (!modal || !modal.classList.contains('is-active')) return;

        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');

        // The cockpit dialog may still be open underneath; only drop the trap when none is.
        if (!document.querySelector('.wm-modal-overlay.is-active')) {
            document.removeEventListener('keydown', handleModalKeyDown);
        }
        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }

    /**
     * Dry run of a solution pack: resolves the install order on the server and writes nothing.
     * There is no install request in the UI any more (the endpoint answers 501 without dry_run),
     * so nothing here reloads the page or re-reads the audit log afterwards.
     */
    async function runDryRun(bundleId) {
        if (!bundleId) return;

        showToast(t('dryRunStarted', bundleId), 'success');

        try {
            const res = await apiRequest('marketplace/install-bundle', 'POST', { bundle_id: bundleId, dry_run: true });
            showToast(res.message || t('dryRunDone'), 'success');
            closePreflightModal();
        } catch (err) {
            showToast(t('dryRunFailed', err.message), 'error');
        }
    }

    /**
     * The "Solution Packs" and "Audit-Log" menu entries render this same page; it opens at their section.
     * Until 1.2.3 both entries landed at the top of the cockpit.
     */
    function openSectionOfMenuEntry() {
        const sectionByPage = {
            'wm-suite-hub-marketplace': 'wm-sec-marketplace',
            'wm-suite-hub-audit': 'wm-sec-audit'
        };
        const sectionId = sectionByPage[new URLSearchParams(window.location.search).get('page') || ''];
        const section = sectionId ? document.getElementById(sectionId) : null;
        if (section) {
            section.scrollIntoView();
        }
    }

    // Initialize Event Listeners
    document.addEventListener('DOMContentLoaded', () => {
        loadAuditLogs();
        openSectionOfMenuEntry();

        // Theme Switcher Dropdown Listener
        const themeSelector = document.getElementById('wm-theme-selector');
        if (themeSelector) {
            themeSelector.addEventListener('change', (e) => {
                switchTheme(e.target.value);
            });
        }

        // Opt-in Uninstall Toggle Listener
        const uninstallToggle = document.getElementById('wm-toggle-delete-on-uninstall');
        if (uninstallToggle) {
            uninstallToggle.addEventListener('change', async (e) => {
                const checked = Boolean(e.target.checked);
                uninstallToggle.disabled = true;
                try {
                    const res = await apiRequest('settings/lifecycle', 'POST', { delete_data_on_uninstall: checked });
                    showToast(res.message || t('lifecycleSaved'), 'success');
                } catch (err) {
                    e.target.checked = !checked;
                    showToast(t('saveFailed', err.message), 'error');
                } finally {
                    uninstallToggle.disabled = false;
                }
            });
        }

        // Theme Cards Button Listeners
        document.querySelectorAll('.wm-theme-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const theme = e.currentTarget.getAttribute('data-theme');
                if (theme) {
                    switchTheme(theme);
                }
            });
        });

        // Tab link state handling
        document.querySelectorAll('.wm-tab-link').forEach(link => {
            link.addEventListener('click', (e) => {
                document.querySelectorAll('.wm-tab-link').forEach(tab => tab.classList.remove('active', 'is-active'));
                e.currentTarget.classList.add('active', 'is-active');
            });
        });

        // Action Buttons
        const refreshBtn = document.getElementById('wm-btn-refresh-telemetry');
        if (refreshBtn) refreshBtn.addEventListener('click', refreshFleetStatus);

        const verifyBtn = document.getElementById('wm-btn-verify-crypto');
        if (verifyBtn) verifyBtn.addEventListener('click', verifyCryptoChain);

        // Modal dismissal
        const closeBtn = document.getElementById('wm-modal-close-btn');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        const dismissBtn = document.getElementById('wm-modal-dismiss-btn');
        if (dismissBtn) dismissBtn.addEventListener('click', closeModal);

        // Preflight modal buttons
        const preflightClose = document.getElementById('wm-btn-modal-close');
        if (preflightClose) preflightClose.addEventListener('click', closePreflightModal);

        const dryrunBtn = document.getElementById('wm-btn-modal-dryrun');
        if (dryrunBtn) dryrunBtn.addEventListener('click', () => runDryRun(currentActiveBundleId));

        // Solution pack card buttons
        document.querySelectorAll('.wm-btn-preflight').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const bId = e.currentTarget.getAttribute('data-bundle-id');
                if (bId) openPreflightModal(bId);
            });
        });

        // Module Self-test ping buttons
        document.querySelectorAll('.wm-btn-action').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const target = e.currentTarget;
                const slug = target.getAttribute('data-slug');
                const action = target.getAttribute('data-action');

                target.disabled = true;
                try {
                    const res = await apiRequest(`module/${slug}/action`, 'POST', { action: action });
                    showToast(t('actionResult', slug, res.data?.message || t('actionSuccess')), 'success');
                    loadAuditLogs();
                } catch (err) {
                    showToast(t('actionFailed', err.message), 'error');
                } finally {
                    target.disabled = false;
                }
            });
        });
    });

})();
