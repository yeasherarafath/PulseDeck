<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-default="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="color-scheme" content="light dark" />
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
        <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
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
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.status.dashboard') }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                                </span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>
                        @foreach (['Services', 'Incidents', 'Maintenances', 'Monitoring', 'Settings'] as $item)
                            <li class="nav-item">
                                <span class="nav-link disabled">
                                    <span class="nav-link-title">{{ $item }}</span>
                                    <span class="badge bg-blue-lt ms-auto">soon</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </aside>
        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">
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
        <div class="page-wrapper">
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
