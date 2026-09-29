<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-default="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="color-scheme" content="light dark" />
    <title>@yield('title', config('app.name', 'Status'))</title>
    <script>
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
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <a href="{{ route('status.index') }}" class="navbar-brand navbar-brand-autodark">
                    <span class="status-dot status-dot-animated bg-green me-1"></span>
                    {{ config('app.name', 'Status') }}
                </a>
            </div>
            @yield('content')
        </div>
    </div>
    @vite('resources/js/app.js')
</body>
</html>
