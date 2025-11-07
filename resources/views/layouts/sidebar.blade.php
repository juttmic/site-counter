<!-- Desktop Sidebar -->
<aside id="desktopSidebar" class="sidebar-desktop d-none d-md-flex flex-column position-fixed top-0 start-0 h-100 bg-white border-end shadow-sm" style="width: 250px; z-index: 1000; transition: width 0.3s ease;">
    <!-- Logo/Brand -->
    <div class="d-flex align-items-center px-3 py-3 border-bottom">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none text-dark w-100">
            <x-application-logo class="d-block" style="height: 32px; width: auto;" />
            {{-- <span id="sidebarBrandText" class="ms-2 fw-semibold" style="transition: opacity 0.3s;">{{ config('app.name', 'TimerGIF App') }}</span> --}}
        </a>
        <button type="button"
                id="desktopSidebarToggle"
                onclick="toggleDesktopSidebar()"
                class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center justify-content-center sidebar-toggle-btn"
                title="Toggle sidebar">
            <i id="sidebarToggleIcon" class="bi bi-chevron-left"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-grow-1 overflow-y-auto py-3">
        <ul class="list-unstyled px-2 mb-0">
            <li class="mb-1">
                <a href="{{ route('dashboard') }}" 
                   class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->routeIs('dashboard') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                   title="Dashboard">
                    <i class="bi bi-speedometer2 me-3"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ route('timers.create') }}" 
                   class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->routeIs('timers.create') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                   title="Create Timer">
                    <i class="bi bi-plus-square me-3"></i>
                    <span class="sidebar-text">Create Timer</span>
                </a>
            </li>
            <li class="mb-1">
                <a href="{{ url('/skins') }}" 
                   class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->is('skins') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                   title="Skin Gallery">
                    <i class="bi bi-images me-3"></i>
                    <span class="sidebar-text">Skin Gallery</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer Section -->
    <div class="border-top p-3">
        <div id="sidebarUserName" class="text-muted small mb-2 text-center" style="transition: opacity 0.3s;">{{ Auth::user()->name }}</div>
        <button type="button" 
                onclick="toggleTheme()" 
                class="w-100 btn btn-light btn-sm mb-2 d-flex align-items-center justify-content-start">
            <i class="bi bi-moon-stars me-2 d-none dark-mode-icon"></i>
            <i class="bi bi-sun me-2 light-mode-icon"></i>
            <span class="sidebar-text">Toggle Theme</span>
        </button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" 
                    class="w-100 btn btn-light btn-sm d-flex align-items-center justify-content-start">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span class="sidebar-text">Log Out</span>
            </button>
        </form>
    </div>
</aside>

<!-- Mobile Sidebar (Bootstrap Offcanvas) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none text-dark gap-2">
                <x-application-logo class="d-block me-2" style="height: 32px; width: auto;" />
                <span>{{ config('app.name', 'TimerGIF App') }}</span>
            </a>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="mb-3">
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <a href="{{ route('dashboard') }}" 
                       class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->routeIs('dashboard') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                       data-bs-dismiss="offcanvas">
                        <i class="bi bi-speedometer2 me-3"></i>
                        Dashboard
                    </a>
                </li>
                <li class="mb-2">
                    <a href="{{ route('timers.create') }}" 
                       class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->routeIs('timers.create') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                       data-bs-dismiss="offcanvas">
                        <i class="bi bi-plus-square me-3"></i>
                        Create Timer
                    </a>
                </li>
                <li class="mb-2">
                    <a href="{{ url('/skins') }}" 
                       class="d-flex align-items-center px-3 py-2 rounded text-decoration-none {{ request()->is('skins') ? 'bg-light text-dark fw-medium' : 'text-dark' }}"
                       data-bs-dismiss="offcanvas">
                        <i class="bi bi-images me-3"></i>
                        Skin Gallery
                    </a>
                </li>
            </ul>
        </nav>
        <div class="border-top pt-3">
            <div class="text-muted small mb-2">{{ Auth::user()->name }}</div>
            <button type="button" 
                    onclick="toggleTheme()" 
                    class="w-100 btn btn-light btn-sm mb-2">
                <i class="bi bi-moon-stars me-2 d-none dark-mode-icon"></i>
                <i class="bi bi-sun me-2 light-mode-icon"></i>
                Toggle Theme
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-100 btn btn-light btn-sm">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Log Out
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Top Bar -->
<div class="d-md-none sticky-top bg-white border-bottom shadow-sm" style="z-index: 999;">
    <div class="d-flex align-items-center justify-content-between px-3 py-2">
        <button type="button" 
                class="btn btn-link text-dark p-2" 
                data-bs-toggle="offcanvas" 
                data-bs-target="#mobileSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="text-decoration-none text-dark fw-semibold">
            {{ config('app.name', 'TimerGIF App') }}
        </a>
        <button onclick="toggleTheme()" type="button" class="btn btn-link text-dark p-2">
            <i class="bi bi-moon-stars d-none dark-mode-icon"></i>
            <i class="bi bi-sun light-mode-icon"></i>
        </button>
    </div>
</div>

<!-- Sidebar Toggle Script -->
<script>
(function() {
    let sidebarCollapsed = false;
    const sidebar = document.getElementById('desktopSidebar');
    const toggleIcon = document.getElementById('sidebarToggleIcon');

    function applySidebarState(isCollapsed) {
        if (!sidebar) return;

        sidebar.classList.toggle('collapsed', isCollapsed);

        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            if (window.innerWidth >= 768) {
                mainContent.style.marginLeft = isCollapsed ? '70px' : '250px';
            } else {
                mainContent.style.marginLeft = '0';
            }
            mainContent.style.transition = 'margin-left 0.3s ease';
        }

        const userName = document.getElementById('sidebarUserName');
        if (userName) {
            userName.style.display = isCollapsed ? 'none' : '';
        }

        const sidebarTexts = document.querySelectorAll('.sidebar-text');
        sidebarTexts.forEach(text => {
            text.style.opacity = isCollapsed ? '0' : '1';
            text.style.display = isCollapsed ? 'none' : '';
        });

        if (toggleIcon) {
            toggleIcon.className = isCollapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
        }
    }

    function toggleSidebar(forceState) {
        if (!sidebar) return;

        if (typeof forceState === 'boolean') {
            sidebarCollapsed = forceState;
        } else {
            sidebarCollapsed = !sidebarCollapsed;
        }

        applySidebarState(sidebarCollapsed);

        try {
            localStorage.setItem('sidebarCollapsed', sidebarCollapsed.toString());
        } catch (e) {}
    }

    // Load saved state
    try {
        const saved = localStorage.getItem('sidebarCollapsed');
        if (saved === 'true') {
            sidebarCollapsed = true;
        }
    } catch (e) {}

    applySidebarState(sidebarCollapsed);

    window.toggleDesktopSidebar = toggleSidebar;

    document.addEventListener('DOMContentLoaded', function() {
        applySidebarState(sidebarCollapsed);
    });

    window.addEventListener('resize', function() {
        applySidebarState(sidebarCollapsed);
    });
})();
</script>

<style>
.sidebar-desktop a:hover {
    background-color: #f8f9fa !important;
}

.sidebar-text {
    transition: opacity 0.3s ease;
    white-space: nowrap;
}

.sidebar-desktop {
    transition: width 0.3s ease;
}

.sidebar-toggle-btn {
    margin-left: 20px;
    width: 38px;
    height: 36px;
    padding: 0 10px;
    border-radius: 12px;
    transition: all 0.3s ease;
    gap: 4px;
}

.sidebar-toggle-btn i {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.sidebar-desktop.collapsed {
    width: 70px !important;
}

.sidebar-desktop.collapsed .sidebar-text,
.sidebar-desktop.collapsed #sidebarUserName {
    display: none !important;
}

.sidebar-desktop.collapsed a,
.sidebar-desktop.collapsed button {
    justify-content: center !important;
}

.sidebar-desktop.collapsed a .bi,
.sidebar-desktop.collapsed button .bi {
    margin-right: 0 !important;
}

.sidebar-desktop.collapsed nav a {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
    text-align: center;
}

.sidebar-desktop.collapsed .border-top .btn {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
}

.sidebar-desktop.collapsed .sidebar-toggle-btn {
    margin-left: auto;
    margin-right: auto;
    width: 30px;
    height: 30px;
    margin-left: 16px;
    border-radius: 0 6px 6px 0px;
    margin-top: -7px;
    /* box-shadow: 0 0 5px #000; */
}
button#desktopSidebarToggle:hover {
    background: #2e3266;
}

.dark-mode-icon {
    display: none !important;
}

.dark .dark-mode-icon {
    display: inline-block !important;
}

.dark .light-mode-icon {
    display: none !important;
}

@media (max-width: 767.98px) {
    .main-content {
        margin-left: 0 !important;
    }
}
</style>
