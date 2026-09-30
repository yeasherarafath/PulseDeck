/**
 * Public status page: live polling + response-time chart.
 * Loaded only where needed (see app.js).
 */

const pollRoot = document.querySelector('[data-status-poll]');

if (pollRoot) {
    initPolling(pollRoot);
}

const chartEl = document.getElementById('response-chart');

if (chartEl) {
    initChart(chartEl);
}

/* ---------- polling ---------- */

const STATUS_COLORS = {
    operational: 'green',
    degraded: 'yellow',
    partial_outage: 'orange',
    major_outage: 'red',
    maintenance: 'blue',
    unknown: 'secondary',
};

const STATUS_LABELS = {
    operational: 'Operational',
    degraded: 'Degraded Performance',
    partial_outage: 'Partial Outage',
    major_outage: 'Major Outage',
    maintenance: 'Scheduled Maintenance',
    unknown: 'Unknown',
};

function initPolling(root) {
    const seconds = Math.max(15, Number(root.dataset.refreshSeconds ?? 45));

    setInterval(async () => {
        try {
            const response = await fetch(root.dataset.refreshUrl, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const label = document.getElementById('overall-label');

            if (label) {
                label.textContent = data.status_label;
            }

            const serverTime = document.getElementById('server-time');

            if (serverTime && data.server_time) {
                serverTime.textContent = data.server_time;
            }

            const updated = document.getElementById('overall-updated');

            if (updated && data.updated_human) {
                updated.textContent = data.updated_human;
            }

            const dot = document.querySelector('#overall-dot .status-dot');

            if (dot) {
                dot.className = `status-dot mb-3 ${data.status === 'operational' ? 'status-dot-animated' : ''} bg-${STATUS_COLORS[data.status] ?? 'secondary'}`;
                dot.style.cssText = 'width: 1rem; height: 1rem;';
            }

            (data.services ?? []).forEach((service) => {
                const row = root.querySelector(`[data-service-row="${service.slug}"] .svc-badge`);

                if (row) {
                    const color = STATUS_COLORS[service.status] ?? 'secondary';
                    const text = STATUS_LABELS[service.status] ?? service.status;
                    const badge = document.createElement('span');
                    const dotEl = document.createElement('span');

                    badge.className = `badge bg-${color}-lt`;
                    dotEl.className = `status-dot bg-${color} me-1`;
                    badge.append(dotEl, document.createTextNode(text));
                    row.replaceChildren(badge);
                }
            });
        } catch {
            // Polling is best-effort; the next tick retries.
        }
    }, seconds * 1000);
}

/* ---------- response-time chart ---------- */

async function initChart(el) {
    const { default: ApexCharts } = await import('apexcharts');

    let samples = [];

    try {
        samples = JSON.parse(el.dataset.samples ?? '[]');
    } catch {
        samples = [];
    }

    const dark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';

    const options = () => ({
        chart: { type: 'line', height: 220, toolbar: { show: false }, animations: { enabled: false } },
        series: [{ name: 'Response ms', data: samples.map((point) => ({ x: new Date(point.t).getTime(), y: point.ms })) }],
        xaxis: { type: 'datetime', labels: { style: { colors: dark() ? '#9ca3af' : '#6b7280' } } },
        yaxis: { title: { text: 'ms' }, labels: { style: { colors: dark() ? '#9ca3af' : '#6b7280' } } },
        grid: { borderColor: dark() ? '#374151' : '#e5e7eb' },
        tooltip: { theme: dark() ? 'dark' : 'light', x: { format: 'MMM d, HH:mm' } },
        stroke: { width: 2 },
    });

    const chart = new ApexCharts(el, options());
    chart.render();

    document.addEventListener('theme:changed', () => {
        chart.updateOptions(options());
    });
}
