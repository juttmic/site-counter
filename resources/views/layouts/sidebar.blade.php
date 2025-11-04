<aside class="bg-white border-r border-gray-200 w-64 min-h-screen hidden md:flex md:flex-col dark:bg-gray-800 dark:border-gray-700" :class="collapsed ? 'w-16' : 'w-64'">
    <div class="h-16 flex items-center px-4 border-b border-gray-100 dark:border-gray-700">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <x-application-logo class="block h-8 w-auto fill-current text-gray-800" />
            <span class="font-semibold text-gray-800" x-show="!collapsed" x-cloak>{{ config('app.name', 'TimerGIF App') }}</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto py-4">
        <ul class="px-2 space-y-1">
            <li>
                <a href="{{ route('dashboard') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white' }}">
                    <i class="fa-solid fa-gauge-high mr-2"></i>
                    <span x-show="!collapsed" x-cloak>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ route('timers.create') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('timers.create') ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white' }}">
                    <i class="fa-regular fa-square-plus mr-2"></i>
                    <span x-show="!collapsed" x-cloak>Create Timer</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/skins') }}" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('skins') ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white' }}">
                    <i class="fa-regular fa-images mr-2"></i>
                    <span x-show="!collapsed" x-cloak>Skin Gallery</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="border-t border-gray-100 p-3 dark:border-gray-700">
        <div class="text-xs text-gray-500 mb-2 dark:text-gray-300" x-show="!collapsed" x-cloak>{{ Auth::user()->name }}</div>
        <button type="button" data-theme-toggle onclick="toggleTheme()" class="w-full mb-2 inline-flex items-center justify-center px-3 py-2 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
            <i class="fa-regular fa-moon mr-2 hidden dark:inline"></i>
            <i class="fa-regular fa-sun mr-2 dark:hidden"></i>
            <span class="hidden dark:inline" x-show="!collapsed" x-cloak>Light Mode</span>
            <span class="dark:hidden" x-show="!collapsed" x-cloak>Dark Mode</span>
        </button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full inline-flex items-center justify-center px-3 py-2 rounded-md text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
                <i class="fa-solid fa-right-from-bracket mr-2"></i>
                Log Out
            </button>
        </form>
    </div>
</aside>

<!-- Mobile top bar + drawer toggle -->
<div x-data="{ open:false }" class="md:hidden">
    <div class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-4">
        <button @click="open = !open" class="p-2 rounded-md text-gray-600 hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <a href="{{ route('dashboard') }}" class="font-semibold text-gray-800">{{ config('app.name', 'TimerGIF App') }}</a>
        <span class="w-6"></span>
    </div>
    <div x-show="open" x-transition class="bg-white border-b border-gray-200">
        <ul class="py-2">
            <li><a class="block px-4 py-2" href="{{ route('dashboard') }}">Dashboard</a></li>
            <li><a class="block px-4 py-2" href="{{ route('timers.create') }}">Create Timer</a></li>
            <li><a class="block px-4 py-2" href="{{ url('/skins') }}">Skin Gallery</a></li>
            <li>
                <form method="POST" action="{{ route('logout') }}" class="px-4 py-2">
                    @csrf
                    <button type="submit">Log Out</button>
                </form>
            </li>
        </ul>
    </div>
</div>

