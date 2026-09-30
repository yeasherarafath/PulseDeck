@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Settings')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.status.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_form" value="1" />
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    @foreach ($grouped as $group => $data)
                        <li class="nav-item" role="presentation">
                            <a href="#set-{{ $group }}" class="nav-link{{ $loop->first ? ' active' : '' }}" data-bs-toggle="tab" role="tab">{{ $data['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    @foreach ($grouped as $group => $data)
                        <div class="tab-pane{{ $loop->first ? ' active show' : '' }}" id="set-{{ $group }}" role="tabpanel">
                            @foreach ($data['settings'] as $setting)
                                <div class="mb-3">
                                    <label class="form-label" for="set-{{ $setting->key }}">
                                        {{ ucwords(str_replace('_', ' ', $setting->key)) }}
                                        @if ($setting->is_encrypted)
                                            <span class="badge bg-yellow-lt ms-1">secret</span>
                                        @endif
                                    </label>
                                    @if ($setting->type->value === 'boolean')
                                        <div>
                                            <label class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]" value="1" @checked((bool) old('settings.'.$setting->key, $setting->value)) />
                                                <span class="form-check-label">{{ $setting->description }}</span>
                                            </label>
                                        </div>
                                    @elseif ($setting->is_encrypted)
                                        <input type="password" class="form-control" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]" value="" placeholder="Saved value hidden — leave blank to keep" autocomplete="new-password" />
                                        <label class="form-check mt-1">
                                            <input type="checkbox" class="form-check-input" name="clear_secrets[{{ $setting->key }}]" value="1" />
                                            <span class="form-check-label">Clear stored value</span>
                                        </label>
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }}</div>@endif
                                    @elseif (in_array($setting->key, ['theme_default'], true))
                                        <select class="form-select" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]">
                                            <option value="light" @selected(old('settings.'.$setting->key, $setting->value) === 'light')>Light</option>
                                            <option value="dark" @selected(old('settings.'.$setting->key, $setting->value) === 'dark')>Dark</option>
                                        </select>
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }}</div>@endif
                                    @elseif (in_array($setting->key, ['timezone'], true))
                                        <select class="form-select" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]">
                                            @foreach ($timezones as $region => $zones)
                                                <optgroup label="{{ $region }}">
                                                    @foreach ($zones as $zone)
                                                        <option value="{{ $zone }}" @selected(old('settings.'.$setting->key, $setting->value) === $zone)>{{ str_replace('_', ' ', $zone === $region ? $zone : substr($zone, strlen($region) + 1)) }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }} (PHP timezone; public times render in it.)</div>@endif
                                    @elseif (in_array($setting->key, ['mail_mailer'], true))
                                        <select class="form-select" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]">
                                            @foreach (['smtp', 'sendmail', 'log'] as $option)
                                                <option value="{{ $option }}" @selected(old('settings.'.$setting->key, $setting->value) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }}</div>@endif
                                    @elseif (in_array($setting->key, ['mail_encryption'], true))
                                        <select class="form-select" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]">
                                            @foreach (['tls', 'ssl', 'none'] as $option)
                                                <option value="{{ $option }}" @selected(old('settings.'.$setting->key, $setting->value) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }}</div>@endif
                                    @elseif (in_array($setting->key, ['logo_path', 'logo_dark_path', 'favicon_path'], true))
                                        @include('admin.status.settings._branding-field', ['setting' => $setting])
                                    @else
                                        <input type="{{ $setting->type->value === 'integer' ? 'number' : 'text' }}" class="form-control" id="set-{{ $setting->key }}" name="settings[{{ $setting->key }}]" value="{{ old('settings.'.$setting->key, $setting->value) }}" />
                                        @if ($setting->description)<div class="form-hint">{{ $setting->description }}</div>@endif
                                    @endif
                                    @error('settings.'.$setting->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                            @if ($group === 'general')
                                <div class="alert alert-info" role="alert">
                                    Changing the admin prefix moves the admin panel (e.g. <code>backend/status</code>).
                                    Bookmarks and login redirects follow automatically; clear any route cache afterwards.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save settings</button>
                <span class="text-secondary ms-2">Mail status: {{ $mailConfigured ? 'configured' : 'not configured' }}</span>
            </div>
        </div>
    </form>

    <div class="row row-deck row-cards mt-3">
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.status.settings.test-mail') }}">
                @csrf
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Send test email</h3></div>
                    <div class="card-body">
                        <label class="form-label required" for="test-email">Recipient</label>
                        <input type="email" class="form-control" id="test-email" name="email" required placeholder="you@example.com" />
                    </div>
                    <div class="card-footer"><button type="submit" class="btn">Send test email</button></div>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.status.settings.test-webhook') }}">
                @csrf
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Send test webhook</h3></div>
                    <div class="card-body">
                        <label class="form-label" for="test-url">Endpoint (blank = default)</label>
                        <input type="url" class="form-control" id="test-url" name="url" placeholder="https://hooks.example.com/…" />
                    </div>
                    <div class="card-footer"><button type="submit" class="btn">Send test webhook</button></div>
                </div>
            </form>
        </div>
    </div>
@endsection
