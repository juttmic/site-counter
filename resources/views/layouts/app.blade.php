<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <!-- Bootstrap 5 JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    </head>
    <body class="bg-light">
        <div class="min-vh-100">
            @include('layouts.sidebar')
            
            <!-- Main Content Area -->
            <div class="main-content" style="min-height: 100vh;">
                <!-- Header -->
                <header class="bg-white border-bottom shadow-sm sticky-top" style="z-index: 999;">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center justify-content-between py-3 px-3 px-md-4">
                            <div class="flex-grow-1">
                                
                                {{ $header ?? '' }}
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                
                                <a href="{{ route('timers.create') }}" class="btn btn-primary btn-sm d-none d-sm-inline-flex">
                                    <i class="bi bi-plus-square me-1"></i>
                                    Create Timer
                                </a>
                                <button onclick="toggleTheme()" 
                                        type="button" 
                                        class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex">
                                    <span class="d-none dark-mode-text">Light Mode</span>
                                    <span class="light-mode-text">Dark Mode</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </header>
                
                <!-- Main Content -->
                <main class="p-3 p-md-4">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
