<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-default="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="color-scheme" content="light dark" />
    @php($favicon = branding_asset(setting('favicon_path')))
    @if ($favicon)<link rel="icon" href="{{ $favicon }}" />@endif
    <title>@yield('meta-title', setting('app_name', config('app.name', 'PulseDeck')) . ' Status')</title>
    <meta name="description" content="@yield('meta-description', 'Live service status and uptime history.')" />
    <link rel="canonical" href="@yield('canonical-url', url()->current())" />
    <meta property="og:title" content="@yield('meta-title', setting('app_name', config('app.name', 'PulseDeck')) . ' Status')" />
    <meta property="og:description" content="@yield('meta-description', 'Live service status and uptime history.')" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary" />
    <script>
        // Pre-paint theme application (avoids a flash of the wrong theme).
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
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
                    <x-app-brand :href="route('status.index')" />
                </h1>
                <div class="navbar-nav flex-row order-md-last ms-auto">
                    <div class="nav-item">
                        <x-theme-toggle />
                    </div>
                </div>
            </div>
        </header>
        <div class="page-wrapper">
            <div class="page-body">
                <div class="container-xl">
                    @yield('content')
                </div>
            </div>
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item"><a href="{{ route('home') }}" class="link-secondary">System Status</a></li>
                                <li class="list-inline-item"><a href="{{ route('status.index') }}#active-incidents" class="link-secondary">Incident History</a></li>
                                <li class="list-inline-item"><a href="{{ route('api.status.index') }}" class="link-secondary">API</a></li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">{{ setting('footer_text') ?: config('app.name', 'Status').' · '.date('Y') }}</li>
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
