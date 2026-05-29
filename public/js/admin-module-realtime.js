/**
 * Admin module realtime polling (inventory, products, suppliers, users, activity logs).
 * Loaded once from admin/layout; each page sets window.__ADMIN_MODULE_REALTIME__ before this runs.
 */
(function () {
    'use strict';

    const DEFAULT_POLL_MS = 2000;
    let pollTimer = null;
    let mercureDebounce = null;
    let visibilityHandler = null;
    let moduleUpdatedHandler = null;

    function getConfig() {
        return window.__ADMIN_MODULE_REALTIME__ || null;
    }

    function getPollMs(cfg) {
        const ms = Number(cfg.pollMs);
        return ms > 0 ? ms : DEFAULT_POLL_MS;
    }

    function formatMoney(value) {
        const n = Number(value) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getTableBody(cfg) {
        const table = document.querySelector(cfg.tableSelector);
        if (!table) {
            return null;
        }
        return table.querySelector('tbody');
    }

    function destroyDataTable(cfg) {
        if (!cfg.reinitTable || !window.jQuery) {
            return;
        }
        const $ = window.jQuery;
        const sel = cfg.tableSelector;
        if ($.fn.DataTable && $.fn.DataTable.isDataTable(sel)) {
            $(sel).DataTable().destroy();
        }
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
        destroyDataTable(cfg);
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

        if (data.rowsHtml && document.querySelector(cfg.tableSelector)) {
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
                cache: 'no-store',
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            applyPayload(cfg, data);
        } catch (e) {
            // polling fallback — ignore transient errors
        }
    }

    function scheduleMercureRefresh(cfg) {
        if (mercureDebounce) {
            clearTimeout(mercureDebounce);
        }
        mercureDebounce = setTimeout(function () {
            pollOnce(cfg);
        }, 200);
    }

    function onModuleUpdated(event) {
        const cfg = getConfig();
        if (!cfg || !event.detail) {
            return;
        }
        if (event.detail.module === cfg.module) {
            scheduleMercureRefresh(cfg);
        }
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (mercureDebounce) {
            clearTimeout(mercureDebounce);
            mercureDebounce = null;
        }
        if (moduleUpdatedHandler) {
            window.removeEventListener('admin:module_updated', moduleUpdatedHandler);
            moduleUpdatedHandler = null;
        }
        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
    }

    function startPolling() {
        stopPolling();

        const cfg = getConfig();
        if (!cfg || !cfg.pollUrl || !cfg.tableSelector) {
            return;
        }

        const interval = getPollMs(cfg);
        const tick = function () {
            pollOnce(cfg);
        };

        tick();
        pollTimer = window.setInterval(tick, interval);

        moduleUpdatedHandler = onModuleUpdated;
        window.addEventListener('admin:module_updated', moduleUpdatedHandler);

        visibilityHandler = function () {
            if (document.hidden) {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            } else if (!pollTimer) {
                tick();
                pollTimer = window.setInterval(tick, interval);
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);
    }

    function boot() {
        startPolling();
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
    document.addEventListener('turbo:before-cache', stopPolling);

    if (document.readyState !== 'loading') {
        boot();
    }

    window.AdminModuleRealtime = { boot: boot, stop: stopPolling };
})();
