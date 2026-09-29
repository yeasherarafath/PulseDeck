/**
 * Service request-builder form: repeatable rows, header presets (Tom Select),
 * auth/body visibility, header templates, and Test Request modal.
 * Loaded only on pages with [data-service-form] (see app.js).
 */

const form = document.querySelector('[data-service-form]');

if (form) {
    initRows();
    initHeaderNames(form);
    initAuthPanels();
    initBodyVisibility();
    initTemplates();
    initTestRequest();
}

/* ---------- repeatable rows ---------- */

function initRows() {
    form.addEventListener('click', (event) => {
        const add = event.target.closest('[data-add-row]');
        const remove = event.target.closest('[data-remove-row]');

        if (add) {
            addRow(document.getElementById(add.dataset.target));
        } else if (remove) {
            const container = remove.closest('[data-rows]');
            const row = remove.closest('[data-row]');

            if (container && row && container.querySelectorAll('[data-row]').length > 1) {
                row.remove();
                reindex(container);
            } else if (row) {
                row.querySelectorAll('input[type="text"], input[type="password"], input:not([type]), textarea').forEach((el) => {
                    el.value = '';
                });
                row.querySelectorAll('select').forEach((el) => {
                    el.selectedIndex = 0;
                });
            }
        }
    });
}

function addRow(container, values = {}) {
    if (!container) {
        return null;
    }

    const first = container.querySelector('[data-row]');

    if (!first) {
        return null;
    }

    const row = first.cloneNode(true);

    row.querySelectorAll('input[type="text"], input[type="password"], input:not([type]), textarea').forEach((el) => {
        el.value = '';
    });
    row.querySelectorAll('select').forEach((el) => {
        el.selectedIndex = 0;
    });
    row.removeAttribute('data-secret');

    // Unwrap any Tom Select rendering so the clone gets a fresh instance.
    const wrapper = row.querySelector('.ts-wrapper');

    if (wrapper) {
        const select = wrapper.querySelector('select');

        if (select) {
            select.classList.remove('tomselected', 'ts-hidden-accessible');
            select.removeAttribute('tabindex');
            wrapper.replaceWith(select);
        }
    }

    row.querySelectorAll('.ts-control, .ts-dropdown').forEach((el) => el.remove());

    // Header value cells always restart as a plain text input.
    const valueWrap = row.querySelector('[data-header-value-wrap]');

    if (valueWrap) {
        const name = valueWrap.querySelector('input, select')?.getAttribute('name') ?? 'headers[0][value]';
        valueWrap.innerHTML = `<input type="text" class="form-control" name="${name}" placeholder="value" />`;
    }

    container.appendChild(row);
    reindex(container);

    const nameSelect = row.querySelector('.js-header-name');

    if (nameSelect) {
        enhanceHeaderSelect(nameSelect);
    }

    applyRowValues(row, values);

    return row;
}

function reindex(container) {
    const prefix = container.dataset.prefix;

    container.querySelectorAll('[data-row]').forEach((row, index) => {
        row.querySelectorAll('input, select, textarea').forEach((el) => {
            if (el.name) {
                el.name = el.name.replace(/\[(\d+)\]/, `[${index}]`);
            }
        });

        if (prefix === 'headers' || prefix === 'auth-headers') {
            const wrap = row.querySelector('[data-header-value-wrap] input, [data-header-value-wrap] select');

            if (wrap && !wrap.name) {
                wrap.name = `${prefix}[${index}][value]`;
            }
        }
    });
}

function applyRowValues(row, values) {
    Object.entries(values).forEach(([key, value]) => {
        const field = row.querySelector(`[name$="[${key}]"]`);

        if (field) {
            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

/* ---------- header name selects ---------- */

function initHeaderNames(scope) {
    scope.querySelectorAll('.js-header-name').forEach(enhanceHeaderSelect);
    scope.addEventListener('change', (event) => {
        if (event.target.matches('.js-header-name')) {
            syncHeaderValueInput(event.target);
        }
    });
}

function enhanceHeaderSelect(select) {
    if (select.tomselect || !window.TomSelect) {
        return;
    }

    const instance = new TomSelect(select, { create: true, maxItems: 1 });

    instance.on('change', () => syncHeaderValueInput(select));
}

function syncHeaderValueInput(select) {
    const row = select.closest('[data-row]');
    const wrap = row?.querySelector('[data-header-value-wrap]');

    if (!wrap) {
        return;
    }

    const option = select.selectedOptions[0];
    const kind = option?.dataset.input ?? 'text';
    const sensitive = option?.dataset.sensitive === '1';
    const currentName = (wrap.querySelector('input, select')?.getAttribute('name') ?? '').replace(/\[\d+\]/, ($0) => $0);
    const baseName = currentName || `${row.closest('[data-rows]').dataset.prefix}[0][value]`;

    if (kind === 'select') {
        let options = [];

        try {
            options = JSON.parse(option.dataset.options ?? '[]');
        } catch {
            options = [];
        }

        wrap.innerHTML = `<select class="form-select" name="${baseName}">`
            + `<option value="">Select a value…</option>`
            + options.map((value) => `<option value="${escapeAttr(value)}">${escapeHtml(value)}</option>`).join('')
            + `</select>`;
    } else {
        const secret = row.hasAttribute('data-secret') || sensitive;
        wrap.innerHTML = `<input type="${kind === 'password' ? 'password' : 'text'}" class="form-control" name="${baseName}" placeholder="${secret ? 'Saved value hidden — leave blank to keep' : 'value'}" ${sensitive ? 'autocomplete="new-password"' : ''} />`;
    }

    reindex(row.closest('[data-rows]'));
}

/* ---------- auth + body visibility ---------- */

function initAuthPanels() {
    const radios = form.querySelectorAll('[data-auth-type]');

    const sync = () => {
        const active = form.querySelector('[data-auth-type]:checked')?.dataset.authType ?? 'none';

        form.querySelectorAll('[data-auth-panel]').forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.authPanel !== active);
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', sync));
    sync();
}

function initBodyVisibility() {
    const select = form.querySelector('#f-body-type');
    const textWrap = form.querySelector('#body-text-wrap');
    const fieldsWrap = form.querySelector('#body-fields-wrap');

    if (!select) {
        return;
    }

    const sync = () => {
        const fieldsMode = select.value === 'form' || select.value === 'urlencoded';

        textWrap?.classList.toggle('d-none', fieldsMode);
        fieldsWrap?.classList.toggle('d-none', !fieldsMode);
    };

    select.addEventListener('change', sync);
    sync();
}

/* ---------- header templates ---------- */

function initTemplates() {
    const select = form.querySelector('#header-template');
    const button = form.querySelector('#apply-template');
    const container = form.querySelector('#header-rows');

    if (!select || !button || !container) {
        return;
    }

    button.addEventListener('click', () => {
        if (select.value === '') {
            return;
        }

        const template = (window.__headerTemplates ?? [])[Number(select.value)];

        if (!template) {
            return;
        }

        // Add before removing: addRow() clones the first existing row,
        // so emptying the container first would leave nothing to clone
        // (and the apply would silently wipe the section).
        const previous = [...container.querySelectorAll('[data-row]')];

        Object.entries(template.headers ?? {}).forEach(([name, value]) => {
            addRow(container, { name, value });
        });

        previous.forEach((row) => row.remove());
        reindex(container);
    });
}

/* ---------- test request ---------- */

function initTestRequest() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Document-wide: the result modal (with its own "Test again" button)
    // lives outside [data-service-form], so a form-scoped query would
    // leave that button without a handler.
    document.querySelectorAll('[data-test-request]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.testUrl;

            if (!url) {
                return;
            }

            renderTestResult(`<div class="text-secondary">Running test request…</div>`);
            showTestModal();

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(buildPayload()),
                });

                const contentType = response.headers.get('content-type') ?? '';
                const isJson = contentType.includes('application/json');

                if (response.status === 422 && isJson) {
                    const data = await response.json();
                    renderTestResult(`<div class="alert alert-danger" role="alert"><h4 class="alert-title">Validation failed</h4><ul class="mb-0">${Object.values(data.errors ?? {}).flat().map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul></div>`);

                    return;
                }

                if (response.status === 419) {
                    renderTestResult(`<div class="alert alert-danger" role="alert"><h4 class="alert-title">Session expired</h4><p class="mb-0">Reload the page and try again.</p></div>`);

                    return;
                }

                if (!response.ok || !isJson) {
                    const text = isJson ? '' : (await response.text()).slice(0, 300);
                    renderTestResult(`<div class="alert alert-danger" role="alert"><h4 class="alert-title">Test failed (HTTP ${response.status})</h4><p class="mb-0">${text ? escapeHtml(text) : 'The server did not return a result. Check the application logs.'}</p></div>`);

                    return;
                }

                const data = await response.json();
                renderTestResult(renderOutcome(data));
            } catch (error) {
                renderTestResult(`<div class="alert alert-danger" role="alert">Test failed to run: ${escapeHtml(error.message)}</div>`);
            }
        });
    });
}

function collectRows(containerId) {
    const container = document.getElementById(containerId);
    const rows = [];

    container?.querySelectorAll('[data-row]').forEach((row) => {
        const obj = {};

        row.querySelectorAll('input, select, textarea').forEach((el) => {
            if (!el.name) {
                return;
            }

            const match = el.name.match(/\[([a-z_]+)\]$/);

            if (match) {
                obj[match[1]] = el.type === 'checkbox' ? el.checked : el.value;
            }
        });

        rows.push(obj);
    });

    return rows;
}

function buildPayload() {
    const data = new FormData(form);
    const checked = (name) => form.querySelector(`[name="${name}"]`)?.checked ?? false;

    return {
        name: data.get('name') ?? 'Test service',
        slug: data.get('slug') ?? '',
        description: data.get('description') ?? '',
        group_id: data.get('group_id') || null,
        url: data.get('url') ?? '',
        method: data.get('method') ?? 'GET',
        check_interval: Number(data.get('check_interval') ?? 300),
        timeout: Number(data.get('timeout') ?? 15),
        connect_timeout: Number(data.get('connect_timeout') ?? 5),
        follow_redirects: checked('follow_redirects'),
        max_redirects: Number(data.get('max_redirects') ?? 5),
        verify_ssl: checked('verify_ssl'),
        http_version: data.get('http_version') ?? 'auto',
        user_agent: data.get('user_agent') ?? '',
        query: collectRows('query-rows'),
        headers: collectRows('header-rows'),
        auth: {
            type: form.querySelector('[data-auth-type]:checked')?.value ?? 'none',
            token: data.get('auth[token]') ?? '',
            username: data.get('auth[username]') ?? '',
            password: data.get('auth[password]') ?? '',
            header: data.get('auth[header]') ?? 'X-API-Key',
            key: data.get('auth[key]') ?? '',
            headers: collectRows('auth-headers-rows'),
        },
        body_type: data.get('body_type') ?? 'none',
        body: data.get('body') ?? '',
        body_fields: collectRows('body-fields-rows'),
        expected_statuses: data.get('expected_statuses') ?? '200',
        warn_ms: data.get('warn_ms') || null,
        fail_ms: data.get('fail_ms') || null,
        body_assertions: collectRows('body-assert-rows'),
        json_assertions: collectRows('json-assert-rows'),
        header_assertions: collectRows('header-assert-rows'),
        is_active: checked('is_active'),
        is_public: checked('is_public'),
        sort_order: Number(data.get('sort_order') ?? 0),
    };
}

function renderOutcome(data) {
    if (!data.ok) {
        return `<div class="alert alert-danger" role="alert"><h4 class="alert-title">Request blocked</h4><p class="mb-0">${escapeHtml(data.error ?? 'Unknown error')}</p></div>`;
    }

    const outcome = data.outcome ?? {};
    const assertions = data.assertions ?? { passed: true, failures: [], warnings: [] };
    const resultColors = { operational: 'green', degraded: 'yellow', failed: 'red', maintenance: 'blue', unknown: 'secondary' };
    const color = resultColors[data.result] ?? 'secondary';

    const rows = [
        ['Method / URL', `${escapeHtml(outcome.request?.method ?? '')} ${escapeHtml(outcome.request?.url ?? '')}`],
        ['Requested at', escapeHtml(outcome.requested_at || '—')],
        ['HTTP status', outcome.http_status ?? '—'],
        ['Result', `<span class="badge bg-${color}-lt">${escapeHtml(data.result)}</span>`],
        ['Response time', outcome.response_time_ms != null ? `${outcome.response_time_ms} ms` : '—'],
        ['Connect time', outcome.connect_time_ms != null ? `${outcome.connect_time_ms} ms` : '—'],
        ['Final URL', escapeHtml(outcome.final_url || '—')],
        ['Redirects', outcome.redirect_count ?? 0],
        ['Response size', outcome.response_size != null ? `${outcome.response_size} bytes` : '—'],
    ];

    if (outcome.error) {
        rows.push(['Error', `<span class="text-danger">${escapeHtml(outcome.error)}${outcome.error_message ? ` — ${escapeHtml(outcome.error_message)}` : ''}</span>`]);
    }

    const failures = (assertions.failures ?? []).map((failure) => `
        <li class="mb-2"><span class="badge bg-red-lt me-1">FAIL</span>
        <strong>${escapeHtml(failure.assertion ?? '')}:</strong> ${escapeHtml(failure.message ?? '')}<br />
        <span class="text-secondary">Expected: ${escapeHtml(String(failure.expected ?? ''))} · Actual: ${escapeHtml(String(failure.actual ?? '').slice(0, 300))}</span></li>`).join('');

    const warnings = (assertions.warnings ?? []).map((warning) => `
        <li class="mb-2"><span class="badge bg-yellow-lt me-1">WARN</span>
        <strong>${escapeHtml(warning.assertion ?? '')}:</strong> ${escapeHtml(warning.message ?? '')}</li>`).join('');

    return `<table class="table table-sm">
        <tbody>${rows.map(([key, value]) => `<tr><td class="text-secondary" style="width: 35%">${key}</td><td>${value}</td></tr>`).join('')}</tbody>
        </table>
        <h4>Assertions</h4>
        ${failures || warnings ? `<ul class="ps-3">${failures}${warnings}</ul>` : '<p class="text-green">All assertions passed.</p>'}`;
}

function renderTestResult(html) {
    const target = document.getElementById('test-result');

    if (target) {
        target.innerHTML = html;
    }
}

function showTestModal() {
    const modal = document.getElementById('test-modal');

    if (!modal) {
        return;
    }

    // Open through Tabler's bundled Bootstrap (single Data-API owner).
    let trigger = document.getElementById('test-modal-trigger');

    if (!trigger) {
        trigger = document.createElement('button');
        trigger.id = 'test-modal-trigger';
        trigger.type = 'button';
        trigger.className = 'd-none';
        trigger.setAttribute('data-bs-toggle', 'modal');
        trigger.setAttribute('data-bs-target', '#test-modal');
        document.body.appendChild(trigger);
    }

    trigger.click();

    // Fallback: if no handler opened it, reveal plainly.
    window.setTimeout(() => {
        if (modal.classList.contains('show')) {
            return;
        }

        modal.classList.add('show');
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');

        if (!document.querySelector('.modal-backdrop.fallback')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show fallback';
            backdrop.addEventListener('click', hideTestModalFallback);
            document.body.appendChild(backdrop);
        }

        modal.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
            button.addEventListener('click', hideTestModalFallback, { once: true });
        });
    }, 150);
}

function hideTestModalFallback() {
    const modal = document.getElementById('test-modal');

    modal?.classList.remove('show');
    modal?.style.removeProperty('display');
    document.querySelectorAll('.modal-backdrop.fallback').forEach((el) => el.remove());
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[char]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}
