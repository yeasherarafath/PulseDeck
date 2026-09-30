@extends('layouts.admin-tabler')

@section('page-pretitle', 'Configure')
@section('page-title', 'API documentation')

@php
    $codeBox = 'p-3 rounded border mb-0 small';
    $codeStyle = 'background: var(--tblr-bg-surface-secondary); color: var(--tblr-body-color); white-space: pre; overflow-x: auto;';
    $methodClass = ['GET' => 'bg-green-lt'];

    $samples = function (array $endpoint): array {
        $url = $endpoint['url'];

        return [
            'cURL' => "curl -s \"{$url}\" \\\n  -H \"Accept: application/json\"",
            'JavaScript' => "const response = await fetch(\"{$url}\", {\n  headers: { Accept: \"application/json\" },\n});\n\nif (!response.ok) throw new Error(`HTTP \${response.status}`);\n\nconst { ok, data } = await response.json();\nconsole.log(data);",
            'PHP' => "<?php\n\n\$response = Illuminate\\Support\\Facades\\Http::acceptJson()->get(\"{$url}\");\n\n\$response->throw();\n\n\$data = \$response->json('data');",
            'Python' => "import requests\n\nresponse = requests.get(\"{$url}\", headers={\"Accept\": \"application/json\"}, timeout=10)\nresponse.raise_for_status()\n\ndata = response.json()[\"data\"]",
        ];
    };
@endphp

@section('content')
    @unless ($apiEnabled)
        <div class="alert alert-warning" role="alert">
            The public API is currently <strong>disabled</strong>: every endpoint below returns HTTP 403 until it is enabled in
            @can('status.settings.manage')<a href="{{ route('admin.status.settings') }}#set-public">Settings → Public page</a>@else Settings → Public page @endcan.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-9">
            {{-- Overview --}}
            <div class="card mb-3" id="overview">
                <div class="card-header"><h3 class="card-title">Overview</h3></div>
                <div class="card-body">
                    <p class="text-secondary">
                        A small, read-only JSON API that exposes exactly what the public status page shows. No API key is needed and
                        private services are never included. Responses are cached for about 30 seconds and refreshed immediately when
                        you change a service, group, incident, maintenance window or setting.
                    </p>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Base URL</dt>
                        <dd class="col-sm-9"><code>{{ $base }}</code>
                            <button type="button" class="btn btn-sm btn-ghost-secondary ms-1" data-copy="{{ $base }}">Copy</button></dd>
                        <dt class="col-sm-3">Authentication</dt>
                        <dd class="col-sm-9">None (public). Send <code>Accept: application/json</code>.</dd>
                        <dt class="col-sm-3">Methods</dt>
                        <dd class="col-sm-9">GET only. Other methods return 405.</dd>
                        <dt class="col-sm-3">Rate limit</dt>
                        <dd class="col-sm-9">60 requests per minute per IP. Beyond that the API answers <code>429 Too Many Requests</code> with a <code>Retry-After</code> header.</dd>
                        <dt class="col-sm-3">Enabled</dt>
                        <dd class="col-sm-9">
                            @if ($apiEnabled)<span class="badge bg-green-lt">Enabled</span>@else<span class="badge bg-red-lt">Disabled</span>@endif
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Envelope --}}
            <div class="card mb-3" id="envelope">
                <div class="card-header"><h3 class="card-title">Response envelope &amp; errors</h3></div>
                <div class="card-body">
                    <p class="text-secondary">Every response is JSON with an <code>ok</code> flag. Successful calls carry a <code>data</code> payload; failures carry a <code>message</code>.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="fw-bold mb-1">Success</div>
                            <pre class="{{ $codeBox }}" style="{{ $codeStyle }}">{{ json_encode(['ok' => true, 'data' => '…'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-bold mb-1">Error</div>
                            <pre class="{{ $codeBox }}" style="{{ $codeStyle }}">{{ json_encode(['ok' => false, 'message' => 'Service not found.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>HTTP</th><th>Meaning</th></tr></thead>
                            <tbody>
                                <tr><td><code>200</code></td><td>Success.</td></tr>
                                <tr><td><code>403</code></td><td>The public API is disabled in Settings.</td></tr>
                                <tr><td><code>404</code></td><td>Unknown or private service slug.</td></tr>
                                <tr><td><code>405</code></td><td>Method not allowed (read-only API).</td></tr>
                                <tr><td><code>429</code></td><td>Rate limit exceeded; wait for <code>Retry-After</code> seconds.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Endpoints --}}
            @foreach ($endpoints as $endpoint)
                @php($code = $samples($endpoint))
                <div class="card mb-3" id="{{ $endpoint['id'] }}">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-1">{{ $endpoint['title'] }}</h3>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge {{ $methodClass[$endpoint['method']] ?? 'bg-blue-lt' }}">{{ $endpoint['method'] }}</span>
                                <code>{{ $endpoint['path'] }}</code>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary">{{ $endpoint['description'] }}</p>

                        @if ($endpoint['params'])
                            <div class="fw-bold mb-1">Parameters</div>
                            <div class="table-responsive mb-3">
                                <table class="table table-vcenter card-table">
                                    <thead><tr><th>Name</th><th>In</th><th>Type</th><th>Description</th></tr></thead>
                                    <tbody>
                                        @foreach ($endpoint['params'] as [$name, $in, $type, $desc])
                                            <tr><td><code>{{ $name }}</code></td><td>{{ $in }}</td><td>{{ $type }}</td><td>{{ $desc }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="small text-secondary mb-3">No parameters.</p>
                        @endif

                        <div class="fw-bold mb-1">Request</div>
                        <ul class="nav nav-tabs" role="tablist">
                            @foreach (array_keys($code) as $i => $lang)
                                <li class="nav-item" role="presentation">
                                    <a href="#tab-{{ $endpoint['id'] }}-{{ $i }}" class="nav-link{{ $i === 0 ? ' active' : '' }}" data-bs-toggle="tab" role="tab">{{ $lang }}</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content mb-3">
                            @foreach ($code as $lang => $snippet)
                                @php($i = array_search($lang, array_keys($code), true))
                                <div class="tab-pane{{ $i === 0 ? ' active show' : '' }}" id="tab-{{ $endpoint['id'] }}-{{ $i }}" role="tabpanel">
                                    <div class="position-relative">
                                        <button type="button" class="btn btn-sm btn-ghost-secondary position-absolute top-0 end-0 m-1" data-copy-target="#snippet-{{ $endpoint['id'] }}-{{ $i }}">Copy</button>
                                        <pre class="{{ $codeBox }} border-top-0 rounded-top-0" style="{{ $codeStyle }}" id="snippet-{{ $endpoint['id'] }}-{{ $i }}">{{ $snippet }}</pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="fw-bold mb-1">Example response <span class="badge bg-green-lt ms-1">200</span></div>
                        <pre class="{{ $codeBox }} mb-3" style="{{ $codeStyle }}">{{ json_encode($endpoint['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>

                        @if ($endpoint['errors'])
                            <div class="fw-bold mb-1">Errors</div>
                            <ul class="mb-3">
                                @foreach ($endpoint['errors'] as [$status, $reason])
                                    <li><code>{{ $status }}</code> &mdash; {{ $reason }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-primary btn-sm" data-try="{{ $endpoint['url'] }}" data-target="#live-{{ $endpoint['id'] }}">Try it</button>
                            <span class="text-secondary small">Sends a real request to this server and shows the live response.</span>
                        </div>
                        <div class="mt-2 d-none" id="live-{{ $endpoint['id'] }}">
                            <div class="small mb-1" data-live-meta></div>
                            <pre class="{{ $codeBox }}" style="{{ $codeStyle }} max-height: 22rem;" data-live-body></pre>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Other public endpoints --}}
            <div class="card mb-3" id="other">
                <div class="card-header"><h3 class="card-title">Badge &amp; polling endpoints</h3></div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-green-lt">GET</span><code>/status/badge.svg</code>
                            @if ($badgeEnabled)<span class="badge bg-green-lt">Enabled</span>@else<span class="badge bg-red-lt">Disabled (404)</span>@endif
                        </div>
                        <p class="text-secondary">Embeddable SVG badge with the overall state (green / orange / red / blue / grey). Cached for 30 seconds.</p>
                        <pre class="{{ $codeBox }}" style="{{ $codeStyle }}">{{ '<img src="'.$origin.'/status/badge.svg" alt="Service status">' }}

{{ '![Service status]('.$origin.'/status/badge.svg)' }}</pre>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-green-lt">GET</span><code>/status/refresh</code>
                        </div>
                        <p class="text-secondary">Lightweight payload the public page polls (60 requests per minute). It is not wrapped in the <code>ok/data</code> envelope.</p>
                        <pre class="{{ $codeBox }}" style="{{ $codeStyle }}">{{ json_encode([
                            'status' => 'operational',
                            'status_label' => 'All Systems Operational',
                            'updated_at' => '2026-09-30T09:36:12+00:00',
                            'server_time' => 'Sep 30, 2026 15:36 Asia/Dhaka',
                            'services' => [['slug' => 'main-website', 'status' => 'operational', 'label' => 'Operational']],
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>

            {{-- Reference values --}}
            <div class="card mb-3" id="reference">
                <div class="card-header"><h3 class="card-title">Reference values</h3></div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="fw-bold mb-1">Service <code>status</code></div>
                            <ul class="mb-0">
                                @foreach ($statuses as [$value, $label])
                                    <li><code>{{ $value }}</code> &mdash; {{ $label }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <div class="fw-bold mb-1">Incident <code>status</code></div>
                            <ul class="mb-0">@foreach ($incidentStatuses as $value)<li><code>{{ $value }}</code></li>@endforeach</ul>
                        </div>
                        <div class="col-md-3">
                            <div class="fw-bold mb-1">Incident <code>impact</code></div>
                            <ul class="mb-0">@foreach ($impacts as $value)<li><code>{{ $value }}</code></li>@endforeach</ul>
                        </div>
                    </div>
                    <p class="small text-secondary mt-3 mb-0">All timestamps are ISO&nbsp;8601. <code>uptime_30d.uptime</code> is a percentage (0&ndash;100); it is 100 when no checks exist yet.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-3 d-none d-lg-block">
            <div class="card sticky-top" style="top: 5rem;">
                <div class="card-header"><h3 class="card-title">On this page</h3></div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action" href="#overview">Overview</a>
                    <a class="list-group-item list-group-item-action" href="#envelope">Envelope &amp; errors</a>
                    @foreach ($endpoints as $endpoint)
                        <a class="list-group-item list-group-item-action" href="#{{ $endpoint['id'] }}">{{ $endpoint['title'] }}</a>
                    @endforeach
                    <a class="list-group-item list-group-item-action" href="#other">Badge &amp; polling</a>
                    <a class="list-group-item list-group-item-action" href="#reference">Reference values</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const copy = async (text, button) => {
                try {
                    await navigator.clipboard.writeText(text);
                    const original = button.textContent;
                    button.textContent = 'Copied';
                    setTimeout(() => { button.textContent = original; }, 1200);
                } catch (error) {
                    button.textContent = 'Press Ctrl+C';
                }
            };

            document.querySelectorAll('[data-copy]').forEach((button) => {
                button.addEventListener('click', () => copy(button.dataset.copy, button));
            });

            document.querySelectorAll('[data-copy-target]').forEach((button) => {
                button.addEventListener('click', () => copy(document.querySelector(button.dataset.copyTarget).textContent, button));
            });

            document.querySelectorAll('[data-try]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const box = document.querySelector(button.dataset.target);
                    const meta = box.querySelector('[data-live-meta]');
                    const body = box.querySelector('[data-live-body]');
                    const startedAt = performance.now();

                    box.classList.remove('d-none');
                    meta.textContent = 'Requesting…';
                    body.textContent = '';
                    button.disabled = true;

                    try {
                        const response = await fetch(button.dataset.try, { headers: { Accept: 'application/json' } });
                        const text = await response.text();
                        let pretty = text;

                        try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (error) { /* keep raw */ }

                        meta.textContent = `HTTP ${response.status} · ${Math.round(performance.now() - startedAt)} ms`;
                        body.textContent = pretty;
                    } catch (error) {
                        meta.textContent = 'Request failed';
                        body.textContent = String(error);
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        })();
    </script>
@endpush
