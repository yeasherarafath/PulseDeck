@props(['href' => '/', 'dot' => true])
@php
    $appName = setting('app_name', config('app.name', 'Status'));
    $logo = branding_asset(setting('logo_path'));
    $logoDark = branding_asset(setting('logo_dark_path'));
@endphp
<a href="{{ $href }}" class="navbar-brand navbar-brand-autodark">
    @if ($logo)
        <img src="{{ $logo }}" class="brand-logo brand-logo-light" alt="{{ $appName }}" />
        <img src="{{ $logoDark ?? $logo }}" class="brand-logo brand-logo-dark" alt="{{ $appName }}" />
    @else
        @if ($dot)<span class="status-dot status-dot-animated bg-green me-1"></span>@endif
        {{ $appName }}
    @endif
</a>
