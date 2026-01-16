<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
      
        <link rel="icon" type="image/png" href="{{ asset('assets/images/small_dark.png') }}">
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            (function() {
                try {
                    var saved = localStorage.getItem('theme');
                    if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {}
            })();
            (function(){
                function toggleTheme() {
                    var isDark = document.documentElement.classList.toggle('dark');
                    try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (e) {}
                }

                window.toggleTheme = toggleTheme; // keep global for inline callers
                function bindThemeButtons(){
                    var buttons = document.querySelectorAll('[data-theme-toggle]');
                    buttons.forEach(function(btn){
                        btn.addEventListener('click', toggleTheme);
                    });
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindThemeButtons);
                } else {
                    bindThemeButtons();
                }
            })();
        </script>
        <style>
            .dark-mode-icon { display: none !important; }
            .dark .dark-mode-icon { display: inline-block !important; }
            .dark .light-mode-icon { display: none !important; }
        </style>
    </head>
    <body class="bg-light">
        <div class="min-vh-100">
            <!-- Main Content Area -->
            <div class="main-content" style="min-height: 100vh;">
                <!-- Header -->
                <header class="bg-white border-bottom shadow-sm sticky-top" style="z-index: 999;">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center justify-content-between gap-3 py-3 px-3 px-md-4 flex-nowrap">
                            <div class="d-flex align-items-center gap-3 flex-nowrap">
                                <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none text-dark">
                                    <x-application-logo class="d-block" style="height: 32px; width: auto;" />
                                </a>
                                <nav class="d-none d-md-flex align-items-center gap-3">
                                    {{-- Dashboard link removed --}}
                                </nav>
                                <div class="flex-grow-1 d-none d-md-block">
                                    {{ $header ?? '' }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <button class="btn btn-outline-secondary btn-sm d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileHeaderNav" aria-expanded="false" aria-controls="mobileHeaderNav" aria-label="Toggle navigation">
                                    <i class="bi bi-list"></i>
                                </button>
                                <a href="{{ route('timers.create') }}"
                                   class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center justify-content-center"
                                   aria-label="Create new counter">
                                    <i class="bi bi-plus-lg" style="margin-right:5px"></i> New Counter
                                </a>
                                <button onclick="toggleTheme()" 
                                        type="button" 
                                        class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center justify-content-center"
                                        aria-label="Toggle theme">
                                    <i class="bi bi-sun light-mode-icon"></i>
                                    <i class="bi bi-moon-stars dark-mode-icon"></i>
                                </button>
                                
                                <div class="dropdown d-none d-md-block">
                                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center rounded-circle p-0" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px;">
                                        <span class="fw-semibold text-uppercase small d-flex align-items-center justify-content-center" style="width: 100%; height: 100%;">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                                        <li class="dropdown-header d-flex align-items-center gap-2">
                                            <i class="bi bi-person-circle"></i>
                                            <span>{{ Auth::user()->name }}</span>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                                    <i class="bi bi-box-arrow-right"></i>
                                                    Logout
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <!-- Mobile collapse menu -->
                        <div class="collapse d-md-none border-top" id="mobileHeaderNav">
                            <div class="px-3 py-2 d-flex flex-column gap-2">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border" style="width: 36px; height: 36px;">
                                            <span class="fw-semibold text-uppercase small">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</span>
                                        </span>
                                        <span class="fw-semibold text-dark">{{ Auth::user()->name }}</span>
                                    </div>
                                    <button onclick="toggleTheme()" 
                                            type="button" 
                                            class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center"
                                            aria-label="Toggle theme">
                                        <i class="bi bi-sun light-mode-icon"></i>
                                        <i class="bi bi-moon-stars dark-mode-icon"></i>
                                    </button>
                                </div>
                                <nav class="d-flex flex-column gap-2">
                                    {{-- Dashboard link removed --}}
                                    <a href="{{ route('timers.create') }}" class="d-flex align-items-center text-decoration-none {{ request()->routeIs('timers.create') ? 'text-primary fw-semibold' : 'text-dark' }}">
                                        <i class="bi bi-plus-square me-2"></i> Create Ndew Counter
                                    </a>
                                </nav>
                                <div class="border-top pt-2">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                                            <i class="bi bi-box-arrow-right"></i>
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                
                <!-- Main Content -->
                <main class="p-2 p-sm-2 p-md-3 p-lg-4">
                    {{ $slot }}
                </main>
            </div>
        </div>
        
        <!-- Global Toast Container -->
        <div class="toast-container position-fixed top-0 start-100 translate-middle-x p-3" id="globalToastContainer" style="z-index: 1080; left:88% !important;"></div>

        <script>
            // Global toast function: window.showToast(message, variant)
            (function(){
                window.showToast = function(message, variant){
                    try {
                        var container = document.getElementById('globalToastContainer');
                        if (!container) return;
                        var toastEl = document.createElement('div');
                        toastEl.className = 'toast align-items-center text-bg-' + (variant || 'success') + ' border-0';
                        toastEl.setAttribute('role', 'status');
                        toastEl.setAttribute('aria-live', 'polite');
                        toastEl.setAttribute('aria-atomic', 'true');
                        toastEl.innerHTML = '<div class="d-flex">\
                            <div class="toast-body">' + (message || '') + '</div>\
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>\
                        </div>';
                        container.appendChild(toastEl);
                        var toast = new bootstrap.Toast(toastEl, { delay: 2000 });
                        toast.show();
                        toastEl.addEventListener('hidden.bs.toast', function(){ toastEl.remove(); });
                    } catch (e) {}
                };
            })();
            // Global confirm handler using SweetAlert2
            (function(){
                function bindConfirmations(){
                    document.addEventListener('submit', function(e){
                        var form = e.target;
                        if (!form || !form.matches('[data-confirm]')) return;
                        e.preventDefault();
                        var msg = form.getAttribute('data-confirm') || 'Are you sure?';
                        Swal.fire({
                            title: 'Please Confirm',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes',
                            cancelButtonText: 'Cancel'
                        }).then(function(result){
                            if (result.isConfirmed) {
                                form.submit();
                            }
                        });
                    }, true);
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindConfirmations);
                } else {
                    bindConfirmations();
                }
            })();
        </script>
    </body>
</html>
