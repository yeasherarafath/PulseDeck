<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-default="light" data-bs-navbar-position="vertical">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="color-scheme" content="light dark" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="description" content="@yield('meta-description', config('app.name') . ' administration')" />
    <title>@yield('title', config('app.name') . ' Admin')</title>
    <script>
        // Pre-paint theme application (avoids a flash of the wrong theme).
        // Mirrors resources/js/theme.js getTheme().
        (function () {
            try {
                var stored = localStorage.getItem('status-theme');
                var theme = stored === 'dark' || stored === 'light' ? stored : 'light';
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    @vite('resources/css/app.scss')
</head>
<body>
    <div class="page">
        <aside class="navbar navbar-vertical navbar-expand-lg">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="{{ route('admin.status.dashboard') }}">
                        <span class="status-dot status-dot-animated bg-green me-1"></span>
                        {{ config('app.name', 'Status') }}
                    </a>
                </h1>
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">
                        <li class="nav-section-title">Monitor</li>
                        <li class="nav-item">
                            <a class="nav-link{{ request()->routeIs('admin.status.dashboard') ? ' active' : '' }}" href="{{ route('admin.status.dashboard') }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                                </span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>
                        @can('status.services.view')
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.services.*') ? ' active' : '' }}" href="{{ route('admin.status.services.index') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12h4l3 8 4 -16 3 8h4" /></svg>
                                    </span>
                                    <span class="nav-link-title">Services</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.groups.*') ? ' active' : '' }}" href="{{ route('admin.status.groups.index') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-11a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2" /></svg>
                                    </span>
                                    <span class="nav-link-title">Groups</span>
                                </a>
                            </li>
                        @endcan
                        @can('status.monitoring.view')
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.monitoring') ? ' active' : '' }}" href="{{ route('admin.status.monitoring') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 17l6 -6l4 4l8 -8" /><path d="M14 7l7 0l0 7" /></svg>
                                    </span>
                                    <span class="nav-link-title">Monitoring</span>
                                </a>
                            </li>
                        @endcan
                        <li class="nav-section-title">Respond</li>
                        @can('status.incidents.view')
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.incidents.*') ? ' active' : '' }}" href="{{ route('admin.status.incidents.index') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4" /><path d="M12 17v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.84 2.75" /></svg>
                                    </span>
                                    <span class="nav-link-title">Incidents</span>
                                </a>
                            </li>
                        @endcan
                        @can('status.maintenance.view')
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.maintenances.*') ? ' active' : '' }}" href="{{ route('admin.status.maintenances.index') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l4 2" /></svg>
                                    </span>
                                    <span class="nav-link-title">Maintenance</span>
                                </a>
                            </li>
                        @endcan
                        <li class="nav-section-title">Configure</li>
                        @can('status.notifications.manage')
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle{{ request()->routeIs('admin.status.notifications.*') ? ' active' : '' }}" href="#sidebar-notifications" data-bs-toggle="dropdown" data-bs-auto-close="false" role="button" aria-expanded="{{ request()->routeIs('admin.status.notifications.*') ? 'true' : 'false' }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9h-18s3 -2 3 -9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                                    </span>
                                    <span class="nav-link-title">Notifications</span>
                                </a>
                                <div class="dropdown-menu{{ request()->routeIs('admin.status.notifications.*') ? ' show' : '' }}">
                                    <a class="dropdown-item{{ request()->routeIs('admin.status.notifications.channels') ? ' active' : '' }}" href="{{ route('admin.status.notifications.channels') }}">Channels</a>
                                    <a class="dropdown-item{{ request()->routeIs('admin.status.notifications.rules') ? ' active' : '' }}" href="{{ route('admin.status.notifications.rules') }}">Rules</a>
                                    <a class="dropdown-item{{ request()->routeIs('admin.status.notifications.subscribers') ? ' active' : '' }}" href="{{ route('admin.status.notifications.subscribers') }}">Subscribers</a>
                                </div>
                            </li>
                        @endcan
                        @can('status.settings.manage')
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.settings*') ? ' active' : '' }}" href="{{ route('admin.status.settings') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 6a2 2 0 1 0 -4 0a2 2 0 0 0 4 0z" /><path d="M4 6h8" /><path d="M16 6h4" /><path d="M8 12a2 2 0 1 0 -4 0a2 2 0 0 0 4 0z" /><path d="M4 12h2" /><path d="M10 12h10" /><path d="M17 18a2 2 0 1 0 -4 0a2 2 0 0 0 4 0z" /><path d="M4 18h11" /><path d="M19 18h1" /></svg>
                                    </span>
                                    <span class="nav-link-title">Settings</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link{{ request()->routeIs('admin.status.audit-logs') ? ' active' : '' }}" href="{{ route('admin.status.audit-logs') }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3h6a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1v-4a1 1 0 0 1 1 -1" /><path d="M9 12h6" /><path d="M9 16h6" /></svg>
                                    </span>
                                    <span class="nav-link-title">Audit log</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </div>
            </div>
        </aside>
        <div class="page-wrapper">
            <header class="navbar navbar-expand-md sticky-top d-none d-lg-flex d-print-none">
                <div class="container-xl">
                    <div class="navbar-nav flex-row order-md-last ms-auto">
                        <div class="nav-item me-2">
                            <x-theme-toggle />
                        </div>
                        @auth
                            <div class="nav-item dropdown">
                                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                                    <span class="avatar avatar-sm">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                                    <div class="d-none d-xl-block ps-2">
                                        <div>{{ auth()->user()->name }}</div>
                                        <div class="mt-1 small text-secondary">{{ auth()->user()->email }}</div>
                                    </div>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Logout</button>
                                    </form>
                                </div>
                            </div>
                        @endauth
                    </div>
                </div>
            </header>
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <div class="page-pretitle">@yield('page-pretitle', 'Status admin')</div>
                            <h2 class="page-title">@yield('page-title', 'Dashboard')</h2>
                        </div>
                        <div class="col-auto ms-auto d-print-none">
                            @yield('page-actions')
                        </div>
                    </div>
                </div>
            </div>
            <div class="page-body">
                <div class="container-xl">
                    @yield('content')
                </div>
            </div>
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">{{ config('app.name', 'Status') }} &middot; {{ date('Y') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
