/**
 * Admin module realtime polling (inventory, products, suppliers, users, activity logs).
 * Expects window.__ADMIN_MODULE_REALTIME__ from the page template.
 */
(function () {
    'use strict';

    const POLL_MS = 4000;
    let pollTimer = null;
    let pollingActive = false;

    function getConfig() {
        return window.__ADMIN_MODULE_REALTIME__ || null;
    }

    function formatMoney(value) {
        const n = Number(value) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateInventoryStats(stats, extra) {
        const map = {
            'stat-total-items': stats?.total_items ?? 0,
            'stat-low-stock': stats?.low_stock_count ?? 0,
            'stat-out-of-stock': stats?.out_of_stock_count ?? 0,
            'stat-total-value': formatMoney(stats?.total_value ?? 0),
        };
        Object.keys(map).forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = map[id];
            }
        });
        const expiringEl = document.getElementById('stat-expiring-count');
        if (expiringEl && extra?.expiringCount !== undefined) {
            expiringEl.textContent = String(extra.expiringCount);
        }
    }

    function replaceTableBody(cfg, html) {
        const tbody = getTableBody(cfg);
        if (!tbody || typeof html !== 'string') {
            return;
        }
        tbody.innerHTML = html;
        document.dispatchEvent(new CustomEvent('admin-realtime:updated', { detail: { module: cfg.module } }));
        if (cfg.reinitTable && typeof window[cfg.reinitTable] === 'function') {
            window[cfg.reinitTable]();
        }
    }

    function applyPayload(cfg, data) {
        if (!data || !data.success) {
            return;
        }

        if (cfg.module === 'inventory' && data.stats) {
            updateInventoryStats(data.stats, {
                expiringCount: data.expiringCount,
            });
        }

        if (cfg.module === 'activity_logs') {
            const totalEl = document.getElementById('activity-logs-total-count');
            if (totalEl && data.totalCount !== undefined) {
                totalEl.textContent = String(data.totalCount);
            }
        }

        if (data.rowsHtml) {
            replaceTableBody(cfg, data.rowsHtml);
        }

        if (data.latestId !== undefined) {
            cfg.afterId = data.latestId;
        }

        const totalUsersEl = document.getElementById('users-total-count');
        if (totalUsersEl && data.totalUsers !== undefined) {
            totalUsersEl.textContent = String(data.totalUsers);
        }
    }

    function buildPollUrl(cfg) {
        const url = new URL(cfg.pollUrl, window.location.origin);
        if (cfg.queryParams) {
            Object.keys(cfg.queryParams).forEach(function (key) {
                const val = cfg.queryParams[key];
                if (val !== null && val !== undefined && val !== '') {
                    url.searchParams.set(key, val);
                }
            });
        }
        return url.toString();
    }

    async function pollOnce(cfg) {
        try {
            const res = await fetch(buildPollUrl(cfg), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            applyPayload(cfg, data);
        } catch (e) {
            // silent — polling fallback
        }
    }

    function startPolling() {
        const cfg = getConfig();
        if (!cfg || !cfg.pollUrl || !cfg.tableSelector) {
            return;
        }
        if (pollingActive) {
            return;
        }
        pollingActive = true;

        const tick = function () {
            pollOnce(cfg);
        };

        tick();
        pollTimer = window.setInterval(tick, POLL_MS);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            } else if (!pollTimer) {
                tick();
                pollTimer = window.setInterval(tick, POLL_MS);
            }
        });
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        pollingActive = false;
    }

    document.addEventListener('DOMContentLoaded', startPolling);
    document.addEventListener('turbo:load', function () {
        stopPolling();
        startPolling();
    });
    document.addEventListener('turbo:before-cache', stopPolling);
})();
