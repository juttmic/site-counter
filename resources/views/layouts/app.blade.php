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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
    <body class="font-sans antialiased bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <div class="min-h-screen">
            <div class="flex" x-data="{ collapsed: false }">
                @include('layouts.sidebar')
                <div class="flex-1 min-h-screen">
                    <header class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                        <div class="mx-auto py-3 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                            <div class="min-h-[24px]">
                                {{ $header ?? '' }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="collapsed = !collapsed" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600" title="Toggle sidebar">
                                    <i class="fa-solid fa-bars"></i>
                                </button>
                                <a href="{{ route('timers.create') }}" class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                                    <i class="fa-regular fa-square-plus mr-2"></i>
                                    Create Timer
                                </a>
                                <button onclick="toggleTheme()" type="button" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
                                    <span class="hidden dark:inline">Light Mode</span>
                                    <span class="dark:hidden">Dark Mode</span>
                                </button>
                            </div>
                        </div>
                    </header>
                    <main class="p-4 sm:p-6 lg:p-8">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
