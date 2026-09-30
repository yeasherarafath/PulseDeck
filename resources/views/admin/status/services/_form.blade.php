@php
    use App\Enums\Status\AssertionOperator;
    use App\Enums\Status\AuthType;
    use App\Enums\Status\BodyAssertionType;
    use App\Enums\Status\CheckInterval;
    use App\Enums\Status\HttpMethod;
    use App\Enums\Status\HttpVersion;
    use App\Enums\Status\RequestBodyType;

    $authType = old('auth.type', $authData['type'] ?? 'none');
    $bodyType = old('body_type', $service?->request_body_type?->value ?? 'none');
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <h4 class="alert-title">Please fix {{ $errors->count() }} error(s)</h4>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" data-service-form novalidate>
    @csrf
    @if (($formMethod ?? 'POST') !== 'POST')
        @method($formMethod)
    @endif

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" data-remember-tab role="tablist">
                <li class="nav-item" role="presentation"><a href="#tab-general" class="nav-link active" data-bs-toggle="tab" role="tab">General</a></li>
                <li class="nav-item" role="presentation"><a href="#tab-request" class="nav-link" data-bs-toggle="tab" role="tab">Request</a></li>
                <li class="nav-item" role="presentation"><a href="#tab-auth" class="nav-link" data-bs-toggle="tab" role="tab">Auth</a></li>
                <li class="nav-item" role="presentation"><a href="#tab-assertions" class="nav-link" data-bs-toggle="tab" role="tab">Assertions</a></li>
                <li class="nav-item" role="presentation"><a href="#tab-advanced" class="nav-link" data-bs-toggle="tab" role="tab">Advanced</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- GENERAL --}}
                <div class="tab-pane active show" id="tab-general" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="f-name">Service name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="f-name" name="name" value="{{ old('name', $service?->name) }}" required />
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-slug">Slug (auto-generated)</label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="f-slug" name="slug" value="{{ old('slug', $service?->slug) }}" placeholder="my-service" />
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="f-description">Description</label>
                            <textarea class="form-control" id="f-description" name="description" rows="2">{{ old('description', $service?->description) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-group">Group</label>
                            <select class="form-select" id="f-group" name="group_id">
                                <option value="">No group</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected((string) old('group_id', $service?->group_id ?? '') === (string) $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="f-url">URL</label>
                            <input type="url" class="form-control @error('url') is-invalid @enderror" id="f-url" name="url" value="{{ old('url', $service?->url) }}" placeholder="https://example.com/health" required />
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="f-method">Method</label>
                            <select class="form-select" id="f-method" name="method">
                                @foreach (HttpMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('method', $service?->method?->value ?? 'GET') === $method->value)>{{ $method->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="f-interval">Check interval</label>
                            <select class="form-select" id="f-interval" name="check_interval">
                                @foreach (CheckInterval::cases() as $interval)
                                    <option value="{{ $interval->value }}" @selected((int) old('check_interval', $service?->check_interval ?? (CheckInterval::tryFrom((int) setting('default_check_interval', 300))?->value ?? 300)) === $interval->value)>{{ $interval->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="f-sort">Sort order</label>
                            <input type="number" class="form-control" id="f-sort" name="sort_order" value="{{ old('sort_order', $service?->sort_order ?? 0) }}" min="0" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $service?->is_active ?? true)) />
                                <span class="form-check-label">Active (monitored by scheduler)</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="is_public" value="1" @checked(old('is_public', $service?->is_public ?? true)) />
                                <span class="form-check-label">Public (shown on status page)</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- REQUEST --}}
                <div class="tab-pane" id="tab-request" role="tabpanel">
                    <h3 class="card-title">Query parameters</h3>
                    <div id="query-rows" data-rows data-prefix="query">
                        @foreach ($queryRows as $i => $row)
                            <div class="row g-2 mb-2" data-row>
                                <div class="col-md-5"><input type="text" class="form-control" name="query[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="page" /></div>
                                <div class="col-md-6"><input type="text" class="form-control" name="query[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="1" /></div>
                                <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm mb-4" data-add-row data-target="query-rows">+ Add parameter</button>

                    <h3 class="card-title">Headers</h3>
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <select class="form-select" id="header-template">
                                <option value="">Apply a template…</option>
                                @foreach ($templates as $index => $template)
                                    <option value="{{ $index }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn" id="apply-template">Apply</button>
                        </div>
                    </div>
                    <div id="header-rows" data-rows data-prefix="headers">
                        @foreach ($headerRows as $i => $row)
                            <div class="row g-2 mb-2" data-row data-header-row @if(!empty($row['secret'])) data-secret="1" @endif>
                                <div class="col-md-5">
                                    <select class="form-select js-header-name" name="headers[{{ $i }}][name]" data-tom-header>
                                        <option value="">Select a header…</option>
                                        @foreach ($presets as $category => $group)
                                            <optgroup label="{{ \App\Enums\Status\HeaderPresetCategory::tryFrom($category)?->label() ?? $category }}">
                                                @foreach ($group as $preset)
                                                    <option value="{{ $preset->header_name }}"
                                                        data-input="{{ $preset->input_type }}"
                                                        data-sensitive="{{ $preset->is_sensitive ? '1' : '0' }}"
                                                        data-options="{{ json_encode($preset->options ?? []) }}"
                                                        @selected(($row['name'] ?? '') === $preset->header_name)>{{ $preset->header_name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                        @if (!empty($row['name']) && !$presets->flatten()->contains(fn ($p) => $p->header_name === $row['name']))
                                            <option value="{{ $row['name'] }}" selected>{{ $row['name'] }} (custom)</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-6" data-header-value-wrap>
                                    <input type="text" class="form-control" name="headers[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}"
                                        @if(!empty($row['secret'])) placeholder="Saved value hidden — leave blank to keep" @else placeholder="value" @endif />
                                </div>
                                <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm mb-4" data-add-row data-target="header-rows">+ Add header</button>

                    <h3 class="card-title">Request body</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="f-body-type">Body type</label>
                            <select class="form-select" id="f-body-type" name="body_type">
                                @foreach (RequestBodyType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected($bodyType === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2" id="body-text-wrap">
                        <label class="form-label" for="f-body">Body</label>
                        <textarea class="form-control font-monospace" id="f-body" name="body" rows="6" placeholder='{"key": "value"}'>{{ old('body', $service?->request_body) }}</textarea>
                        @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mt-2 d-none" id="body-fields-wrap">
                        <div id="body-fields-rows" data-rows data-prefix="body_fields">
                            @foreach ($bodyFields as $i => $row)
                                <div class="row g-2 mb-2" data-row>
                                    <div class="col-md-5"><input type="text" class="form-control" name="body_fields[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="field" /></div>
                                    <div class="col-md-6"><input type="text" class="form-control" name="body_fields[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="value" /></div>
                                    <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-ghost-primary btn-sm" data-add-row data-target="body-fields-rows">+ Add field</button>
                    </div>
                </div>

                {{-- AUTH --}}
                <div class="tab-pane" id="tab-auth" role="tabpanel">
                    <div class="mb-3">
                        @foreach (AuthType::cases() as $type)
                            <label class="form-check form-check-inline">
                                <input type="radio" class="form-check-input" name="auth[type]" value="{{ $type->value }}" data-auth-type="{{ $type->value }}" @checked($authType === $type->value) />
                                <span class="form-check-label">{{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="row g-3" data-auth-panel="bearer">
                        <div class="col-md-6">
                            <label class="form-label" for="f-token">Token @if(!empty($authData['token_kept']))<span class="badge bg-blue-lt ms-1">saved — leave blank to keep</span>@endif</label>
                            <input type="password" class="form-control" id="f-token" name="auth[token]" value="" placeholder="••••••••" autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="row g-3" data-auth-panel="basic">
                        <div class="col-md-6">
                            <label class="form-label" for="f-username">Username</label>
                            <input type="text" class="form-control" id="f-username" name="auth[username]" value="{{ old('auth.username', $authData['username'] ?? '') }}" autocomplete="off" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-password">Password @if(!empty($authData['password_kept']))<span class="badge bg-blue-lt ms-1">saved — leave blank to keep</span>@endif</label>
                            <input type="password" class="form-control" id="f-password" name="auth[password]" value="" placeholder="••••••••" autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="row g-3" data-auth-panel="api_key">
                        <div class="col-md-6">
                            <label class="form-label" for="f-key-header">Header name</label>
                            <input type="text" class="form-control" id="f-key-header" name="auth[header]" value="{{ old('auth.header', $authData['header'] ?? 'X-API-Key') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-key">API key @if(!empty($authData['key_kept']))<span class="badge bg-blue-lt ms-1">saved — leave blank to keep</span>@endif</label>
                            <input type="password" class="form-control" id="f-key" name="auth[key]" value="" placeholder="••••••••" autocomplete="new-password" />
                        </div>
                    </div>
                    <div data-auth-panel="custom">
                        <p class="text-secondary">Custom authentication headers (values are encrypted; blanks keep stored secrets).</p>
                        <div id="auth-headers-rows" data-rows data-prefix="auth-headers">
                            @foreach (old('auth.headers', $authData['headers'] ?? [['name' => '', 'value' => '']]) as $i => $row)
                                <div class="row g-2 mb-2" data-row>
                                    <div class="col-md-5"><input type="text" class="form-control" name="auth[headers][{{ $i }}][name]" value="{{ is_array($row) ? ($row['name'] ?? '') : '' }}" placeholder="X-Custom-Auth" /></div>
                                    <div class="col-md-6"><input type="password" class="form-control" name="auth[headers][{{ $i }}][value]" value="" @if(is_array($row) && !empty($row['secret'])) placeholder="Saved value hidden — leave blank to keep" @else placeholder="value" @endif autocomplete="new-password" /></div>
                                    <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-ghost-primary btn-sm" data-add-row data-target="auth-headers-rows">+ Add header</button>
                    </div>
                    <div data-auth-panel="none">
                        <p class="text-secondary mb-0">No authentication — the request is sent without credentials.</p>
                    </div>
                </div>

                {{-- ASSERTIONS --}}
                <div class="tab-pane" id="tab-assertions" role="tabpanel">
                    <h3 class="card-title">HTTP status</h3>
                    <div class="mb-3">
                        <label class="form-label" for="f-statuses">Expected status codes (comma separated)</label>
                        <input type="text" class="form-control @error('expected_statuses') is-invalid @enderror" id="f-statuses" name="expected_statuses" value="{{ $expectedStatuses }}" placeholder="200, 201" />
                        @error('expected_statuses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <h3 class="card-title">Response time</h3>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="f-warn">Warning threshold (ms, optional)</label>
                            <input type="number" class="form-control" id="f-warn" name="warn_ms" value="{{ old('warn_ms', $service?->response_time_warning) }}" min="1" placeholder="1000" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-fail">Failure threshold (ms, optional)</label>
                            <input type="number" class="form-control" id="f-fail" name="fail_ms" value="{{ old('fail_ms', $service?->response_time_failure) }}" min="1" placeholder="3000" />
                        </div>
                    </div>
                    <h3 class="card-title">Response body</h3>
                    <div id="body-assert-rows" data-rows data-prefix="body_assertions">
                        @foreach ($bodyAssertions as $i => $row)
                            <div class="row g-2 mb-2" data-row>
                                <div class="col-md-4">
                                    <select class="form-select" name="body_assertions[{{ $i }}][type]">
                                        @foreach (BodyAssertionType::cases() as $type)
                                            <option value="{{ $type->value }}" @selected(($row['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-7"><input type="text" class="form-control" name="body_assertions[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder='"status":"ok"' /></div>
                                <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm mb-4" data-add-row data-target="body-assert-rows">+ Add body assertion</button>

                    <h3 class="card-title">JSON</h3>
                    <div id="json-assert-rows" data-rows data-prefix="json_assertions">
                        @foreach ($jsonAssertions as $i => $row)
                            <div class="row g-2 mb-2" data-row>
                                <div class="col-md-4"><input type="text" class="form-control font-monospace" name="json_assertions[{{ $i }}][path]" value="{{ $row['path'] ?? '' }}" placeholder="$.status" /></div>
                                <div class="col-md-3">
                                    <select class="form-select" name="json_assertions[{{ $i }}][operator]">
                                        @foreach (AssertionOperator::cases() as $op)
                                            <option value="{{ $op->value }}" @selected(($row['operator'] ?? '') === $op->value)>{{ $op->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4"><input type="text" class="form-control" name="json_assertions[{{ $i }}][expected]" value="{{ $row['expected'] ?? '' }}" placeholder="ok" /></div>
                                <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm mb-4" data-add-row data-target="json-assert-rows">+ Add JSON assertion</button>

                    <h3 class="card-title">Response headers</h3>
                    <div id="header-assert-rows" data-rows data-prefix="header_assertions">
                        @foreach ($headerAssertions as $i => $row)
                            <div class="row g-2 mb-2" data-row>
                                <div class="col-md-4"><input type="text" class="form-control" name="header_assertions[{{ $i }}][header]" value="{{ $row['header'] ?? '' }}" placeholder="Content-Type" /></div>
                                <div class="col-md-3">
                                    <select class="form-select" name="header_assertions[{{ $i }}][operator]">
                                        @foreach (['equals' => 'Equals', 'not_equals' => 'Not equals', 'contains' => 'Contains', 'not_contains' => 'Does not contain', 'exists' => 'Exists', 'not_exists' => 'Does not exist'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['operator'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4"><input type="text" class="form-control" name="header_assertions[{{ $i }}][expected]" value="{{ $row['expected'] ?? '' }}" placeholder="application/json" /></div>
                                <div class="col-md-1"><button type="button" class="btn btn-icon btn-ghost-danger" data-remove-row aria-label="Remove row">&times;</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-ghost-primary btn-sm" data-add-row data-target="header-assert-rows">+ Add header assertion</button>
                </div>

                {{-- ADVANCED --}}
                <div class="tab-pane" id="tab-advanced" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="f-timeout">Request timeout (seconds)</label>
                            <input type="number" class="form-control" id="f-timeout" name="timeout" value="{{ old('timeout', $service?->timeout ?? setting('default_timeout', 15)) }}" min="1" max="60" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="f-ctimeout">Connection timeout (seconds)</label>
                            <input type="number" class="form-control" id="f-ctimeout" name="connect_timeout" value="{{ old('connect_timeout', $service?->connect_timeout ?? setting('default_connect_timeout', 5)) }}" min="1" max="60" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="follow_redirects" value="1" @checked(old('follow_redirects', $service?->follow_redirects ?? true)) />
                                <span class="form-check-label">Follow redirects</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-maxred">Maximum redirects</label>
                            <input type="number" class="form-control" id="f-maxred" name="max_redirects" value="{{ old('max_redirects', $service?->max_redirects ?? 5) }}" min="0" max="20" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-mindown">Min failures before down</label>
                            <input type="number" class="form-control" id="f-mindown" name="min_failed_checks_down" value="{{ old('min_failed_checks_down', $service?->min_failed_checks_down) }}" min="1" max="100" placeholder="Global default ({{ $globalMinDown ?? 1 }})" />
                            <div class="form-hint">Consecutive failed checks before this service shows as down. Blank uses the global default.</div>
                            @error('min_failed_checks_down')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-failth">Failures to open incident</label>
                            <input type="number" class="form-control" id="f-failth" name="failure_threshold" value="{{ old('failure_threshold', $service?->failure_threshold ?? setting('failure_threshold', 3)) }}" min="1" max="100" required />
                            <div class="form-hint">Consecutive failures before an incident auto-opens.</div>
                            @error('failure_threshold')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-recth">Recoveries to resolve incident</label>
                            <input type="number" class="form-control" id="f-recth" name="recovery_threshold" value="{{ old('recovery_threshold', $service?->recovery_threshold ?? setting('recovery_threshold', 2)) }}" min="1" max="100" required />
                            <div class="form-hint">Consecutive successes before an incident auto-resolves.</div>
                            @error('recovery_threshold')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="verify_ssl" value="1" @checked(old('verify_ssl', $service?->verify_ssl ?? true)) />
                                <span class="form-check-label">Verify SSL certificate</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="f-httpver">HTTP version</label>
                            <select class="form-select" id="f-httpver" name="http_version">
                                @foreach (HttpVersion::cases() as $version)
                                    <option value="{{ $version->value }}" @selected(old('http_version', $service?->http_version?->value ?? 'auto') === $version->value)>{{ $version->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="f-ua">Custom User-Agent (optional)</label>
                            <input type="text" class="form-control" id="f-ua" name="user_agent" value="{{ old('user_agent', $service?->user_agent) }}" placeholder="StatusMonitor/1.0" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="notify_on_failure" value="1" @checked(old('notify_on_failure', $service?->notify_on_failure ?? true)) />
                                <span class="form-check-label">Notify on failure</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="notify_on_recovery" value="1" @checked(old('notify_on_recovery', $service?->notify_on_recovery ?? true)) />
                                <span class="form-check-label">Notify on recovery</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
            @if ($canTest)
                <button type="button" class="btn" data-test-request data-test-url="{{ $testUrl }}">Test request</button>
            @endif
            <a href="{{ route('admin.status.services.index') }}" class="btn btn-link">Cancel</a>
            @if (!empty($service?->id))
                @can('status.incidents.create')
                    <a href="{{ route('admin.status.incidents.create', ['service' => $service->id]) }}" class="btn btn-link text-danger ms-auto">Open incident</a>
                @endcan
            @endif
        </div>
    </div>
</form>

<div class="modal modal-blur fade" id="test-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="test-result">
                <div class="text-secondary">Run the test to see the live request result here.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" data-test-request data-test-url="{{ $testUrl ?? '' }}">Test again</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.__headerTemplates = @json($templates->map(fn ($t) => ['name' => $t->name, 'headers' => $t->headers ?? []])->values());
</script>
